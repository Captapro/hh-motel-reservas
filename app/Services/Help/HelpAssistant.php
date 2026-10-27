<?php

namespace App\Services\Help;

use App\Models\HelpQuestion;
use App\Models\HelpSetting;
use App\Models\User;
use App\Services\Analyst\ClaudeTransport;
use Throwable;

/**
 * Asistente de ayuda para el equipo: responde cómo se hace algo en el
 * sistema según el manual (resources/help/manual-anfitriones.md) y las
 * reglas de la casa que escribe el administrador. No ve ventas, clientes ni
 * claves, y no ejecuta nada.
 */
class HelpAssistant
{
    /** Turnos anteriores del mismo chat que se le recuerdan al modelo. */
    private const HISTORY = 6;

    public function __construct(private ClaudeTransport $claude) {}

    /**
     * @param  array<int, array{q: string, a: string}>  $history
     */
    public function answer(User $user, string $question, ?string $page, array $history): HelpQuestion
    {
        $messages = [];
        foreach (array_slice($history, -self::HISTORY) as $turn) {
            $messages[] = ['role' => 'user', 'content' => mb_substr((string) ($turn['q'] ?? ''), 0, 1000)];
            $messages[] = ['role' => 'assistant', 'content' => mb_substr((string) ($turn['a'] ?? ''), 0, 4000)];
        }
        $pageNote = $page ? "\n\n(Estoy en la pantalla: {$page})" : '';
        $messages[] = ['role' => 'user', 'content' => $question.$pageNote];

        $record = new HelpQuestion([
            'user_id' => $user->id,
            'role' => $user->role,
            'page' => $page ? mb_substr($page, 0, 120) : null,
            'question' => $question,
        ]);

        try {
            $response = $this->claude->send($this->systemPrompt(), $messages, [], [
                'effort' => 'medium',
                'max_tokens' => 4000,
                'timeout' => 50,
            ]);
            $text = '';
            foreach ($response->content as $block) {
                if (($block->type ?? null) === 'text') {
                    $text .= $block->text;
                }
            }
            $record->fill([
                'answer' => trim($text) ?: 'No tengo una respuesta para eso. Consulta al administrador.',
                'status' => 'listo',
                'input_tokens' => (int) ($response->usage->inputTokens ?? 0) + (int) ($response->usage->cacheReadInputTokens ?? 0) + (int) ($response->usage->cacheCreationInputTokens ?? 0),
                'output_tokens' => (int) ($response->usage->outputTokens ?? 0),
            ]);
        } catch (Throwable $e) {
            report($e);
            $record->fill(['status' => 'error', 'answer' => 'El asistente no está disponible en este momento. Intenta de nuevo en un minuto o consulta al administrador.']);
        }

        $record->save();

        return $record;
    }

    private function systemPrompt(): string
    {
        $manual = (string) @file_get_contents(resource_path('help/manual-anfitriones.md'));
        $rules = trim((string) HelpSetting::current()->house_rules);
        if ($rules === '' || $rules === trim(HelpSetting::TEMPLATE)) {
            $rules = 'El administrador todavía no cargó reglas de la casa. Si la pregunta depende de una política del motel (devoluciones, atrasos, excepciones), di que lo consulte con el administrador.';
        }

        return <<<PROMPT
Eres el asistente de ayuda del sistema de reservas de HH Motel. Ayudas al equipo del turno (anfitriones y recepción) a usar el sistema: cómo hacer una reserva, un check-in, cobrar, agregar consumos, vender tiempo, finalizar, pasar a aseo, etc.

## Manual del sistema
<manual>
{$manual}
</manual>

## Reglas de la casa (las escribe el administrador; mandan sobre tu criterio)
<reglas_casa>
{$rules}
</reglas_casa>

## Cómo respondes
- Español de Chile, tuteando y sin voseo. Respuestas cortas y prácticas: el anfitrión está atendiendo.
- Da los pasos numerados con los nombres exactos de los botones y pantallas del manual, en **negrita**. Si sabes en qué pantalla está, parte desde ahí.
- Responde solo con lo que dice el manual o las reglas de la casa. Si no está, dilo claramente y sugiere consultar al administrador. No inventes botones, pantallas ni políticas.
- Si la situación implica dinero que no cuadra, devoluciones, descuentos no autorizados, conflictos con clientes o seguridad, recomienda avisar al administrador y, si hay riesgo para personas, llamar a emergencias.
- No tienes acceso a reservas, clientes, ventas ni claves de acceso, y no puedes hacer cambios en el sistema. Si te piden un dato concreto (por ejemplo una clave o el saldo de una reserva), explica dónde verlo en el sistema.
PROMPT;
    }
}
