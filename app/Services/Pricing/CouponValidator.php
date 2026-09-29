<?php

namespace App\Services\Pricing;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Room;
use Carbon\Carbon;

/**
 * Valida la elegibilidad de un cupón en el servidor — nunca se confía en lo
 * que haya validado el frontend. Las promociones que exigen ver un documento
 * (cédula, credencial) no las verifica el sistema: solo deja constancia de
 * quién lo hizo, vía el flag verified_by en coupon_redemptions.
 */
class CouponValidator
{
    public function validate(Coupon $coupon, Room $room, Carbon $startsAt, int $durationMinutes, ?Customer $customer, int $priceOriginal, ?int $excludeBookingId = null): int
    {
        if (! $coupon->is_active) {
            throw new InvalidCouponException('Este cupón no está activo.');
        }

        $today = $startsAt->toDateString();
        if ($coupon->starts_at && $today < $coupon->starts_at->toDateString()) {
            throw new InvalidCouponException('Este cupón todavía no comienza su vigencia.');
        }
        if ($coupon->ends_at && $today > $coupon->ends_at->toDateString()) {
            throw new InvalidCouponException('Este cupón ya venció.');
        }

        if ($coupon->allowed_weekdays && ! in_array($startsAt->dayOfWeek, $coupon->allowed_weekdays, true)) {
            throw new InvalidCouponException('Este cupón no aplica ese día de la semana.');
        }

        if ($coupon->allowed_time_start && $coupon->allowed_time_end) {
            $time = $startsAt->format('H:i:s');
            if ($time < $coupon->allowed_time_start || $time >= $coupon->allowed_time_end) {
                throw new InvalidCouponException('Este cupón no aplica a ese horario.');
            }
        }

        if ($coupon->allowed_durations && ! in_array($durationMinutes, $coupon->allowed_durations, true)) {
            throw new InvalidCouponException('Este cupón no aplica a esa duración.');
        }

        if (! $coupon->appliesToRoom($room)) {
            throw new InvalidCouponException('Este cupón no aplica a esta habitación.');
        }

        if ($coupon->min_amount && $priceOriginal < $coupon->min_amount) {
            throw new InvalidCouponException('El monto de la reserva no alcanza el mínimo requerido por el cupón.');
        }

        if ($coupon->max_uses_total !== null && $coupon->redemptions()->when($excludeBookingId, fn ($q) => $q->where('booking_id', '!=', $excludeBookingId))->count() >= $coupon->max_uses_total) {
            throw new InvalidCouponException('Este cupón alcanzó su número máximo de usos.');
        }

        if ($customer && $coupon->max_uses_per_customer !== null) {
            $usedByCustomer = $coupon->redemptions()->where('customer_id', $customer->id)->when($excludeBookingId, fn ($q) => $q->where('booking_id', '!=', $excludeBookingId))->count();
            if ($usedByCustomer >= $coupon->max_uses_per_customer) {
                throw new InvalidCouponException('Este cliente ya usó este cupón el máximo de veces permitido.');
            }
        }

        // Cupones con edad mínima (ej. Expertos en Vida, 65+): antes solo se
        // avisaba a recepción para que mirara la cédula ("pide
        // verificación"), sin control real. Ahora hace falta la fecha de
        // nacimiento del cliente y que la edad calculada cumpla el mínimo —
        // si no, el cupón no queda activo.
        if ($coupon->min_age) {
            $age = $customer?->age();
            if ($age === null) {
                throw new InvalidCouponException('Este cupón exige verificar la edad — cargá la fecha de nacimiento del cliente.');
            }
            if ($age < $coupon->min_age) {
                throw new InvalidCouponException('Este cupón requiere '.$coupon->min_age.' años o más — el cliente tiene '.$age.'.');
            }
        }

        return $this->computeDiscount($coupon, $priceOriginal);
    }

    private function computeDiscount(Coupon $coupon, int $priceOriginal, ?int $excludeBookingId = null): int
    {
        $discount = match ($coupon->discount_type) {
            'percentage' => (int) round($priceOriginal * $coupon->discount_value / 100),
            // discount_value = precio final ofertado; el descuento es la diferencia.
            'precio_fijo' => max(0, $priceOriginal - $coupon->discount_value),
            default => $coupon->discount_value,
        };

        if ($coupon->max_discount_amount !== null && $coupon->discount_type !== 'precio_fijo') {
            $discount = min($discount, $coupon->max_discount_amount);
        }

        return min($discount, $priceOriginal);
    }
}
