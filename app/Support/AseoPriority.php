<?php

namespace App\Support;

use Carbon\Carbon;

/** Prioridad de aseo según lo que falta para la reserva: 3, 6 y 12 horas. Igual en tablero y panel de mucamas. */
class AseoPriority
{
    /** @return array{class: string, text: string} */
    public static function for(Carbon $startsAt): array
    {
        $at = $startsAt->copy()->timezone('America/Santiago');
        $hours = now()->diffInMinutes($at, false) / 60;

        [$class, $label] = match (true) {
            $hours <= 3 => ['aseo-urgent', 'Urgente'],
            $hours <= 6 => ['aseo-high', 'Prioridad alta'],
            $hours <= 12 => ['aseo-mid', 'Prioridad'],
            default => ['aseo-info', 'Reserva'],
        };
        $day = $at->isToday() || $at->isPast() ? '' : ($at->isTomorrow() ? 'mañana ' : $at->format('d/m').' ');

        return ['class' => $class, 'text' => $label.' · '.$day.$at->format('H:i')];
    }
}
