<?php

namespace App\Services\Marketing;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * API de conversiones de Meta: cuando una reserva queda pagada se le avisa la
 * venta ("Purchase") con su monto, para que Meta sepa qué anuncios terminan en
 * plata y optimice por eso. Las del catálogo van como venta web (con la cookie
 * del píxel y el clic del anuncio); las de recepción, como venta en el local.
 * Teléfono, correo y nombre van cifrados (SHA-256), como exige Meta.
 *
 * El mismo event_id lo usa el píxel en la página de la reserva: Meta junta los
 * dos avisos en una sola venta. Sin META_CAPI_TOKEN no hace nada.
 */
class MetaConversions
{
    private const GRAPH_URL = 'https://graph.facebook.com/v21.0';

    public const LAST_RESULT_KEY = 'meta:capi:last';

    public static function enabled(): bool
    {
        return filled(config('services.meta.pixel_id')) && filled(config('services.meta.capi_token'));
    }

    public static function purchaseEventId(Booking $booking): string
    {
        return 'purchase-'.$booking->code;
    }

    /** Agenda el aviso para después de responder: no demora el cobro en recepción. */
    public static function purchaseLater(Booking $booking): void
    {
        if (! self::enabled()) {
            return;
        }

        dispatch(fn () => app(self::class)->sendPurchase($booking->fresh()))->afterResponse();
    }

    /** Nunca lanza: una venta ya registrada no puede fallar porque Meta no respondió. */
    public function sendPurchase(?Booking $booking): bool
    {
        if (! $booking || $booking->meta_purchase_sent_at || ! self::enabled()) {
            return false;
        }

        try {
            $booking->loadMissing(['customer', 'room.category', 'trackingData']);
            $response = Http::timeout(8)->post(
                self::GRAPH_URL.'/'.config('services.meta.pixel_id').'/events',
                array_filter([
                    'data' => [$this->purchasePayload($booking)],
                    'test_event_code' => config('services.meta.test_event_code'),
                    'access_token' => config('services.meta.capi_token'),
                ])
            );

            Cache::put(self::LAST_RESULT_KEY, [
                'at' => now()->toIso8601String(),
                'booking' => $booking->code,
                'status' => $response->status(),
                'error' => $response->successful() ? null : mb_substr((string) $response->json('error.message', $response->body()), 0, 300),
            ], now()->addDays(30));

            if (! $response->successful()) {
                return false;
            }

            $booking->forceFill(['meta_purchase_sent_at' => now()])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Envío único de ventas pagadas que quedaron sin avisar (p. ej. las anteriores al token).
     * Cada una va con su hora real. Meta acepta ventas en el local de hasta 62 días y ventas
     * web de hasta 7: las del catálogo más antiguas van como venta en el local. Solo se mandan
     * las que tienen teléfono o correo (sin eso Meta no puede reconocer a nadie). En lotes de 200.
     *
     * @return array{sent: int, skipped: int, error: ?string}
     */
    public function sendPastPurchases(Carbon $since): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'error' => null];
        if (! self::enabled()) {
            return ['error' => 'Falta META_CAPI_TOKEN.'] + $result;
        }

        $bookings = self::pastPurchasesQuery($since)->with(['customer', 'room.category', 'trackingData', 'addons'])->get();
        $withContact = $bookings->filter(fn (Booking $b) => filled($b->customer?->phone_e164) || filled($b->customer?->email));
        $result['skipped'] = $bookings->count() - $withContact->count();

        foreach ($withContact->chunk(200) as $chunk) {
            $response = Http::timeout(30)->post(self::GRAPH_URL.'/'.config('services.meta.pixel_id').'/events', array_filter([
                'data' => $chunk->map(fn (Booking $b) => $this->purchasePayload($b, self::saleTime($b)))->values()->all(),
                'test_event_code' => config('services.meta.test_event_code'),
                'access_token' => config('services.meta.capi_token'),
            ]));
            if (! $response->successful()) {
                $result['error'] = 'Meta respondió '.$response->status().': '.mb_substr((string) $response->json('error.message', $response->body()), 0, 300);
                break;
            }
            Booking::whereKey($chunk->modelKeys())->update(['meta_purchase_sent_at' => now()]);
            $result['sent'] += $chunk->count();
        }

        return $result;
    }

    /** Ventas pagadas y cerradas o en curso, desde $since y dentro de lo que Meta acepta (60 días), aún sin avisar. */
    public static function pastPurchasesQuery(Carbon $since)
    {
        return Booking::query()
            ->where('payment_status', 'PAGADA')
            ->whereNull('meta_purchase_sent_at')
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->where('starts_at', '>=', $since->copy()->max(now()->subDays(60)))
            ->where('starts_at', '<=', now());
    }

    private static function saleTime(Booking $booking): Carbon
    {
        return ($booking->checked_in_at ?? $booking->starts_at)->copy()->min(now());
    }

    public function purchasePayload(Booking $booking, ?Carbon $at = null): array
    {
        $customer = $booking->customer;
        $tracking = $booking->origin === 'catalogo' ? $booking->trackingData : null;
        [$first, $last] = array_pad(explode(' ', trim((string) $customer?->name), 2), 2, '');

        $userData = array_filter([
            'ph' => $this->hashed(preg_replace('/\D/', '', (string) $customer?->phone_e164)),
            'em' => $this->hashed(strtolower(trim((string) $customer?->email))),
            'fn' => $this->hashed(mb_strtolower(trim($first))),
            'ln' => $this->hashed(mb_strtolower(trim($last))),
            'country' => $this->hashed('cl'),
            'external_id' => $customer ? $this->hashed('hh-customer-'.$customer->id) : null,
            'fbc' => $tracking?->fbc,
            'fbp' => $tracking?->fbp,
            'client_ip_address' => $tracking?->ip,
            'client_user_agent' => $tracking?->user_agent,
        ]);

        // Una venta del catálogo de hace más de 7 días ya no entra como web: va como venta en el local.
        $online = $booking->origin === 'catalogo' && (! $at || $at->gt(now()->subDays(7)));

        return array_filter([
            'event_name' => 'Purchase',
            'event_time' => ($at ?? now())->timestamp,
            'event_id' => self::purchaseEventId($booking),
            'action_source' => $online ? 'website' : 'physical_store',
            'event_source_url' => $online ? route('catalog.booked', $booking->pass_token) : null,
            'user_data' => $userData,
            'custom_data' => array_filter([
                'currency' => 'CLP',
                'value' => $booking->price_final + $booking->addonsTotal(),
                'order_id' => $booking->code,
                'content_name' => $booking->room?->category?->name,
                'content_type' => 'product',
                'content_ids' => $booking->room?->category ? ['categoria-'.$booking->room->room_category_id] : null,
            ], fn ($v) => $v !== null),
        ]);
    }

    private function hashed(string $value): ?array
    {
        return $value === '' ? null : [hash('sha256', $value)];
    }
}
