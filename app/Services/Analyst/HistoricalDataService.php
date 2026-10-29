<?php

namespace App\Services\Analyst;

use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
use App\Services\Historical\HistoricalCustomerAnalysis;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ventas anteriores al sistema (planillas de recepción), solo como cifras
 * agregadas para el analista: nunca nombres, RUT ni edad de una persona.
 * Lo que sí sale de las personas es un conteo: cuántas distintas y cuántas
 * volvieron.
 */
class HistoricalDataService
{
    private const WEEKDAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    public function available(): bool
    {
        return HistoricalSale::query()->exists();
    }

    /** Qué rango de fechas cubre lo cargado. */
    public function coverage(): ?array
    {
        $min = HistoricalSale::min('sold_on');

        return $min ? ['desde' => Carbon::parse($min)->toDateString(), 'hasta' => Carbon::parse(HistoricalSale::max('sold_on'))->toDateString()] : null;
    }

    /**
     * Días récord de venta del período: los mejores días en total y, por cada día de la
     * semana, cuál fue su récord (y los siguientes), con el promedio para comparar.
     * La venta del día es la de todas las líneas de la planilla con esa fecha.
     * Solo cifras, sin personas. Devuelve null si no hay ventas en el período.
     */
    public function records(Carbon $from, Carbon $to): ?array
    {
        $days = HistoricalSale::query()
            ->whereBetween('sold_on', [$from->toDateString(), $to->toDateString()])
            ->get(['sold_on', 'sale_type', 'amount_main', 'amount_extras'])
            ->groupBy(fn (HistoricalSale $s) => $s->sold_on->toDateString())
            ->map(fn (Collection $g, string $date) => [
                'date' => Carbon::parse($date),
                'total' => (int) $g->sum(fn (HistoricalSale $s) => $s->total()),
                'stays' => $g->where('sale_type', 'ALOJAMIENTO')->count(),
            ])
            ->filter(fn (array $d) => $d['total'] > 0)
            ->values();

        if ($days->isEmpty()) {
            return null;
        }

        $byWeekday = collect(self::WEEKDAYS)->map(function (string $name, int $i) use ($days) {
            $mine = $days->filter(fn (array $d) => $d['date']->dayOfWeekIso === $i + 1)->sortByDesc('total')->values();

            return [
                'weekday' => $name,
                'days' => $mine->count(),
                'average' => $mine->isEmpty() ? 0 : (int) round($mine->avg('total')),
                'record' => $mine->first(),
                'next' => $mine->slice(1, 2)->values(),
            ];
        });

        return [
            'days_with_sales' => $days->count(),
            'total' => (int) $days->sum('total'),
            'top' => $days->sortByDesc('total')->take(10)->values(),
            'lowest' => $days->sortBy('total')->first(),
            'by_weekday' => $byWeekday,
        ];
    }

    /**
     * Un mes de la planilla, día por día, para la pantalla de Ventas. Solo
     * cifras (sin personas). Devuelve null si ese mes no tiene planilla.
     */
    public function month(Carbon $first): ?array
    {
        $first = $first->copy()->startOfMonth();
        $last = $first->copy()->endOfMonth();
        $lines = HistoricalSale::query()->whereBetween('sold_on', [$first->toDateString(), $last->toDateString()])->get();
        if ($lines->isEmpty()) {
            return null;
        }

        $money = fn (Collection $c) => (int) $c->sum(fn (HistoricalSale $s) => $s->total());
        $of = fn (Collection $c, string $concept) => $money($c->filter(fn (HistoricalSale $s) => $s->concept() === $concept));
        $byDay = $lines->groupBy(fn (HistoricalSale $s) => $s->sold_on->toDateString());
        $days = collect();
        for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
            $g = $byDay->get($d->toDateString(), collect());
            $days->push([
                'date' => $d->copy(),
                'stays' => $g->where('sale_type', 'ALOJAMIENTO')->count(),
                'lodging' => $of($g, 'alojamiento'),
                'extra_hour' => $of($g, 'hora_extra'),
                'extra_person' => $of($g, 'persona_adicional'),
                'services' => $of($g, 'servicios'),
                'extras' => $of($g, 'extras'),
                'split' => $of($g, 'fraccionado'),
                'total' => $money($g),
                'cash' => $money($g->where('payment_method', 'efectivo')),
                'card' => $money($g->where('payment_method', 'tarjeta')),
                'transfer' => $money($g->where('payment_method', 'transferencia')),
            ]);
        }

        return [
            'sources' => $lines->pluck('source')->unique()->values()->all(),
            'days' => $days,
            'totals' => collect(['stays', 'lodging', 'extra_hour', 'extra_person', 'services', 'extras', 'split', 'total', 'cash', 'card', 'transfer'])->mapWithKeys(fn ($k) => [$k => $days->sum($k)])->all(),
        ];
    }

    /** Todos los meses de la planilla, uno por fila, con la misma forma del historial de Ventas. */
    public function monthsForHistory(): Collection
    {
        return HistoricalSale::query()->get(['sold_on', 'sale_type', 'amount_main', 'amount_extras'])
            ->groupBy(fn (HistoricalSale $s) => $s->sold_on->format('Y-m'))
            ->map(function (Collection $g, string $ym) {
                $lodging = (int) $g->where('sale_type', 'ALOJAMIENTO')->sum(fn (HistoricalSale $s) => $s->total());
                $others = (int) $g->where('sale_type', '!=', 'ALOJAMIENTO')->sum(fn (HistoricalSale $s) => $s->total());

                return [
                    'month' => Carbon::parse($ym.'-01', 'America/Santiago'),
                    'rooms' => $g->where('sale_type', 'ALOJAMIENTO')->count(),
                    'lodging' => $lodging, 'extras' => $others, 'total' => $lodging + $others, 'cash' => $lodging + $others,
                    'source' => 'planilla',
                ];
            })->values();
    }

    /**
     * Venta de extras mes a mes, para ver cómo van creciendo: los extras (consumos) por un
     * lado y los extras core (hora extra, persona adicional y otros servicios) por otro,
     * junto a las estadías, para compararlos por estadía y no solo en pesos. Solo cifras.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function extrasByMonth(): Collection
    {
        return HistoricalSale::query()->get(['sold_on', 'sale_type', 'detail', 'amount_main', 'amount_extras'])
            ->groupBy(fn (HistoricalSale $s) => $s->sold_on->format('Y-m'))
            ->sortKeys()
            ->map(function (Collection $g, string $ym) {
                $of = fn (array $concepts) => (int) $g->filter(fn (HistoricalSale $s) => in_array($s->concept(), $concepts, true))->sum(fn (HistoricalSale $s) => $s->total());
                $first = Carbon::parse($ym.'-01');
                $lastDay = Carbon::parse($g->max('sold_on'));

                return [
                    'month' => $first,
                    'stays' => $g->where('sale_type', 'ALOJAMIENTO')->count(),
                    'days' => $g->map(fn (HistoricalSale $s) => $s->sold_on->toDateString())->unique()->count(),
                    'lodging' => $of(['alojamiento']),
                    'extras' => $of(['extras']),
                    'core' => $of(['hora_extra', 'persona_adicional', 'servicios']),
                    'total' => (int) $g->sum(fn (HistoricalSale $s) => $s->total()),
                    'partial' => $lastDay->lt($first->copy()->endOfMonth()->startOfDay()),
                    'last_day' => $lastDay,
                ];
            })->values();
    }

    public function summary(Carbon $from, Carbon $to): array
    {
        $lines = HistoricalSale::query()
            ->whereBetween('sold_on', [$from->toDateString(), $to->toDateString()])
            ->get();
        $stays = $lines->where('sale_type', 'ALOJAMIENTO');
        $total = fn (Collection $c) => (int) $c->sum(fn (HistoricalSale $s) => $s->total());

        return [
            'nota' => 'Ventas de planillas de recepción anteriores al sistema. No incluye reservas del sistema ni estados de pago; "estadías" son las líneas de alojamiento.',
            'cobertura_cargada' => $this->coverage(),
            'periodo' => $from->toDateString().' a '.$to->toDateString(),
            'totales' => [
                'estadias' => $stays->count(),
                'venta_alojamiento' => $total($stays),
                'venta_hora_extra' => $total($lines->filter(fn (HistoricalSale $s) => $s->concept() === 'hora_extra')),
                'venta_persona_adicional' => $total($lines->filter(fn (HistoricalSale $s) => $s->concept() === 'persona_adicional')),
                'venta_otros_extra_core' => $total($lines->filter(fn (HistoricalSale $s) => $s->concept() === 'servicios')),
                'venta_pago_fraccionado' => $total($lines->filter(fn (HistoricalSale $s) => $s->concept() === 'fraccionado')),
                'venta_extras_consumos' => $total($lines->filter(fn (HistoricalSale $s) => $s->concept() === 'extras')),
                'venta_total' => $total($lines),
                'ticket_promedio_estadia' => $stays->count() ? (int) round($total($stays) / $stays->count()) : 0,
                'duracion_promedio_min' => $stays->whereNotNull('duration_minutes')->count() ? (int) round($stays->whereNotNull('duration_minutes')->avg('duration_minutes')) : 0,
            ],
            'por_mes' => $this->group($stays, fn (HistoricalSale $s) => $s->sold_on->format('Y-m')),
            'por_categoria' => $this->group($stays, fn (HistoricalSale $s) => $s->categoryName() ?: 'Sin dato'),
            'por_horas_pagadas' => $this->group($stays, fn (HistoricalSale $s) => $s->hours_paid ? $s->hours_paid.' h' : 'Sin dato'),
            'por_dia_semana' => $this->ordered($this->group($stays, fn (HistoricalSale $s) => self::WEEKDAYS[$s->sold_on->dayOfWeekIso - 1]), self::WEEKDAYS),
            'por_hora_ingreso' => $this->group($stays->whereNotNull('starts_time'), fn (HistoricalSale $s) => substr($s->starts_time, 0, 2).':00'),
            'por_turno' => $this->group($stays, fn (HistoricalSale $s) => $s->shift ?: 'Sin dato'),
            'por_medio_pago' => $this->group($lines, fn (HistoricalSale $s) => $s->payment_method ?: 'Sin dato'),
            'descuentos' => [
                'estadias_con_descuento' => $stays->filter(fn (HistoricalSale $s) => filled($s->discount_note))->count(),
                'por_motivo' => $this->group($stays->filter(fn (HistoricalSale $s) => filled($s->discount_note)), fn (HistoricalSale $s) => mb_strtolower($s->discount_note)),
            ],
            'con_reserva_previa' => $stays->filter(fn (HistoricalSale $s) => filled($s->reservation_ref))->count(),
            'personas' => $this->guestCounts($from, $to),
        ];
    }

    /**
     * Cuántas personas distintas (por RUT válido) y cuántas volvieron, en todo
     * lo cargado hasta el final del período. Solo números.
     */
    public function guestCounts(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = HistoricalGuest::query()
            ->where('document_kind', 'rut')
            ->join('historical_sales', 'historical_sales.id', '=', 'historical_guests.historical_sale_id')
            ->where('historical_sales.sale_type', 'ALOJAMIENTO')
            ->when($to, fn ($q) => $q->where('historical_sales.sold_on', '<=', $to->toDateString()))
            ->get(['historical_guests.document', 'historical_sales.id as sale_id', 'historical_sales.sold_on']);

        $byPerson = $rows->groupBy('document')->map(fn (Collection $g) => $g->pluck('sale_id')->unique()->count());
        $inPeriod = $from
            ? $rows->filter(fn ($r) => Carbon::parse($r->sold_on)->gte($from->copy()->startOfDay()))->pluck('document')->unique()
            : $byPerson->keys();
        $returned = $inPeriod->filter(fn ($doc) => ($byPerson[$doc] ?? 0) >= 2);

        return [
            'nota' => 'Personas contadas por RUT válido (titulares y acompañantes). "volvieron" = con 2 o más estadías en todo lo cargado hasta el fin del período.',
            'personas_distintas_en_el_periodo' => $inPeriod->count(),
            'que_volvieron' => $returned->count(),
            'porcentaje_que_volvio' => $inPeriod->count() ? round($returned->count() * 100 / $inPeriod->count(), 1) : 0.0,
            'estadias_por_persona_promedio' => $inPeriod->count() ? round($inPeriod->avg(fn ($doc) => $byPerson[$doc] ?? 1), 2) : 0.0,
            'distribucion_estadias_por_persona' => $inPeriod->groupBy(fn ($doc) => min(5, $byPerson[$doc] ?? 1))->map->count()->sortKeys()->mapWithKeys(fn ($n, $k) => [($k >= 5 ? '5 o más' : $k.($k === 1 ? ' vez' : ' veces')) => $n])->all(),
        ];
    }

    /**
     * Cuánto vuelve la gente en un período de las planillas, solo como cifras (sin nombres ni RUT).
     * «Volvieron» = 2 o más estadías dentro del período; «ya habían venido» = con estadías antes del período.
     */
    public function loyalty(Carbon $from, Carbon $to): array
    {
        $people = app(HistoricalCustomerAnalysis::class)->people($from, $to);
        $months = max(1, (int) $from->copy()->startOfMonth()->diffInMonths($to->copy()->startOfMonth()) + 1);
        $count = $people->count();
        $returned = $people->where('visits', '>=', 2);

        return [
            'nota' => 'Personas por RUT válido (titulares y acompañantes), solo estadías de alojamiento de las planillas. Con pocos meses cargados, "primera vez" está sobreestimado porque no se conoce lo anterior.',
            'periodo' => $from->toDateString().' a '.$to->toDateString(),
            'meses_del_periodo' => $months,
            'personas_distintas' => $count,
            'volvieron_en_el_periodo' => $returned->count(),
            'porcentaje_que_volvio' => $count ? round($returned->count() * 100 / $count, 1) : 0.0,
            'ya_habian_venido_antes_del_periodo' => $people->where('before', '>', 0)->count(),
            'primera_vez_en_lo_cargado' => $people->where('before', 0)->count(),
            'visitas_por_persona_por_mes' => $count ? round($people->avg('visits') / $months, 2) : 0.0,
            'visitas_por_persona_por_mes_entre_los_que_volvieron' => $returned->isEmpty() ? 0.0 : round($returned->avg('visits') / $months, 2),
            'distribucion_visitas_por_persona' => $people->groupBy(fn (array $p) => min(5, $p['visits']))->map->count()->sortKeys()
                ->mapWithKeys(fn ($n, $k) => [($k >= 5 ? '5 o más' : $k.($k === 1 ? ' vez' : ' veces')) => $n])->all(),
        ];
    }

    /**
     * Comportamiento de los clientes según todo lo cargado de las planillas, para
     * la pantalla «Comportamiento de clientes». Solo cifras, sin nombres ni RUT.
     * Las personas se cuentan por RUT válido; «nueva» significa que su primera
     * estadía de lo cargado cae en ese mes (con pocos meses cargados hay más
     * «nuevas» de las reales). Devuelve null si no hay planillas.
     */
    public function behavior(): ?array
    {
        $coverage = $this->coverage();
        if ($coverage === null) {
            return null;
        }

        $from = Carbon::parse($coverage['desde'])->startOfDay();
        $to = Carbon::parse($coverage['hasta'])->endOfDay();
        $summary = $this->summary($from, $to);
        $stayCount = $summary['totales']['estadias'];

        // Una fila por persona y estadía (titular o acompañante con RUT válido).
        $visits = HistoricalGuest::query()
            ->where('document_kind', 'rut')
            ->join('historical_sales', 'historical_sales.id', '=', 'historical_guests.historical_sale_id')
            ->where('historical_sales.sale_type', 'ALOJAMIENTO')
            ->get(['historical_guests.document', 'historical_sales.id as sale_id', 'historical_sales.sold_on'])
            ->unique(fn ($r) => $r->document.'|'.$r->sale_id)
            ->map(fn ($r) => ['doc' => $r->document, 'sale' => $r->sale_id, 'day' => Carbon::parse($r->sold_on)->startOfDay()]);

        $byPerson = $visits->groupBy('doc');
        $people = $byPerson->count();
        $returned = $byPerson->filter(fn (Collection $g) => $g->pluck('sale')->unique()->count() >= 2)->count();

        // Días entre una estadía y la siguiente de la misma persona (mismo día no cuenta).
        $gaps = collect();
        foreach ($byPerson as $g) {
            $days = $g->pluck('day')->sort()->values()->unique(fn (Carbon $d) => $d->toDateString())->values();
            for ($i = 1; $i < $days->count(); $i++) {
                $gaps->push((int) $days[$i - 1]->diffInDays($days[$i]));
            }
        }
        $median = $gaps->isEmpty() ? null : (int) $gaps->sort()->values()->get(intdiv($gaps->count(), 2));
        $buckets = [['Hasta 7 días', 0, 7], ['8 a 14 días', 8, 14], ['15 a 30 días', 15, 30], ['31 a 60 días', 31, 60], ['61 a 90 días', 61, 90], ['Más de 90 días', 91, PHP_INT_MAX]];

        // Por mes: personas distintas, cuántas son su primera vez en lo cargado y cuántas ya habían venido antes.
        $firstDay = $byPerson->map(fn (Collection $g) => $g->min('day'));
        $months = $visits->groupBy(fn ($v) => $v['day']->format('Y-m'))->sortKeys()->map(function (Collection $g, string $ym) use ($firstDay) {
            $docs = $g->pluck('doc')->unique();
            $new = $docs->filter(fn ($d) => $firstDay[$d]->format('Y-m') === $ym)->count();

            return ['label' => Carbon::parse($ym.'-01')->locale('es')->isoFormat('MMM YY'), 'people' => $docs->count(), 'new' => $new, 'returning' => $docs->count() - $new];
        })->values();

        $rows = fn (array $groups) => collect($groups)->map(fn ($g) => ['label' => $g['grupo'], 'count' => $g['estadias']])->values();

        return [
            'coverage' => $coverage,
            'stays' => $stayCount,
            'stays_with_rut' => $visits->pluck('sale')->unique()->count(),
            'people' => $people,
            'returned' => $returned,
            'returned_pct' => $people ? (int) round($returned * 100 / $people) : 0,
            'stays_per_person' => $people ? round($visits->count() / $people, 1) : 0,
            'median_gap' => $median,
            'frequency' => collect($summary['personas']['distribucion_estadias_por_persona'])->map(fn ($n, $label) => ['label' => $label, 'count' => $n])->values(),
            'gap_buckets' => collect($buckets)->map(fn ($b) => ['label' => $b[0], 'count' => $gaps->filter(fn ($d) => $d >= $b[1] && $d <= $b[2])->count()])->values(),
            'months' => $months,
            'categories' => $rows($summary['por_categoria'])->sortByDesc('count')->values(),
            'hours' => $rows($summary['por_horas_pagadas'])->sortBy(fn ($r) => (int) $r['label'] ?: 999)->values(),
            'weekdays' => $rows($summary['por_dia_semana']),
            'shifts' => $rows($summary['por_turno'])->sortByDesc('count')->values(),
        ];
    }

    private function group(Collection $rows, callable $key): array
    {
        return $rows->groupBy($key)->map(fn (Collection $g, $k) => [
            'grupo' => (string) $k,
            'estadias' => $g->count(),
            'venta' => (int) $g->sum(fn (HistoricalSale $s) => $s->total()),
        ])->sortBy('grupo')->values()->all();
    }

    private function ordered(array $groups, array $order): array
    {
        $byKey = collect($groups)->keyBy('grupo');

        return collect($order)->map(fn ($k) => $byKey[$k] ?? ['grupo' => $k, 'estadias' => 0, 'venta' => 0])->all();
    }
}
