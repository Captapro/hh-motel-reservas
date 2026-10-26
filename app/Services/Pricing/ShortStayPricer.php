<?php

namespace App\Services\Pricing;

use App\Models\RateRule;
use App\Models\RateRulePrice;
use App\Models\Room;
use App\Models\ShortStaySetting;
use Carbon\Carbon;

/**
 * Estadía corta por cierre: cuando queda menos tiempo para cerrar que la
 * duración mínima que se vende en la categoría (ej. 1h30 de un mínimo de 3 h),
 * se vende igual con salida a la hora de cierre y un precio menor.
 *
 * Las reglas (desde/hasta cuántos minutos antes del cierre, cómo se calcula
 * el precio y en qué canales se ofrece) se editan en Administración →
 * Estadía corta (ShortStaySetting). El precio se redondea hacia arriba a la
 * centena y nunca supera el de la duración mínima.
 */
class ShortStayPricer
{
    public const LABEL = 'Estadía corta por cierre';

    public function __construct(private RateRuleResolver $resolver) {}

    /**
     * @return array{rate_rule: RateRule, closes_at: Carbon, minutes: int, duration_minutes: int, full_price: int, price: int}|null
     */
    public function quote(Room $room, Carbon $startsAt, int $durationMinutes, string $channel = 'recepcion'): ?array
    {
        return $this->quoteForCategory((int) $room->room_category_id, $startsAt, $durationMinutes, $channel);
    }

    /**
     * Null si no corresponde: está apagada para ese canal, el horario completo
     * ya cabe en una tarifa, el inicio cae fuera de horario, el tiempo que
     * queda está fuera del rango configurado, o la duración pedida no es la
     * mínima de la categoría (si cabe una más corta, se vende esa).
     *
     * @return array{rate_rule: RateRule, closes_at: Carbon, minutes: int, duration_minutes: int, full_price: int, price: int}|null
     */
    public function quoteForCategory(int $categoryId, Carbon $startsAt, int $durationMinutes, string $channel = 'recepcion'): ?array
    {
        $settings = ShortStaySetting::current();
        if (! $settings->offeredOn($channel)) {
            return null;
        }

        if ($this->resolver->resolve($startsAt, $startsAt->copy()->addMinutes($durationMinutes))) {
            return null;
        }

        $status = $this->resolver->currentStatus($startsAt);
        if (! $status) {
            return null;
        }

        /** @var Carbon $closesAt */
        $closesAt = $status['closes_at'];
        $minutes = (int) $startsAt->diffInMinutes($closesAt, false);

        $shortest = $this->shortestPrice($status['rate_rule'], $categoryId);

        if (! $shortest
            || (int) $shortest->duration_minutes !== $durationMinutes
            || $minutes < $settings->min_minutes
            || $minutes > $settings->max_minutes
            || $minutes >= $durationMinutes) {
            return null;
        }

        $full = (int) $shortest->price;
        $byPercent = (int) (ceil($full * $settings->percent / 100 / 100) * 100);
        $price = $settings->pricing_mode === 'porcentaje'
            ? $byPercent
            : max((int) (ceil($full * $minutes / $durationMinutes / 100) * 100), $byPercent);

        return [
            'rate_rule' => $status['rate_rule'],
            'closes_at' => $closesAt,
            'minutes' => $minutes,
            'duration_minutes' => $durationMinutes,
            'full_price' => $full,
            'price' => min($full, $price),
        ];
    }

    /** Duración mínima que se vende en la categoría bajo esa tarifa. */
    public function shortestPrice(RateRule $rateRule, int $categoryId): ?RateRulePrice
    {
        return $rateRule->prices()
            ->where('room_category_id', $categoryId)
            ->orderBy('duration_minutes')
            ->first();
    }
}
