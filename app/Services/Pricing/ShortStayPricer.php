<?php

namespace App\Services\Pricing;

use App\Models\RateRule;
use App\Models\Room;
use Carbon\Carbon;

/**
 * Estadía corta por cierre: cuando queda menos tiempo para cerrar que la
 * duración mínima que se vende en la categoría (ej. 2 h de un mínimo de 3 h),
 * recepción puede venderla igual con salida a la hora de cierre.
 *
 * Precio: proporcional al tiempo que queda sobre el precio de la duración
 * mínima, con un piso del 50% para no regalar la habitación. Redondeado
 * hacia arriba a la centena.
 */
class ShortStayPricer
{
    /** Con menos de esto para el cierre ya no se ofrece. */
    public const MIN_MINUTES = 30;

    /** Piso del precio, como fracción del precio de la duración mínima. */
    public const FLOOR_RATIO = 0.5;

    public const LABEL = 'Estadía corta por cierre';

    public function __construct(private RateRuleResolver $resolver) {}

    /**
     * Null si no corresponde: el horario completo ya cabe en una tarifa, el
     * inicio cae fuera de horario, queda muy poco tiempo, o la duración pedida
     * no es la mínima de la categoría (si cabe una más corta, se vende esa).
     *
     * @return array{rate_rule: RateRule, closes_at: Carbon, minutes: int, duration_minutes: int, full_price: int, price: int}|null
     */
    public function quote(Room $room, Carbon $startsAt, int $durationMinutes): ?array
    {
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

        $shortest = $status['rate_rule']->prices()
            ->where('room_category_id', $room->room_category_id)
            ->orderBy('duration_minutes')
            ->first();

        if (! $shortest
            || (int) $shortest->duration_minutes !== $durationMinutes
            || $minutes < self::MIN_MINUTES
            || $minutes >= $durationMinutes) {
            return null;
        }

        $full = (int) $shortest->price;
        $proportional = (int) (ceil($full * $minutes / $durationMinutes / 100) * 100);
        $floor = (int) (ceil($full * self::FLOOR_RATIO / 100) * 100);

        return [
            'rate_rule' => $status['rate_rule'],
            'closes_at' => $closesAt,
            'minutes' => $minutes,
            'duration_minutes' => $durationMinutes,
            'full_price' => $full,
            'price' => min($full, max($proportional, $floor)),
        ];
    }
}
