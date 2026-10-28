<?php

namespace App\Services\Analyst;

use App\Models\AnalystRun;
use App\Models\AnalystSetting;
use App\Models\RoomCategory;
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

    public function __construct(private SalesDataService $data, private ClaudeTransport $claude) {}

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
                $response = $this->claude->send($this->systemPrompt(), $messages, $this->tools());
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
                        $output = json_encode($this->callTool($block->name, $input), JSON_UNESCAPED_UNICODE);
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

    private function callTool(string $name, array $input): array
    {
        return match ($name) {
            'resumen_ventas' => $this->data->overview(...$this->range($input)),
            'ocupacion' => $this->data->occupancy(...array_slice($this->range($input), 0, 2)),
            'configuracion_precios' => $this->data->pricingConfig(),
            'tendencia_mensual' => $this->data->monthlyTrend(),
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

    private function stepLabel(string $name, array $input): string
    {
        $range = isset($input['desde'], $input['hasta']) ? ' ('.$input['desde'].' a '.$input['hasta'].(! empty($input['categoria']) ? ', '.$input['categoria'] : '').')' : '';

        return match ($name) {
            'resumen_ventas' => 'Revisando ventas'.$range,
            'ocupacion' => 'Revisando ocupación'.$range,
            'configuracion_precios' => 'Revisando tarifas, ofertas y upsells',
            'tendencia_mensual' => 'Revisando los últimos 12 meses',
            'historial_cambios_precios' => 'Revisando cambios de precios y promociones'.$range,
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

    private function tools(): array
    {
        $range = [
            'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (hora de Chile)'],
            'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD, inclusive'],
        ];

        return [
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
                'name' => 'historial_cambios_precios',
                'description' => 'Cambios hechos en el sistema a tarifas, precios, ofertas, cupones, upsells y estadía corta en un rango, con fecha y valor antes/después. Úsalo para medir el efecto de un cambio comparando ventas y ocupación antes y después de la fecha. Máximo 400 días.',
                'inputSchema' => ['type' => 'object', 'properties' => $range, 'required' => ['desde', 'hasta']],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        $today = now(self::TZ)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
        $context = trim((string) AnalystSetting::current()->business_context);
        $context = $context !== '' && $context !== trim(AnalystSetting::TEMPLATE)
            ? $context
            : 'El dueño todavía no cargó objetivos, costos ni precios de la competencia. Si una recomendación depende de esos datos, dilo y sugiere cargarlos en "Contexto del negocio".';

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
- No pidas ni menciones datos personales de clientes; trabajas con cifras agregadas.

## Cómo respondes
- Escribe en español de Chile, tuteando y sin voseo. Ve al grano: primero la conclusión en 2 o 3 líneas, después el detalle.
- Cada recomendación incluye: qué cambiar exactamente (por ejemplo "subir PLUS 3 h en la tarifa HOT de \$45.000 a \$48.000, viernes y sábado de 21:00 a 02:00"), por qué (los datos), impacto estimado en pesos al mes con el supuesto que usaste, el riesgo, y cómo medirlo en 2 a 4 semanas. Ordénalas por impacto y marca cuáles son de bajo riesgo para partir ya.
- Cuando corresponda, termina con un plan de acción corto: qué hacer esta semana, qué medir y cuándo volver a revisar.
- Usa títulos cortos, listas y tablas en Markdown. Montos como \$45.000.
PROMPT;
    }
}
