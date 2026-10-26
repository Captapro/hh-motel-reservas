<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\LateArrivalSetting;
use App\Services\Pricing\RateRuleResolver;
use Carbon\Carbon;

/**
 * Cuánto tiempo se le respeta a quien llega tarde al check-in.
 *
 * Dentro de la tolerancia el contador parte de cero (la salida se corre todo
 * el atraso). Pasada la tolerancia, según la regla de Administración, se
 * descuenta solo el exceso o el atraso completo. La salida nunca se corre más
 * allá del cierre ni encima de la siguiente reserva: si no cabe, se queda la
 * hora de salida original.
 */
class LateArrivalPolicy
{
    public function __construct(private RateRuleResolver $rateRules, private AvailabilityChecker $availability) {}

    /**
     * blocked: correspondía correr la salida pero choca con el cierre o con la siguiente reserva.
     *
     * @return array{delay: int, credited: int, lost: int, within_tolerance: bool, blocked: bool, ends_at: Carbon, tolerance: int}
     */
    public function resolve(Booking $booking, Carbon $arrival): array
    {
        $settings = LateArrivalSetting::current();
        $tolerance = $settings->tolerance_minutes;
        $delay = max(0, (int) $booking->starts_at->diffInMinutes($arrival, false));
        $endsAt = $booking->ends_at->copy();

        $within = $delay <= $tolerance;
        $credit = $within ? $delay : ($settings->mode === 'todo' ? 0 : $tolerance);

        $blocked = false;
        if ($credit > 0) {
            $extendedEnd = $endsAt->copy()->addMinutes($credit);
            if ($this->rateRules->resolve($booking->starts_at, $extendedEnd)
                && $this->availability->isAvailable($booking->room, $booking->starts_at, $extendedEnd, $booking->id)) {
                $endsAt = $extendedEnd;
            } else {
                $credit = 0;
                $blocked = true;
            }
        }

        return [
            'delay' => $delay,
            'credited' => $credit,
            'lost' => $delay - $credit,
            'within_tolerance' => $within,
            'blocked' => $blocked,
            'ends_at' => $endsAt,
            'tolerance' => $tolerance,
        ];
    }
}
