<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\GuestReview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Opiniones con premio: 15 minutos después del check-out se le pide la
 * opinión al cliente (una vez por estadía, y nunca a quien ya opinó), y al
 * dejarla recibe un cupón personal del 10% para su próxima visita. Los
 * WhatsApp los manda GHL por webhook; sin la URL configurada no se envía
 * nada, pero el cupón igual se crea y se muestra en la página de gracias.
 *
 * El premio es por opinar en nuestra página, cualquiera sea la nota: Google
 * prohíbe dar descuentos a cambio de reseñas en Google.
 */
class GhlReviewRequests
{
    public const MINUTES_AFTER_CHECKOUT = 15;

    public const DISCOUNT_PERCENT = 10;

    public const VALID_DAYS = 60;

    public const ORIGIN = 'opinion';

    public function __construct(private readonly GhlReservationNotifier $notifier) {}

    /** Corre con app:reservas-expirar. Nunca lanza. */
    public function sendDue(): void
    {
        $url = config('services.ghl.webhook_pedir_opinion');
        if (! filled($url)) {
            return;
        }

        Booking::with(['customer', 'room.category'])
            ->where('booking_status', 'FINALIZADA')
            ->whereNull('review_requested_at')
            ->whereNotNull('checked_out_at')
            ->where('checked_out_at', '<=', now()->subMinutes(self::MINUTES_AFTER_CHECKOUT))
            // Solo las recién terminadas: al configurar el webhook no se le escribe a estadías viejas.
            ->where('checked_out_at', '>=', now()->subHours(6))
            ->get()
            ->each(function (Booking $booking) use ($url) {
                $booking->update(['review_requested_at' => now()]);
                if (filled($booking->customer?->phone_e164) && ! $this->alreadyReviewed($booking)) {
                    $this->post('pedir_opinion', $url, $booking, ['link_opinion' => route('reviews.create', $booking->code)]);
                }
            });
    }

    /** ¿Este cliente ya dejó alguna opinión ligada a una de sus reservas? */
    public function alreadyReviewed(Booking $booking): bool
    {
        return GuestReview::whereHas('booking', fn ($q) => $q->where('customer_id', $booking->customer_id))->exists();
    }

    /**
     * Cupón del 10% por la opinión: uno solo por cliente, para siempre. Si ya
     * lo tenía, devuelve el mismo (no se acumulan cupones opinando de nuevo).
     */
    public function rewardFor(GuestReview $review): ?Coupon
    {
        $booking = $review->booking;
        if (! $booking?->customer_id) {
            return null;
        }

        $existing = Coupon::where('customer_id', $booking->customer_id)->where('origin', self::ORIGIN)->first();
        if ($existing) {
            $review->update(['coupon_id' => $existing->id]);

            return $existing;
        }

        $coupon = Coupon::create([
            'code' => $this->uniqueCode(),
            'customer_id' => $booking->customer_id,
            'origin' => self::ORIGIN,
            'auto_apply' => false,
            'internal_name' => self::DISCOUNT_PERCENT.'% por opinión — '.$booking->customer->name,
            'discount_type' => 'percentage',
            'discount_value' => self::DISCOUNT_PERCENT,
            'starts_at' => now('America/Santiago')->toDateString(),
            'ends_at' => now('America/Santiago')->addDays(self::VALID_DAYS)->toDateString(),
            'max_uses_total' => 1,
            'max_uses_per_customer' => 1,
            'is_stackable' => false,
            'requires_verification' => false,
            'is_active' => true,
        ]);
        $review->update(['coupon_id' => $coupon->id]);

        $url = config('services.ghl.webhook_cupon_opinion');
        if (filled($url) && filled($booking->customer?->phone_e164)) {
            $this->post('cupon_opinion', $url, $booking, [
                'cupon_codigo' => $coupon->code,
                'cupon_descuento' => self::DISCOUNT_PERCENT.'%',
                'cupon_vence' => $coupon->ends_at->locale('es')->isoFormat('D [de] MMMM'),
                'link_reservar' => route('catalog.reserve', ['cupon' => $coupon->code]),
            ]);
        }

        return $coupon;
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'GRACIAS-'.Str::upper(Str::random(5));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

    private function post(string $kind, string $url, Booking $booking, array $extra): void
    {
        try {
            $response = Http::timeout(6)->acceptJson()->post($url, $this->notifier->payload($booking) + $extra);
            if (! $response->successful()) {
                Log::warning('GHL: el webhook "'.$kind.'" respondió '.$response->status().' para la reserva '.$booking->code);
            }
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo mandar "'.$kind.'" de la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }
}
