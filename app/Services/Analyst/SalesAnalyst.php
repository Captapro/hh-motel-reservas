<?php

namespace App\Services\Analyst;

use App\Models\AnalystNote;
use App\Models\AnalystRun;
use App\Models\AnalystSetting;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HistoricalSale;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Throwable;

/**
 * Analista de ventas: Claude con cuatro herramientas de solo lectura sobre
 * SalesDataService. Responde la pregunta de un AnalystRun y deja la
 * respuesta guardada en el mismo registro.
 */
class SalesAnalyst
{
    private const TZ = 'America/Santiago';

    private const MAX_STEPS = 10;

    /** Preguntas anteriores de la misma conversación que se le recuerdan al modelo. */
    private const HISTORY = 6;

    public function __construct(private SalesDataService $data, private ClaudeTransport $claude, private HistoricalDataService $historical) {}

    public function run(AnalystRun $run): void
    {
        $run->update(['status' => 'trabajando']);
        $steps = [];
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            $messages = $this->history($run);
            $messages[] = ['role' => 'user', 'content' => $run->question];

            for ($i = 0; $i < self::MAX_STEPS; $i++) {
                $canNote = (bool) User::find($run->user_id)?->isAdministrador();
                $response = $this->claude->send($this->systemPrompt($canNote), $messages, $this->tools($canNote));
                $inputTokens += (int) ($response->usage->inputTokens ?? 0) + (int) ($response->usage->cacheReadInputTokens ?? 0) + (int) ($response->usage->cacheCreationInputTokens ?? 0);
                $outputTokens += (int) ($response->usage->outputTokens ?? 0);

                if ($response->stopReason !== 'tool_use') {
                    $answer = $this->text($response);
                    if ($response->stopReason === 'refusal') {
                        $answer = $answer ?: 'El analista no pudo responder esa consulta. Prueba reformulándola.';
                    } elseif ($response->stopReason === 'max_tokens') {
                        $answer .= "\n\n_(La respuesta quedó cortada por largo. Pídele que la resuma o que siga.)_";
                    }
                    $run->update([
                        'status' => 'listo',
                        'answer' => trim($answer) ?: 'Sin respuesta.',
                        'steps' => $steps,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                    ]);

                    return;
                }

                $results = [];
                foreach ($response->content as $block) {
                    if (($block->type ?? null) !== 'tool_use') {
                        continue;
                    }
                    $input = is_array($block->input) ? $block->input : (array) $block->input;
                    $steps[] = $this->stepLabel($block->name, $input);
                    $run->update(['steps' => $steps]);
                    try {
                        $output = json_encode($this->callTool($block->name, $input, $run), JSON_UNESCAPED_UNICODE);
                        $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => $output];
                    } catch (Throwable $e) {
                        $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => 'Error: '.$e->getMessage(), 'isError' => true];
                    }
                }
                $messages[] = ['role' => 'assistant', 'content' => $response->content];
                $messages[] = ['role' => 'user', 'content' => $results];
            }

            throw new \DomainException('El análisis tomó demasiados pasos. Prueba con una pregunta más acotada.');
        } catch (Throwable $e) {
            report($e);
            $run->update([
                'status' => 'error',
                'error' => $this->friendlyError($e),
                'steps' => $steps,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]);
        }
    }

    /** Preguntas y respuestas anteriores de la conversación, solo como texto. */
    private function history(AnalystRun $run): array
    {
        return AnalystRun::where('thread', $run->thread)
            ->where('id', '<', $run->id)
            ->where('status', 'listo')
            ->latest('id')->take(self::HISTORY)->get()->reverse()
            ->flatMap(fn (AnalystRun $r) => [
                ['role' => 'user', 'content' => $r->question],
                ['role' => 'assistant', 'content' => $r->answer],
            ])->values()->all();
    }

    private function text(object $response): string
    {
        $out = '';
        foreach ($response->content as $block) {
            if (($block->type ?? null) === 'text') {
                $out .= $block->text;
            }
        }

        return $out;
    }

    private function callTool(string $name, array $input, AnalystRun $run): array
    {
        return match ($name) {
            'guardar_nota' => $this->saveNote($input, $run),
            'resumen_ventas' => $this->data->overview(...$this->range($input)),
            'ocupacion' => $this->data->occupancy(...array_slice($this->range($input), 0, 2)),
            'configuracion_precios' => $this->data->pricingConfig(),
            'tendencia_mensual' => $this->data->monthlyTrend(),
            'ventas_historicas' => $this->historical->summary(...array_slice($this->historicalRange($input), 0, 2)),
            'fidelidad_clientes' => $this->historical->loyalty(...array_slice($this->historicalRange($input), 0, 2)),
            'historial_cambios_precios' => $this->data->priceChanges(...array_slice($this->range($input), 0, 2)),
            default => throw new \InvalidArgumentException("Herramienta desconocida: {$name}"),
        };
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?int} */
    private function range(array $input): array
    {
        $from = Carbon::parse((string) ($input['desde'] ?? ''), self::TZ)->startOfDay();
        $to = Carbon::parse((string) ($input['hasta'] ?? ''), self::TZ)->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > 400) {
            throw new \InvalidArgumentException('El rango máximo es de 400 días.');
        }
        $categoryId = null;
        if (! empty($input['categoria'])) {
            $categoryId = RoomCategory::whereRaw('LOWER(name) = ?', [mb_strtolower((string) $input['categoria'])])->value('id');
            if (! $categoryId) {
                throw new \InvalidArgumentException('No existe la categoría '.$input['categoria'].'. Usa los nombres de configuracion_precios.');
            }
        }

        return [$from, $to, $categoryId];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function historicalRange(array $input): array
    {
        $from = Carbon::parse((string) ($input['desde'] ?? ''), self::TZ)->startOfDay();
        $to = Carbon::parse((string) ($input['hasta'] ?? ''), self::TZ)->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > 1100) {
            throw new \InvalidArgumentException('El rango máximo de ventas históricas es de unos 3 años.');
        }

        return [$from, $to];
    }

    /** Guarda un dato del negocio que el administrador acaba de contar en la conversación. */
    private function saveNote(array $input, AnalystRun $run): array
    {
        $user = User::find($run->user_id);
        if (! $user?->isAdministrador()) {
            return ['guardada' => false, 'motivo' => 'Solo el administrador puede guardar notas del negocio.'];
        }

        $body = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['nota'] ?? '')));
        if (mb_strlen($body) < 5) {
            return ['guardada' => false, 'motivo' => 'La nota es muy corta.'];
        }
        $body = mb_substr($body, 0, AnalystNote::MAX_LENGTH);
        $existing = AnalystNote::pluck('body');
        if ($existing->contains(fn (string $n) => mb_strtolower($n) === mb_strtolower($body))) {
            return ['guardada' => false, 'motivo' => 'Ya estaba anotada.'];
        }
        if ($existing->count() >= AnalystNote::MAX_NOTES) {
            return ['guardada' => false, 'motivo' => 'Hay '.AnalystNote::MAX_NOTES.' notas: pídele al administrador que borre alguna.'];
        }

        $note = AnalystNote::create(['body' => $body, 'source' => 'analista', 'created_by' => $user->id]);
        AuditLog::record($user->id, 'analista.nota', 'AnalystNote', $note->id, null, ['body' => $body, 'origen' => 'conversación']);

        return ['guardada' => true, 'notas_en_total' => $existing->count() + 1];
    }

    private function stepLabel(string $name, array $input): string
    {
        $range = isset($input['desde'], $input['hasta']) ? ' ('.$input['desde'].' a '.$input['hasta'].(! empty($input['categoria']) ? ', '.$input['categoria'] : '').')' : '';

        return match ($name) {
            'resumen_ventas' => 'Revisando ventas'.$range,
            'ocupacion' => 'Revisando ocupación'.$range,
            'configuracion_precios' => 'Revisando tarifas, ofertas y upsells',
            'tendencia_mensual' => 'Revisando los últimos 12 meses',
            'ventas_historicas' => 'Revisando las ventas anteriores al sistema'.$range,
            'fidelidad_clientes' => 'Revisando cuánto vuelven los clientes'.$range,
            'historial_cambios_precios' => 'Revisando cambios de precios y promociones'.$range,
            'guardar_nota' => 'Anotando un dato del negocio',
            default => $name,
        };
    }

    private function friendlyError(Throwable $e): string
    {
        $class = class_basename($e);

        return match (true) {
            str_contains($class, 'Authentication') => 'La clave de la API de Claude no es válida. Revisa ANTHROPIC_API_KEY en Render.',
            str_contains($class, 'RateLimit'), str_contains($class, 'Overloaded') => 'El servicio de IA está ocupado. Intenta de nuevo en un minuto.',
            str_contains($class, 'Billing') || str_contains($class, 'Permission') => 'La cuenta de la API de Claude no tiene saldo o permisos. Revisa console.anthropic.com.',
            str_contains($class, 'Connection') || str_contains($class, 'Timeout') => 'No se pudo conectar con el servicio de IA. Intenta de nuevo.',
            $e instanceof \DomainException => $e->getMessage(),
            default => 'No se pudo completar el análisis. Intenta de nuevo.',
        };
    }

    private function tools(bool $canNote = false): array
    {
        $range = [
            'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (hora de Chile)'],
            'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD, inclusive'],
        ];

        $tools = [
            [
                'name' => 'resumen_ventas',
                'description' => 'Ventas pagadas de un rango de fechas: totales y comparación con el período anterior del mismo largo, cortes por categoría, duración, día de semana, hora de llegada, mapa día×hora, tarifa aplicada, canal, anticipación, ofertas/cupones, consumos y upsells, atrasos, clientes nuevos vs. que vuelven, cancelaciones y reservas vencidas sin pago. Máximo 400 días.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $range + ['categoria' => ['type' => 'string', 'description' => 'Opcional: nombre exacto de una categoría para filtrar']],
                    'required' => ['desde', 'hasta'],
                ],
            ],
            [
                'name' => 'ocupacion',
                'description' => 'Porcentaje de ocupación (horas ocupadas / horas disponibles) de un rango: promedio, por categoría y por día de semana × hora. Sirve para ver horarios llenos (subir precio, quitar descuentos) y vacíos (ofertas). Máximo 400 días.',
                'inputSchema' => ['type' => 'object', 'properties' => $range, 'required' => ['desde', 'hasta']],
            ],
            [
                'name' => 'configuracion_precios',
                'description' => 'Configuración vigente: categorías y cuántos Playrooms tiene cada una, tarifas con sus horarios y precios por categoría y duración, hora extra, ofertas y cupones activos, upsells activos, estadía corta y días con tarifa forzada.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'tendencia_mensual',
                'description' => 'Últimos 12 meses mes a mes: reservas, venta total, venta de Playrooms y de consumos, ticket promedio, descuentos y duración promedio.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'ventas_historicas',
                'description' => 'Ventas de las PLANILLAS de recepción (no están en resumen_ventas, que solo trae lo registrado en el sistema): estadías, venta por mes, categoría, horas pagadas, día de semana, hora de ingreso, turno, medio de pago, descuentos y cuántas personas distintas volvieron. La respuesta indica qué fechas hay cargadas (cobertura_cargada). Hasta que el sistema tenga ventas propias, es la FUENTE PRINCIPAL para precios, franjas, categorías, estacionalidad y fidelidad; también sirve para comparar contra años anteriores. No tiene estados de pago ni ocupación. Máximo ~3 años por consulta.',
                'inputSchema' => ['type' => 'object', 'properties' => $range, 'required' => ['desde', 'hasta']],
            ],
            [
                'name' => 'fidelidad_clientes',
                'description' => 'Cuánto vuelve la gente según las planillas, en un rango de fechas: personas distintas, cuántas volvieron dentro del período, cuántas ya habían venido antes, visitas por persona por mes y distribución de visitas. Solo cifras, sin nombres. Úsala para preguntas de fidelidad, recurrencia, "clientes que vuelven" o para dimensionar campañas de retención.',
                'inputSchema' => ['type' => 'object', 'properties' => $range, 'required' => ['desde', 'hasta']],
            ],
            [
                'name' => 'historial_cambios_precios',
                'description' => 'Cambios hechos en el sistema a tarifas, precios, ofertas, cupones, upsells y estadía corta en un rango, con fecha y valor antes/después. Úsalo para medir el efecto de un cambio comparando ventas y ocupación antes y después de la fecha. Máximo 400 días.',
                'inputSchema' => ['type' => 'object', 'properties' => $range, 'required' => ['desde', 'hasta']],
            ],
        ];

        if ($canNote) {
            $tools[] = [
                'name' => 'guardar_nota',
                'description' => 'Anota un dato estable del negocio que el administrador acaba de contarte (un costo, una meta, un precio de la competencia, una regla, una fecha clave). Una frase corta y autocontenida con las cifras como las dijo. No la uses para opiniones tuyas, supuestos, datos personales de clientes ni claves.',
                'inputSchema' => ['type' => 'object', 'properties' => ['nota' => ['type' => 'string', 'description' => 'La nota, máximo 300 caracteres. Ej.: El aseo y la lavandería cuestan $2.500 por Playroom usado.']], 'required' => ['nota']],
            ];
        }

        return $tools;
    }

    private function systemPrompt(bool $canNote = false): string
    {
        $today = now(self::TZ)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
        $legacy = trim((string) AnalystSetting::current()->business_context);
        $legacy = $legacy !== trim(AnalystSetting::TEMPLATE) ? $legacy : '';
        $notes = AnalystNote::orderBy('id')->pluck('body')->map(fn (string $n) => '- '.$n)->implode("\n");
        $context = trim(($legacy !== '' ? $legacy."\n\n" : '').($notes !== '' ? "NOTAS DEL NEGOCIO\n".$notes : ''));
        $context = $context !== ''
            ? $context
            : 'El dueño todavía no cargó objetivos, costos ni precios de la competencia. Si una recomendación depende de esos datos, dilo y pídeselos en la conversación'.($canNote ? ' (lo que te cuente, anótalo con guardar_nota)' : '').'.';
        $coverage = $this->historical->coverage();
        $firstBooking = Booking::whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])->min('starts_at');
        $loadedMonths = $coverage ? HistoricalSale::query()->get(['sold_on'])->map(fn (HistoricalSale $h) => $h->sold_on->format('Y-m'))->unique()->count() : 0;
        $dataSources = 'Planillas de recepción cargadas: '.($coverage ? 'desde '.$coverage['desde'].' hasta '.$coverage['hasta'].' ('.$loadedMonths.' '.($loadedMonths === 1 ? 'mes' : 'meses').')' : 'ninguna todavía').'. Ventas registradas en el sistema: '.($firstBooking ? 'desde '.Carbon::parse($firstBooking, self::TZ)->toDateString() : 'todavía ninguna').'.';
        $noteRule = $canNote
            ? "\n- Si el usuario te cuenta un dato estable de su negocio (un costo, una meta, un precio de la competencia, una regla, una fecha clave), anótalo con guardar_nota: una frase corta y autocontenida, con las cifras como las dijo. No anotes opiniones tuyas, supuestos, datos personales de clientes ni claves, ni lo que ya está en las notas. Después dile en una línea qué anotaste (\"Anoté: …\")."
            : '';

        return <<<PROMPT
Eres el director de revenue management de HH Motel, un motel en Chile: un estratega de precios con experiencia en hotelería por horas. Hablas con el dueño o el administrador. Hoy es {$today} (hora de Chile).

## Cómo funciona el negocio
- Se venden estadías por bloques de tiempo (por ejemplo 3, 6 o 12 horas) en Playrooms agrupados por categoría. El precio depende de la categoría, la duración y la tarifa vigente según día y hora.
- Hay ofertas que se aplican solas en ciertos días u horarios, cupones, upsells que se ofrecen al reservar o al hacer check-in (subir de categoría, más tiempo, packs), consumos (productos y combos), hora extra y estadía corta (entrar tarde pagando menos).
- Canales: reserva en línea (catálogo, paga por Mercado Pago), reserva asistida por recepción y presencial.
- Montos en pesos chilenos, sin decimales.

## Contexto que cargó el dueño
<contexto_negocio>
{$context}
</contexto_negocio>

## Datos disponibles
{$dataSources}
- Las planillas (herramientas ventas_historicas y fidelidad_clientes) son datos REALES de recepción y, mientras el sistema no tenga semanas de ventas propias, son tu fuente principal: úsalas para "cómo vamos", precios, franjas horarias, categorías, estacionalidad y fidelidad de clientes. Nunca digas que no hay datos si las planillas cubren ese período.
- Para fechas posteriores al inicio del sistema usa resumen_ventas y ocupacion; para las anteriores, las planillas. No sumes ambas fuentes sin avisar: son registros distintos (las planillas no traen estados de pago ni ocupación).

## Cómo piensas
- La métrica principal es la venta por Playroom disponible por hora (venta ÷ Playrooms ÷ horas del período), no la cantidad de reservas. Un Playroom vendido barato en un horario que se iba a llenar igual es plata perdida; un Playroom vacío en un horario muerto también.
- Segmenta la demanda por día y franja horaria (tardes de semana, noches de semana, viernes y sábado en la noche, madrugada, domingo). Cada franja tiene su propia disposición a pagar.
- Franjas sobre ~80 % de ocupación: hay demanda que no entra. Sube precio, quita descuentos, privilegia duraciones cortas que rotan más o exige bloques mínimos según lo que deje más por hora. Las reservas en línea vencidas sin pago en esas franjas son una señal de fricción o de precio.
- Franjas bajo ~40 %: estimula sin canibalizar. Usa ofertas acotadas a esa franja (día y hora), estadía corta, packs con consumo o subir de categoría a precio fijo, antes que bajar el precio de lista. Un descuento que se aplica también a quien igual iba a venir es pérdida pura: revisa en qué franjas se usaron las ofertas.
- Escalera de precios: revisa que el salto entre categorías y entre duraciones tenga sentido (precio por hora decreciente con la duración, pero sin que la estadía larga canibalice las cortas en franjas de alta demanda). Busca categorías mal posicionadas: alta ocupación y precio bajo, o baja ocupación y precio alto.
- Ticket promedio: mide qué parte de las reservas compra consumo o upsell y propone cómo subirlo (qué ofrecer, a qué precio, en qué momento).
- Separa correlación de causa. Si cambió un precio o una oferta (herramienta historial_cambios_precios), compara períodos equivalentes antes y después (mismos días de semana, mismo largo) y considera estacionalidad.
- Si hay costos cargados, razona con margen y no solo con venta. Si hay precios de la competencia, ubica cada categoría frente a ellos.
- Cuando no hay datos suficientes (pocas semanas, pocas reservas), dilo y propone un experimento en vez de una conclusión.

## Cómo trabajas
- Consulta los datos con las herramientas antes de afirmar algo. Para una recomendación de precios revisa al menos ventas, ocupación y configuración vigente. Compara contra un período anterior o más largo. No inventes cifras.
- Tú no cambias nada en el sistema. Indica dónde se hace cada cambio: Administración → Tarifas, Ofertas, Cupones, Upsells, Estadía corta o Calendario.
- Cuando uses las planillas aclara que son otra fuente: solo tiene lo que recepción anotaba y no incluye estados de pago ni ocupación. Para fidelidad de clientes usa fidelidad_clientes (solo cifras).
- No pidas ni menciones datos personales de clientes; trabajas con cifras agregadas.{$noteRule}

## Cómo respondes
- Escribe en español de Chile, tuteando y sin voseo. Ve al grano: primero la conclusión en 2 o 3 líneas, después el detalle.
- Cada recomendación incluye: qué cambiar exactamente (por ejemplo "subir PLUS 3 h en la tarifa HOT de \$45.000 a \$48.000, viernes y sábado de 21:00 a 02:00"), por qué (los datos), impacto estimado en pesos al mes con el supuesto que usaste, el riesgo, y cómo medirlo en 2 a 4 semanas. Ordénalas por impacto y marca cuáles son de bajo riesgo para partir ya.
- Cuando corresponda, termina con un plan de acción corto: qué hacer esta semana, qué medir y cuándo volver a revisar.
- Usa títulos cortos, listas y tablas en Markdown. Montos como \$45.000.
PROMPT;
    }
}
