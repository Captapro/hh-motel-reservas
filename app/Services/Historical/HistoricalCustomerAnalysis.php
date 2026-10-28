<?php

namespace App\Services\Historical;

use App\Models\HistoricalGuest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuánto vuelven las personas que aparecen en las planillas (por RUT válido), para
 * un período cualquiera de lo cargado. Tiene nombres y RUT: solo para la pantalla de
 * soporte, nunca para el Analista de IA (ese usa HistoricalDataService, solo cifras).
 */
class HistoricalCustomerAnalysis
{
    /** Primer y último mes cargados («YYYY-MM»), o null si no hay planillas. */
    public function loadedMonths(): ?array
    {
        $months = $this->visits()->map(fn (array $v) => $v['day']->format('Y-m'))->unique()->sort()->values();

        return $months->isEmpty() ? null : ['first' => $months->first(), 'last' => $months->last(), 'all' => $months->all()];
    }

    /**
     * Una fila por persona con lo que hizo en el período [$from, $to] y lo anterior.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function people(Carbon $from, Carbon $to): Collection
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return $this->visits()->groupBy('doc')->map(function (Collection $g, string $doc) use ($from, $to) {
            $inPeriod = $g->filter(fn (array $v) => $v['day']->betweenIncluded($from, $to));
            if ($inPeriod->isEmpty()) {
                return null;
            }
            $before = $g->filter(fn (array $v) => $v['day']->lt($from));
            $holder = $inPeriod->where('holder', true);

            return [
                'document' => $doc,
                'name' => $inPeriod->sortBy('day')->last()['name'],
                'visits' => $inPeriod->count(),
                'as_holder' => $holder->count(),
                'as_companion' => $inPeriod->count() - $holder->count(),
                'spent' => (int) $holder->sum('amount'),
                'first' => $inPeriod->min('day'),
                'last' => $inPeriod->max('day'),
                'months' => $inPeriod->map(fn (array $v) => $v['day']->format('Y-m'))->unique()->sort()->values()->all(),
                'before' => $before->count(),
                'total' => $g->count(),
            ];
        })->filter()->values();
    }

    /** Estadías de alojamiento con RUT válido: una por persona y estadía (si figura dos veces, vale como titular). */
    private function visits(): Collection
    {
        return HistoricalGuest::query()
            ->where('document_kind', 'rut')
            ->join('historical_sales', 'historical_sales.id', '=', 'historical_guests.historical_sale_id')
            ->where('historical_sales.sale_type', 'ALOJAMIENTO')
            ->orderBy('historical_sales.sold_on')
            ->get(['historical_guests.document', 'historical_guests.name', 'historical_guests.is_holder', 'historical_sales.id as sale_id', 'historical_sales.sold_on', 'historical_sales.amount_main', 'historical_sales.amount_extras'])
            ->groupBy(fn ($r) => $r->document.'|'.$r->sale_id)
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'doc' => $first->document,
                    'name' => $first->name,
                    'sale' => $first->sale_id,
                    'day' => Carbon::parse($first->sold_on)->startOfDay(),
                    'holder' => $rows->contains(fn ($r) => (bool) $r->is_holder),
                    'amount' => (int) ($first->amount_main + $first->amount_extras),
                ];
            })->values();
    }
}
