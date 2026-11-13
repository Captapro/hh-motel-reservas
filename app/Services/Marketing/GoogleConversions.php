<?php

namespace App\Services\Marketing;

use App\Models\Booking;
use App\Models\TrackingData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Aviso de ventas a Google Ads desde el servidor. Cada reserva del catálogo que llegó por un
 * anuncio (tiene gclid, gbraid o wbraid) y quedó pagada se manda una sola vez a una hoja de
 * Google (Apps Script, ver entregables/google-conversiones); Google Ads importa esa hoja solo.
 * Así la venta cuenta aunque el cliente pague y nunca vuelva a la página de confirmación.
 * Sin GOOGLE_CONVERSIONS_URL y GOOGLE_CONVERSIONS_TOKEN no hace nada.
 */
class GoogleConversions
{
    public const LAST_RESULT_KEY = 'google:conversions:last';

    /** Google Ads solo acepta ventas de clics de hasta 90 días. */
    private const CLICK_WINDOW_DAYS = 90;

    public static function enabled(): bool
    {
        return filled(config('services.google.upload_url')) && filled(config('services.google.upload_token'));
    }

    /** Reservas pagadas del catálogo con clic de Google que aún no se avisaron. */
    public static function pendingQuery()
    {
        return Booking::query()
            ->where('origin', 'catalogo')
            ->where('payment_status', 'PAGADA')
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->where('created_at', '>=', now()->subDays(self::CLICK_WINDOW_DAYS))
            ->whereHas('trackingData', fn ($q) => $q
                ->whereNull('google_conversion_sent_at')
                ->where(fn ($c) => $c->whereNotNull('gclid')->orWhereNotNull('gbraid')->orWhereNotNull('wbraid')));
    }

    /**
     * Manda hasta $limit ventas. Devuelve cuántas se avisaron; si Google (la hoja) falla, lanza
     * con el motivo para que el comando lo registre. Cada venta se marca solo si la hoja la recibió.
     */
    public function sendPending(int $limit = 40): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $bookings = self::pendingQuery()->with(['trackingData', 'payments', 'addons'])->orderBy('id')->limit($limit)->get();
        if ($bookings->isEmpty()) {
            return 0;
        }

        $rows = $bookings->map(fn (Booking $b) => $this->row($b))->values()->all();

        $response = Http::timeout(30)->asJson()->post((string) config('services.google.upload_url'), [
            'token' => (string) config('services.google.upload_token'),
            'rows' => $rows,
        ]);
        $body = $response->json();

        Cache::put(self::LAST_RESULT_KEY, [
            'at' => now()->toIso8601String(),
            'rows' => count($rows),
            'status' => $response->status(),
            'error' => ($response->successful() && is_array($body) && ($body['ok'] ?? false) === true) ? null : mb_substr((string) (is_array($body) ? ($body['error'] ?? '') : $response->body()), 0, 300),
        ], now()->addDays(30));

        if (! $response->successful() || ! is_array($body) || ($body['ok'] ?? false) !== true) {
            throw new \RuntimeException('La hoja de Google no aceptó las ventas: '.(is_array($body) ? ($body['error'] ?? 'sin detalle') : 'HTTP '.$response->status()));
        }

        // La hoja confirma cuáles quedaron guardadas (nuevas o ya estaban); solo esas se marcan.
        $accepted = collect((array) ($body['recibidos'] ?? []))->map(fn ($c) => (string) $c)->all();
        $done = 0;
        foreach ($bookings as $booking) {
            if (in_array($booking->code, $accepted, true)) {
                TrackingData::whereKey($booking->trackingData->id)->update(['google_conversion_sent_at' => now()]);
                $done++;
            }
        }

        return $done;
    }

    /** La fila de una venta con las columnas que pide la importación de Google Ads. */
    public function row(Booking $booking): array
    {
        $tracking = $booking->trackingData;
        $paidAt = $booking->payments->where('status', 'aprobado')->min('created_at') ?? $booking->created_at;

        // Google acepta UN solo identificador por venta: con dos (gclid + gbraid, que en
        // celulares llegan juntos) rechaza la fila y la venta no cuenta (12-nov).
        $gclid = (string) $tracking->gclid;
        $gbraid = $gclid === '' ? (string) $tracking->gbraid : '';
        $wbraid = $gclid === '' && $gbraid === '' ? (string) $tracking->wbraid : '';

        return [
            'gclid' => $gclid,
            'gbraid' => $gbraid,
            'wbraid' => $wbraid,
            'conversion_name' => (string) config('services.google.upload_action'),
            // Hora de Chile con su desfase, como la pide Google: 2026-11-11 15:45:00-03:00
            'conversion_time' => Carbon::parse($paidAt)->min(now())->setTimezone('America/Santiago')->format('Y-m-d H:i:sP'),
            'value' => (int) ($booking->price_final + $booking->addonsTotal()),
            'currency' => 'CLP',
            'order_id' => $booking->code,
        ];
    }
}
