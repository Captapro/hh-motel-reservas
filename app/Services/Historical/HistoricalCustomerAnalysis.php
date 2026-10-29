<?php

namespace App\Services\Historical;

use App\Models\HistoricalGuest;
use App\Support\Rut;
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
                'categories' => $this->categoryMix($inPeriod),
                'before' => $before->count(),
                'total' => $g->count(),
            ];
        })->filter()->values();
    }

    /**
     * Lo que dicen las planillas de cada RUT dado (por ejemplo el de un cliente del sistema).
     * Solo trae a quienes figuran; la llave es el RUT normalizado («12345678-9»).
     *
     * @param  iterable<string|null>  $ruts
     * @return Collection<string, array<string, mixed>>
     */
    public function forDocuments(iterable $ruts): Collection
    {
        $wanted = collect($ruts)->map(fn ($r) => Rut::normalize((string) $r))->filter()->unique()->values();
        if ($wanted->isEmpty()) {
            return collect();
        }

        return $this->visits($wanted->all())->groupBy('doc')->map(function (Collection $g) {
            $holder = $g->where('holder', true);

            return [
                'visits' => $g->count(),
                'as_holder' => $holder->count(),
                'as_companion' => $g->count() - $holder->count(),
                'spent' => (int) $holder->sum('amount'),
                'first' => $g->min('day'),
                'last' => $g->max('day'),
                'months' => $g->map(fn (array $v) => $v['day']->format('Y-m'))->unique()->sort()->values()->all(),
                'name' => $g->sortBy('day')->last()['name'],
            ];
        });
    }

    /**
     * Cuántas estadías hizo en cada tipo de playroom, de más a menos («PLUS» => 3).
     * Lo que la planilla no trae como tipo va aparte como «Sin tipo».
     *
     * @param  Collection<int, array<string, mixed>>  $visits
     * @return array<string, int>
     */
    public static function categoryMix(Collection $visits): array
    {
        return $visits->countBy(fn (array $v) => $v['category'] ?: 'Sin tipo')->sortDesc()->all();
    }

    /** Estadías de alojamiento con RUT válido: una por persona y estadía (si figura dos veces, vale como titular). */
    private function visits(?array $documents = null): Collection
    {
        return HistoricalGuest::query()
            ->where('document_kind', 'rut')
            ->when($documents !== null, fn ($q) => $q->whereIn('historical_guests.document', $documents))
            ->join('historical_sales', 'historical_sales.id', '=', 'historical_guests.historical_sale_id')
            ->where('historical_sales.sale_type', 'ALOJAMIENTO')
            ->orderBy('historical_sales.sold_on')
            ->get(['historical_guests.document', 'historical_guests.name', 'historical_guests.is_holder', 'historical_sales.id as sale_id', 'historical_sales.sold_on', 'historical_sales.category', 'historical_sales.amount_main', 'historical_sales.amount_extras'])
            ->groupBy(fn ($r) => $r->document.'|'.$r->sale_id)
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'doc' => $first->document,
                    'name' => $first->name,
                    'sale' => $first->sale_id,
                    'day' => Carbon::parse($first->sold_on)->startOfDay(),
                    'category' => $first->category,
                    'holder' => $rows->contains(fn ($r) => (bool) $r->is_holder),
                    'amount' => (int) ($first->amount_main + $first->amount_extras),
                ];
            })->values();
    }
}
