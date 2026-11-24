<?php

namespace App\Services\Analyst;

use Anthropic\Client;

/**
 * La única llamada a la API de Claude (analista de ventas y asistente de
 * ayuda). Va aparte para poder reemplazarla en los tests sin salir a internet.
 */
class ClaudeTransport
{
    public function configured(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array{effort?: string, max_tokens?: int, timeout?: int, model?: string}  $options
     */
    public function send(string $system, array $messages, array $tools, array $options = []): object
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));
        $model = (string) ($options['model'] ?? config('services.anthropic.model'));

        $params = [
            'model' => $model,
            'maxTokens' => $options['max_tokens'] ?? 32000,
            'system' => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            'messages' => $messages,
            'tools' => $tools ?: null,
            'outputConfig' => ['effort' => $options['effort'] ?? 'xhigh'],
            'requestOptions' => ['timeout' => $options['timeout'] ?? 400],
        ];
        // Si el modelo principal declina una consulta, la API la reintenta sola con el modelo de respaldo
        // que corresponda. Haiku no tiene ese respaldo en el servidor: ahí no se manda.
        if (! str_starts_with($model, 'claude-haiku')) {
            $params['fallbacks'] = 'default';
            $params['betas'] = ['server-side-fallback-2026-07-01'];
        }

        return $client->beta->messages->create(...$params);
    }
}
