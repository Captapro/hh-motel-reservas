<?php

namespace App\Services\Analyst;

use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
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
        $byDay = $lines->groupBy(fn (HistoricalSale $s) => $s->sold_on->toDateString());
        $days = collect();
        for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
            $g = $byDay->get($d->toDateString(), collect());
            $stays = $g->where('sale_type', 'ALOJAMIENTO');
            $days->push([
                'date' => $d->copy(),
                'stays' => $stays->count(),
                'lodging' => $money($stays),
                'others' => $money($g->where('sale_type', '!=', 'ALOJAMIENTO')),
                'total' => $money($g),
                'cash' => $money($g->where('payment_method', 'efectivo')),
                'card' => $money($g->where('payment_method', 'tarjeta')),
                'transfer' => $money($g->where('payment_method', 'transferencia')),
            ]);
        }

        return [
            'sources' => $lines->pluck('source')->unique()->values()->all(),
            'days' => $days,
            'totals' => [
                'stays' => $days->sum('stays'), 'lodging' => $days->sum('lodging'), 'others' => $days->sum('others'), 'total' => $days->sum('total'),
                'cash' => $days->sum('cash'), 'card' => $days->sum('card'), 'transfer' => $days->sum('transfer'),
            ],
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
                'venta_horas_extra_y_packs' => $total($lines->where('sale_type', 'EXTRA CORE')),
                'venta_pago_fraccionado' => $total($lines->where('sale_type', 'PAGO FRACCIONADO')),
                'venta_extras_productos' => $total($lines->where('sale_type', 'EXTRAS')),
                'venta_total' => $total($lines),
                'ticket_promedio_estadia' => $stays->count() ? (int) round($total($stays) / $stays->count()) : 0,
                'duracion_promedio_min' => $stays->whereNotNull('duration_minutes')->count() ? (int) round($stays->whereNotNull('duration_minutes')->avg('duration_minutes')) : 0,
            ],
            'por_mes' => $this->group($stays, fn (HistoricalSale $s) => $s->sold_on->format('Y-m')),
            'por_categoria' => $this->group($stays, fn (HistoricalSale $s) => $s->category ?: 'Sin dato'),
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
