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
     * @param  array{effort?: string, max_tokens?: int, timeout?: int}  $options
     */
    public function send(string $system, array $messages, array $tools, array $options = []): object
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));

        return $client->beta->messages->create(
            model: (string) config('services.anthropic.model'),
            maxTokens: $options['max_tokens'] ?? 32000,
            system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            messages: $messages,
            tools: $tools ?: null,
            outputConfig: ['effort' => $options['effort'] ?? 'xhigh'],
            // Si el modelo principal declina una consulta, la API la reintenta
            // sola con el modelo de respaldo que corresponda.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => $options['timeout'] ?? 400],
        );
    }
}
