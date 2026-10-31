<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Última revisión antes de vencer una reserva pendiente de pago: se le
 * pregunta a Mercado Pago si esa reserva tiene un pago aprobado. Si lo tiene
 * se registra (y la reserva ya no vence); así un pago no se pierde aunque el
 * cliente no haya vuelto a la página ni haya llegado el aviso.
 */
class MercadoPagoReconciler
{
    /** Si Mercado Pago no responde, se espera hasta esto antes de vencerla igual. */
    private const GRACE_MINUTES = 5;

    public static function wasChecked(int $bookingId): bool
    {
        return Cache::has('mp-checked:'.$bookingId);
    }

    public function __construct(
        private readonly MercadoPagoClient $client,
        private readonly MercadoPagoPayments $payments,
    ) {}

    /**
     * Revisa las reservas del catálogo cuya retención ya venció y deja marcadas
     * las revisadas, para que Booking::releaseExpired() las pueda vencer. Si
     * Mercado Pago no responde, se marca igual pasados GRACE_MINUTES.
     */
    public function checkExpiring(): void
    {
        if (! $this->client->enabled()) {
            return;
        }

        $due = Booking::where('booking_status', 'PENDIENTE_PAGO')->where('origin', 'catalogo')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->get(['id', 'code', 'expires_at']);

        foreach ($due as $booking) {
            try {
                foreach ($this->client->paymentsFor($booking->code) as $mp) {
                    $result = $this->payments->record($mp);
                    if (str_starts_with($result, 'registrado')) {
                        Log::info('Mercado Pago: pago encontrado al revisar antes de vencer', ['reserva' => $booking->code, 'pago' => $mp['id'] ?? null]);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Mercado Pago: no se pudo revisar antes de vencer', ['reserva' => $booking->code, 'error' => $e->getMessage()]);
                if ($booking->expires_at->gt(now()->subMinutes(self::GRACE_MINUTES))) {
                    continue;
                }
            }
            Cache::put('mp-checked:'.$booking->id, true, now()->addDay());
        }
    }
}
