<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de la API v2 de GoHighLevel (services.leadconnectorhq.com),
 * autenticado con el Private Integration Token de la subcuenta -- no es
 * OAuth, no expira ni hay refresh token que manejar.
 *
 * No hay endpoint de upsert que uses acá a ciegas: busca por teléfono y
 * decide explícitamente crear o actualizar, para saber siempre qué pasó.
 */
class GhlClient
{
    private const BASE_URL = 'https://services.leadconnectorhq.com';

    private const API_VERSION = '2021-07-28';

    private readonly string $token;

    private readonly string $locationId;

    public function __construct(?string $token = null, ?string $locationId = null)
    {
        $this->token = $token ?? (string) config('services.ghl.private_token');
        $this->locationId = $locationId ?? (string) config('services.ghl.location_id');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->locationId !== '';
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)
            ->withHeaders(['Version' => self::API_VERSION, 'Accept' => 'application/json'])
            ->baseUrl(self::BASE_URL)
            // Corto a propósito -- esto se llama en el mismo request que crea
            // la reserva (no hay worker de cola corriendo todavía), así que
            // si GHL está lento no puede demorar la confirmación del cliente
            // más de unos segundos.
            ->timeout(6);
    }

    /**
     * Busca un contacto por teléfono (E.164) dentro de esta subcuenta.
     * Devuelve el ID de GHL, o null si no existe todavía.
     */
    public function findContactIdByPhone(string $phoneE164): ?string
    {
        $response = $this->http()->get('/contacts/', [
            'locationId' => $this->locationId,
            'query' => $phoneE164,
            'limit' => 5,
        ])->throw();

        foreach ($response->json('contacts', []) as $contact) {
            if (($contact['phone'] ?? null) === $phoneE164) {
                return $contact['id'];
            }
        }

        return null;
    }

    /**
     * Crea o actualiza el contacto. Devuelve su ID de GHL.
     */
    public function upsertContact(string $phoneE164, string $fullName, ?string $email = null): string
    {
        [$firstName, $lastName] = $this->splitName($fullName);

        $payload = array_filter([
            'phone' => $phoneE164,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
        ], fn ($v) => $v !== null && $v !== '');

        $existingId = $this->findContactIdByPhone($phoneE164);

        if ($existingId) {
            // A diferencia de la creación, PUT /contacts/{id} rechaza
            // locationId con 422 ("property locationId should not exist") --
            // el contacto ya pertenece a esa location, no hace falta repetirlo.
            $this->http()->put("/contacts/{$existingId}", $payload)->throw();

            return $existingId;
        }

        $response = $this->http()->post('/contacts/', ['locationId' => $this->locationId, ...$payload])->throw();

        return (string) $response->json('contact.id');
    }

    /**
     * Agrega una nota al contacto -- a diferencia de actualizar campos o
     * tags, esto sí cuenta como "actividad" en la vista de contactos de
     * GHL (esa columna no se mueve solo con PUT /contacts o tags).
     */
    public function addNote(string $contactId, string $body): void
    {
        $this->http()->post("/contacts/{$contactId}/notes", ['body' => $body])->throw();
    }

    public function getTags(string $contactId): array
    {
        $response = $this->http()->get("/contacts/{$contactId}")->throw();

        return $response->json('contact.tags', []);
    }

    public function addTags(string $contactId, array $tags): void
    {
        if (empty($tags)) {
            return;
        }
        $this->http()->post("/contacts/{$contactId}/tags", ['tags' => $tags])->throw();
    }

    public function removeTags(string $contactId, array $tags): void
    {
        if (empty($tags)) {
            return;
        }
        $this->http()->delete("/contacts/{$contactId}/tags", ['tags' => $tags])->throw();
    }

    /**
     * Reemplaza cualquier tag anterior con ese mismo prefijo por uno nuevo --
     * así el tag de "última reserva" queda siempre único y al día, en vez de
     * acumular un tag distinto por cada reserva histórica del cliente.
     */
    public function replaceTagWithPrefix(string $contactId, string $prefix, string $newTag): void
    {
        $stale = array_values(array_filter(
            $this->getTags($contactId),
            fn ($tag) => str_starts_with($tag, $prefix) && $tag !== $newTag
        ));

        $this->removeTags($contactId, $stale);
        $this->addTags($contactId, [$newTag]);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?? $fullName, $parts[1] ?? null];
    }
}
