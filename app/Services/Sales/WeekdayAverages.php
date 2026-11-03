<?php

namespace App\Services\Sales;

use App\Models\Booking;
use App\Models\HistoricalSale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Promedio de venta por día de la semana: la venta de todos los lunes del mes
 * dividida por la cantidad de lunes que tuvo (aunque alguno no haya vendido),
 * y lo mismo para cada día, comparado con los meses anteriores.
 *
 * Cada mes sale de una sola fuente para no contar dos veces: el sistema o la
 * planilla de recepción (antes del sistema), la que tenga más venta ese mes.
 * El primer y el último mes de cada fuente cuentan solo los días que cubre.
 */
class WeekdayAverages
{
    private const TZ = 'America/Santiago';

    public const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * @return array{rows: Collection, months: Collection, source: ?string}
     */
    public function forMonth(Carbon $first, Carbon $lastCounted, int $monthsBack = 12): array
    {
        $first = $first->copy()->timezone(self::TZ)->startOfMonth();
        $months = $this->monthlyAverages();

        $current = $months->get($first->format('Y-m'));
        // El mes en curso solo cuenta hasta hoy; un mes sin ventas igual cuenta sus días.
        $current = $current
            ? $this->averagesFor($current['days'], $current['start'], $lastCounted, $current['source'])
            : $this->averagesFor(collect(), $first, $lastCounted, 'sistema');

        $previous = $months->filter(fn ($m, $ym) => $ym < $first->format('Y-m'));
        $lastMonth = $previous->get($first->copy()->subMonth()->format('Y-m'));

        $rows = collect(self::WEEKDAYS)->map(function (string $label, int $iso) use ($current, $previous, $lastMonth) {
            $days = $previous->sum(fn ($m) => $m['weekdays'][$iso]['days']);
            $sales = $previous->sum(fn ($m) => $m['weekdays'][$iso]['sales']);
            $historical = $days ? (int) round($sales / $days) : null;
            $now = $current['weekdays'][$iso];

            return [
                'label' => $label,
                'days' => $now['days'],
                'sales' => $now['sales'],
                'average' => $now['average'],
                'last_month' => $lastMonth['weekdays'][$iso]['average'] ?? null,
                'historical' => $historical,
                'historical_days' => $days,
                'vs_historical' => $historical && $now['average'] !== null ? ($now['average'] - $historical) / $historical : null,
            ];
        });

        return [
            'rows' => $rows,
            'source' => $current['source'],
            // Los meses anteriores, del más reciente al más antiguo, para la tabla mes a mes.
            'months' => $previous->sortKeysDesc()->take($monthsBack)->map(fn ($m, $ym) => [
                'month' => Carbon::parse($ym.'-01', self::TZ),
                'source' => $m['source'],
                'averages' => collect($m['weekdays'])->map(fn ($w) => $w['average']),
            ])->values(),
        ];
    }

    /** Todos los meses con venta, cada uno con el promedio por día de la semana. */
    private function monthlyAverages(): Collection
    {
        $system = $this->systemDays();
        $planilla = $this->planillaDays();

        $range = fn (Collection $days) => [$days->keys()->min(), $days->keys()->max()];
        $ranges = ['sistema' => $range($system), 'planilla' => $range($planilla)];

        $byMonth = fn (Collection $days) => $days->groupBy(fn ($total, $date) => substr($date, 0, 7), true);
        $systemMonths = $byMonth($system);
        $planillaMonths = $byMonth($planilla);

        return $systemMonths->keys()->merge($planillaMonths->keys())->unique()->sort()
            ->mapWithKeys(function (string $ym) use ($systemMonths, $planillaMonths, $ranges) {
                $sys = $systemMonths->get($ym, collect());
                $pla = $planillaMonths->get($ym, collect());
                [$days, $source] = $pla->sum() > $sys->sum() ? [$pla, 'planilla'] : [$sys, 'sistema'];
                [$firstData, $lastData] = $ranges[$source];

                // Solo los días que la fuente cubre: un mes a medias no baja el promedio.
                $monthStart = Carbon::parse($ym.'-01', self::TZ);
                $start = $monthStart->copy()->max(Carbon::parse($firstData, self::TZ));
                $end = $monthStart->copy()->endOfMonth()->startOfDay()->min(Carbon::parse($lastData, self::TZ));
                $month = $this->averagesFor($days, $start, $end, $source);

                return [$ym => $month + ['days' => $days, 'start' => $start]];
            });
    }

    /** Venta y cantidad de días de cada día de la semana entre $start y $end. */
    private function averagesFor(Collection $days, Carbon $start, Carbon $end, string $source): array
    {
        $weekdays = collect(self::WEEKDAYS)->map(fn () => ['days' => 0, 'sales' => 0, 'average' => null])->all();
        for ($d = $start->copy()->startOfDay(); $d->lte($end); $d->addDay()) {
            $iso = $d->dayOfWeekIso;
            $weekdays[$iso]['days']++;
            $weekdays[$iso]['sales'] += (int) ($days[$d->toDateString()] ?? 0);
        }
        foreach ($weekdays as $iso => $w) {
            $weekdays[$iso]['average'] = $w['days'] ? (int) round($w['sales'] / $w['days']) : null;
        }

        return ['weekdays' => $weekdays, 'source' => $source];
    }

    /** Venta del sistema por fecha de estadía (mismo criterio que el resumen mensual). */
    private function systemDays(): Collection
    {
        return Booking::with(['addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale())
            ->groupBy(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->toDateString())
            ->map(fn (Collection $rows) => (int) $rows->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount()))
            ->sortKeys();
    }

    /** Venta de la planilla por fecha (todas las líneas del día). */
    private function planillaDays(): Collection
    {
        return HistoricalSale::query()->get(['sold_on', 'sale_type', 'amount_main', 'amount_extras'])
            ->groupBy(fn (HistoricalSale $s) => $s->sold_on->toDateString())
            ->map(fn (Collection $rows) => (int) $rows->sum(fn (HistoricalSale $s) => $s->total()))
            ->sortKeys();
    }
}
