<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Proyección de venta a fin de mes, como la de la planilla ("venta ÷ días × días del mes")
 * pero con los días contados solos: solo días ya cerrados (hasta ayer), para que el día en
 * curso a medio vender no la tire hacia abajo. La segunda cifra estima cada día que falta con
 * el promedio de ese mismo día de la semana, porque viernes a domingo venden mucho más.
 * La usan el resumen mensual y Analytics, para que las dos pantallas den lo mismo.
 */
class SalesProjection
{
    /** @param  Collection<int, array{date: Carbon, total: int}>  $daily  un registro por día del mes */
    public static function forMonth(Collection $daily, Carbon $first): ?array
    {
        $today = now('America/Santiago')->startOfDay();
        if (! $first->isSameMonth($today)) {
            return null;
        }
        $closed = $daily->filter(fn (array $r) => $r['date']->lt($today));
        if ($closed->isEmpty()) {
            return ['closed_days' => 0, 'days' => $first->daysInMonth];
        }
        $closedSales = (int) $closed->sum('total');
        $perDay = $closedSales / $closed->count();
        $byWeekday = $closed->groupBy(fn (array $r) => $r['date']->dayOfWeekIso)->map(fn (Collection $g) => $g->avg('total'));
        $rest = $daily->filter(fn (array $r) => $r['date']->gte($today))->sum(fn (array $r) => $byWeekday[$r['date']->dayOfWeekIso] ?? $perDay);

        return [
            'closed_days' => $closed->count(),
            'days' => $first->daysInMonth,
            'closed_sales' => $closedSales,
            'per_day' => (int) round($perDay),
            'simple' => (int) round($perDay * $first->daysInMonth),
            'by_weekday' => (int) round($closedSales + $rest),
        ];
    }
}
