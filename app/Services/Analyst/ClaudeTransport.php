<?php

namespace App\Services\Analyst;

use Anthropic\Client;

/**
 * La única llamada a la API de Claude. Va aparte para poder reemplazarla en
 * los tests sin salir a internet.
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
     */
    public function send(string $system, array $messages, array $tools): object
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));

        return $client->beta->messages->create(
            model: (string) config('services.anthropic.model'),
            maxTokens: 16000,
            system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            messages: $messages,
            tools: $tools,
            outputConfig: ['effort' => 'high'],
            // Si el modelo principal declina una consulta, la API la reintenta
            // sola con el modelo de respaldo que corresponda.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => 170],
        );
    }
}
