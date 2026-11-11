<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\BookingAdOrigin;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Averigua de qué anuncio llegó el cliente de cada reserva. GHL guarda, en el contacto
 * (que el sistema busca por teléfono), el anuncio de Meta desde el que escribió por
 * WhatsApp; aquí se lee y se guarda junto a la reserva para que Analytics pueda
 * mostrar las ventas por anuncio sin cruzarlas a mano.
 *
 * Solo lee de GHL: no crea ni cambia contactos.
 */
class GhlAdOrigin
{
    private const BASE_URL = 'https://services.leadconnectorhq.com';

    /** Cuántas veces se intenta si el contacto aún no existe en GHL. */
    public const MAX_ATTEMPTS = 3;

    /** Minutos de espera entre intentos y antes del primero (el contacto se crea al reservar). */
    public const RETRY_MINUTES = 10;

    public function __construct(private ?string $token = null, private ?string $locationId = null)
    {
        $this->token ??= (string) config('services.ghl.private_token');
        $this->locationId ??= (string) config('services.ghl.location_id');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->locationId !== '';
    }

    /**
     * Anuncio de origen según GHL.
     *
     * @return array{found: bool, ad_id: ?string, ad_name: ?string, source: ?string}
     */
    public function lookup(string $phoneE164): array
    {
        $response = Http::withToken($this->token)
            ->withHeaders(['Version' => '2021-07-28', 'Accept' => 'application/json'])
            ->baseUrl(self::BASE_URL)
            ->timeout(8)
            ->get('/contacts/', ['locationId' => $this->locationId, 'query' => $phoneE164, 'limit' => 5])
            ->throw();

        foreach ($response->json('contacts', []) as $contact) {
            if (($contact['phone'] ?? null) !== $phoneE164) {
                continue;
            }

            return ['found' => true] + self::pickAttribution((array) ($contact['attributions'] ?? []));
        }

        return ['found' => false, 'ad_id' => null, 'ad_name' => null, 'source' => null];
    }

    /**
     * Entre las atribuciones del contacto, la de un anuncio si la hay (la primera);
     * si no, la primera que traiga fuente.
     *
     * @return array{ad_id: ?string, ad_name: ?string, source: ?string}
     */
    public static function pickAttribution(array $attributions): array
    {
        foreach ($attributions as $a) {
            if (! empty($a['utmAdId'])) {
                return [
                    'ad_id' => mb_substr((string) $a['utmAdId'], 0, 40),
                    'ad_name' => isset($a['adName']) ? mb_substr((string) $a['adName'], 0, 160) : null,
                    'source' => isset($a['utmSessionSource']) ? mb_substr((string) $a['utmSessionSource'], 0, 40) : 'Paid Social',
                ];
            }
        }

        $first = collect($attributions)->first(fn ($a) => ! empty($a['utmSessionSource']));

        return ['ad_id' => null, 'ad_name' => null, 'source' => $first ? mb_substr((string) $first['utmSessionSource'], 0, 40) : null];
    }

    /** Reservas con teléfono que aún no tienen su anuncio averiguado (o que hay que reintentar). */
    public static function pendingQuery()
    {
        $since = config('services.ghl.opportunities_since');

        return Booking::query()
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', now()->subMinutes(self::RETRY_MINUTES))
            ->whereHas('customer', fn ($q) => $q->whereNotNull('phone_e164')->where('phone_e164', '!=', ''))
            ->where(function ($q) {
                $q->whereDoesntHave('adOrigin')
                    ->orWhereHas('adOrigin', fn ($o) => $o->where('contact_found', false)
                        ->where('attempts', '<', self::MAX_ATTEMPTS)
                        ->where('checked_at', '<=', now()->subMinutes(self::RETRY_MINUTES)));
            });
    }

    /** Averigua hasta $limit reservas. Devuelve cuántas quedaron resueltas. Un fallo de GHL corta la pasada. */
    public function resolvePending(int $limit = 8): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $done = 0;
        foreach (self::pendingQuery()->with('customer', 'adOrigin')->orderBy('id')->limit($limit)->get() as $booking) {
            try {
                $result = $this->lookup((string) $booking->customer->phone_e164);
            } catch (Throwable $e) {
                // GHL lento o caído: se reintenta en la próxima pasada, sin gastar intentos.
                report($e);
                break;
            }

            $origin = $booking->adOrigin ?? new BookingAdOrigin(['booking_id' => $booking->id, 'attempts' => 0]);
            $origin->fill([
                'attempts' => ((int) $origin->attempts) + 1,
                'checked_at' => now(),
                'contact_found' => $result['found'],
                'meta_ad_id' => $result['ad_id'],
                'meta_ad_name' => $result['ad_name'],
                'wa_source' => $result['source'],
            ])->save();

            if ($result['found']) {
                $done++;
            }
        }

        return $done;
    }
}
