<?php

namespace App\Services\Customers;

use App\Services\Analyst\ClaudeTransport;
use App\Support\Rut;
use Throwable;

/**
 * Lee la foto de un carnet de identidad (frente) y devuelve RUN, nombres y apellidos para
 * autocompletar el formulario. La foto NO se guarda: llega, se lee y se descarta. Todo lo que
 * devuelve es una propuesta que recepción revisa antes de guardar; el RUN se valida con su dígito.
 */
class IdCardReader
{
    public function __construct(private ClaudeTransport $claude) {}

    public function configured(): bool
    {
        return $this->claude->configured();
    }

    /**
     * @return array{ok: bool, rut: ?string, first_name: ?string, last_name: ?string, nationality: ?string, birth_date: ?string, expires_on: ?string, foreigner: bool, error: ?string}
     */
    public function read(string $imageBytes, string $mediaType): array
    {
        $blank = ['ok' => false, 'rut' => null, 'first_name' => null, 'last_name' => null, 'nationality' => null,
            'birth_date' => null, 'expires_on' => null, 'foreigner' => false, 'error' => null];

        if (! $this->configured()) {
            return ['error' => 'La lectura por foto no está configurada.'] + $blank;
        }

        try {
            $response = $this->claude->send($this->systemPrompt(), [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => base64_encode($imageBytes)]],
                    ['type' => 'text', 'text' => 'Lee este documento y responde solo el JSON pedido.'],
                ],
            ]], [], [
                'effort' => 'low',
                'max_tokens' => 600,
                'timeout' => 45,
                'model' => (string) (config('services.anthropic.vision_model') ?: config('services.anthropic.model')),
            ]);
        } catch (Throwable $e) {
            report($e);

            return ['error' => 'No se pudo leer la foto ahora. Escribe los datos a mano.'] + $blank;
        }

        $text = '';
        foreach ($response->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        return $this->parse($text) ?? (['error' => 'No se pudo leer el carnet. Toma la foto de nuevo, con buena luz y sin reflejos.'] + $blank);
    }

    /** Convierte la respuesta del modelo en datos limpios y validados (null si no sirve). */
    public function parse(string $text): ?array
    {
        if (! preg_match('/\{.*\}/s', $text, $m)) {
            return null;
        }
        $data = json_decode($m[0], true);
        if (! is_array($data) || empty($data['legible'])) {
            return null;
        }

        $rutRaw = trim((string) ($data['rut'] ?? ''));
        $rut = $rutRaw !== '' && Rut::isValid($rutRaw) ? Rut::normalize($rutRaw) : null;
        if (! $rut) {
            // Sin un RUN válido no hay nada confiable que completar (los pasaportes quedan para escribir a mano).
            return null;
        }

        $clean = fn ($v) => ($t = trim(preg_replace('/\s+/', ' ', (string) $v))) !== '' ? mb_convert_case(mb_strtolower($t), MB_CASE_TITLE) : null;
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
        $nationality = $clean($data['nacionalidad'] ?? null);

        return [
            'ok' => true,
            'rut' => $this->formatRut($rut),
            'first_name' => $clean($data['nombres'] ?? null),
            'last_name' => $clean($data['apellidos'] ?? null),
            'nationality' => $nationality,
            'birth_date' => $date($data['fecha_nacimiento'] ?? null),
            'expires_on' => $date($data['fecha_vencimiento'] ?? null),
            'foreigner' => ($data['tipo'] ?? '') === 'carnet_extranjero' || ($nationality && ! preg_match('/^chile/i', $nationality)),
            'error' => null,
        ];
    }

    private function formatRut(string $normalized): string
    {
        $clean = preg_replace('/[^0-9kK]/', '', $normalized);

        return substr($clean, 0, -1).'-'.strtoupper(substr($clean, -1));
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
Eres un lector de carnets de identidad para la recepción de un motel en Chile. Recibes la foto de un documento
(cédula de identidad chilena, carnet de extranjero o pasaporte) y extraes SOLO lo que se ve impreso.

Responde únicamente con un objeto JSON, sin texto extra ni comillas de código, con estas claves:
{"legible": true|false, "tipo": "cedula_chilena"|"carnet_extranjero"|"pasaporte"|"otro",
 "rut": "12345678-5" o null, "nombres": "...", "apellidos": "...", "nacionalidad": "..." o null,
 "fecha_nacimiento": "AAAA-MM-DD" o null, "fecha_vencimiento": "AAAA-MM-DD" o null}

Reglas:
- "rut" es el RUN impreso en el frente, con guion y dígito verificador. Si no se ve completo, null. Nunca lo inventes.
- "nombres" son los nombres de pila; "apellidos" son ambos apellidos (paterno y materno), tal como están impresos.
- Si la foto no es un documento, está borrosa o cortada, responde {"legible": false}.
- No agregues datos que no estén en la imagen y no adivines ninguno.
TXT;
    }
}
