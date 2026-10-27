<?php

namespace App\Services\Analyst;

use App\Models\Booking;
use App\Models\CalendarOverride;
use App\Models\Coupon;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\ShortStaySetting;
use App\Models\UpsellOffer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Los datos que ve el analista de ventas (IA). Todo sale agregado: cifras por
 * categoría, día, hora, tarifa, oferta… nunca nombres, teléfonos ni RUT de
 * clientes, y nada de Claves. Cada método devuelve un arreglo listo para
 * pasarlo como JSON al modelo.
 */
class SalesDataService
{
    private const TZ = 'America/Santiago';

    private const WEEKDAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    /** Venta del período: totales, cortes y comparación con el período anterior del mismo largo. */
    public function overview(Carbon $from, Carbon $to, ?int $categoryId = null): array
    {
        $span = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevFrom = $from->copy()->subDays($span);
        $prevTo = $from->copy()->subSecond();

        $current = $this->paidSales($from, $to, $categoryId);
        $previous = $this->paidSales($prevFrom, $prevTo, $categoryId);
        $upsellNames = UpsellOffer::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

        $lost = Booking::query()
            ->whereIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$from->copy(), $to->copy()])
            ->get(['booking_status', 'origin']);

        $withDelay = $current->filter(fn (Booking $b) => $b->checked_in_at !== null);
        $delays = $withDelay->map(fn (Booking $b) => $b->checkInDelayMinutes());

        return [
            'periodo' => $this->period($from, $to),
            'periodo_anterior' => $this->period($prevFrom, $prevTo),
            'totales' => $this->totals($current),
            'totales_periodo_anterior' => $this->totals($previous),
            'perdidas' => [
                'canceladas' => $lost->where('booking_status', 'CANCELADA')->count(),
                'vencidas_sin_pago' => $lost->where('booking_status', 'EXPIRADA')->count(),
                'vencidas_sin_pago_en_linea' => $lost->where('booking_status', 'EXPIRADA')->where('origin', 'catalogo')->count(),
            ],
            'por_categoria' => $this->group($current, fn (Booking $b) => $b->room->category->name),
            'por_duracion_minutos' => $this->group($current, fn (Booking $b) => (string) $b->duration_minutes),
            'por_dia_semana' => $this->ordered($this->group($current, fn (Booking $b) => self::WEEKDAYS[$this->local($b)->dayOfWeekIso - 1]), self::WEEKDAYS),
            'por_hora_llegada' => $this->ordered($this->group($current, fn (Booking $b) => sprintf('%02d:00', $this->local($b)->hour)), array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23))),
            'reservas_por_dia_y_hora' => $this->heatmap($current),
            'por_tarifa_aplicada' => $this->group($current, fn (Booking $b) => $b->rate_rule_name_snapshot ?: 'Sin tarifa'),
            'por_canal' => $this->group($current, fn (Booking $b) => match ((string) $b->origin) {
                'catalogo' => 'Reserva en línea',
                'recepcion' => 'Reserva asistida',
                'presencial' => 'Presencial',
                default => 'Sin dato',
            }),
            'anticipacion_reserva' => $this->group($current, fn (Booking $b) => $this->leadTimeBucket($b)),
            'estadia_corta' => [
                'reservas' => $current->where('short_stay', true)->count(),
                'venta' => (int) $current->where('short_stay', true)->sum(fn (Booking $b) => $this->revenue($b)),
            ],
            'ofertas_y_cupones' => $current->filter(fn (Booking $b) => $b->coupon_id || $b->discount_amount > 0)
                ->groupBy(fn (Booking $b) => $b->coupon_code_snapshot ?: 'Descuento sin cupón')
                ->map(fn (Collection $g, $k) => [
                    'nombre' => $k,
                    'usos' => $g->count(),
                    'descuento_total' => (int) $g->sum('discount_amount'),
                    'venta_con_oferta' => (int) $g->sum(fn (Booking $b) => $this->revenue($b)),
                ])->sortByDesc('usos')->values()->all(),
            'consumos' => [
                'reservas_con_consumo_pagado' => $current->filter(fn (Booking $b) => $b->paidAddonsAmount() > 0)->count(),
                'detalle' => $current->flatMap(fn (Booking $b) => $b->paidAddons())
                    ->groupBy('description')
                    ->map(fn (Collection $rows, $desc) => [
                        'producto' => $desc,
                        'es_upsell' => in_array(mb_strtolower($desc), $upsellNames, true),
                        'cantidad' => (int) $rows->sum('quantity'),
                        'venta' => (int) $rows->sum('amount'),
                    ])->sortByDesc('venta')->values()->take(30)->all(),
            ],
            'llegadas' => [
                'con_check_in' => $withDelay->count(),
                'atraso_promedio_min' => $delays->count() ? (int) round($delays->avg()) : null,
                'llegaron_mas_de_15_min_tarde' => $delays->filter(fn ($d) => $d > 15)->count(),
            ],
            'clientes' => $this->customerMix($current),
        ];
    }

    /**
     * Qué tan llenas estuvieron las habitaciones: horas ocupadas sobre horas
     * disponibles, por categoría y por día de semana × hora. Cuenta toda
     * reserva no cancelada ni vencida (pagada o por pagar), desde la hora
     * agendada hasta la salida.
     */
    public function occupancy(Carbon $from, Carbon $to): array
    {
        $rooms = Room::with('category')->whereHas('category', fn ($q) => $q->where('is_active', true))->get();
        $roomsByCategory = $rooms->groupBy(fn (Room $r) => $r->category->name)->map->count();

        // Cuántas veces aparece cada (día, hora) en el rango, para el denominador.
        $slotOccurrences = [];
        for ($t = $from->copy()->startOfHour(); $t->lte($to); $t->addHour()) {
            $key = ($t->dayOfWeekIso - 1).'-'.$t->hour;
            $slotOccurrences[$key] = ($slotOccurrences[$key] ?? 0) + 1;
        }
        $totalHours = array_sum($slotOccurrences);

        $bookings = Booking::with('room.category')
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->where('starts_at', '<', $to->copy())
            ->where('ends_at', '>', $from->copy())
            ->get();

        $occupiedByCategory = [];
        $occupiedBySlot = [];
        foreach ($bookings as $b) {
            $start = $b->starts_at->copy()->timezone(self::TZ)->max($from);
            $end = $b->ends_at->copy()->timezone(self::TZ)->min($to);
            $cat = $b->room->category->name;
            // Recorre la estadía en tramos de 15 minutos.
            for ($t = $start->copy(); $t->lt($end); $t->addMinutes(15)) {
                $occupiedByCategory[$cat] = ($occupiedByCategory[$cat] ?? 0) + 0.25;
                $key = ($t->dayOfWeekIso - 1).'-'.$t->hour;
                $occupiedBySlot[$key] = ($occupiedBySlot[$key] ?? 0) + 0.25;
            }
        }

        $totalRooms = max(1, $rooms->count());
        $grid = [];
        foreach (self::WEEKDAYS as $d => $dayName) {
            $row = [];
            for ($h = 0; $h < 24; $h++) {
                $key = $d.'-'.$h;
                $capacity = ($slotOccurrences[$key] ?? 0) * $totalRooms;
                $row[sprintf('%02d', $h)] = $capacity ? (int) round(100 * ($occupiedBySlot[$key] ?? 0) / $capacity) : null;
            }
            $grid[$dayName] = $row;
        }

        return [
            'periodo' => $this->period($from, $to),
            'habitaciones_activas' => $totalRooms,
            'ocupacion_promedio_pct' => $totalHours ? (int) round(100 * array_sum($occupiedByCategory) / ($totalHours * $totalRooms)) : null,
            'por_categoria' => $roomsByCategory->map(fn (int $count, string $cat) => [
                'categoria' => $cat,
                'habitaciones' => $count,
                'horas_ocupadas' => (int) round($occupiedByCategory[$cat] ?? 0),
                'ocupacion_pct' => $totalHours ? (int) round(100 * ($occupiedByCategory[$cat] ?? 0) / ($totalHours * $count)) : null,
            ])->values()->all(),
            'ocupacion_pct_por_dia_y_hora' => $grid,
        ];
    }

    /** Cómo está configurado hoy el precio: tarifas, ofertas, upsells y estadía corta. */
    public function pricingConfig(): array
    {
        $dayNames = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
        $short = ShortStaySetting::current();

        return [
            'categorias' => RoomCategory::withCount('rooms')->orderBy('display_order')->get()->map(fn (RoomCategory $c) => [
                'nombre' => $c->name,
                'activa' => (bool) $c->is_active,
                'habitaciones' => $c->rooms_count,
                'capacidad_base' => $c->base_capacity,
                'capacidad_max' => $c->max_capacity,
            ])->all(),
            'tarifas' => RateRule::with(['windows', 'prices.roomCategory'])->where('is_active', true)->orderByDesc('priority')->get()->map(fn (RateRule $r) => [
                'nombre' => $r->name,
                'prioridad' => $r->priority,
                'vigencia' => trim(($r->valid_from?->toDateString() ?? '').' a '.($r->valid_until?->toDateString() ?? ''), ' a'),
                'precio_hora_extra' => $r->extra_hour_price,
                'precio_persona_extra' => $r->extra_person_price,
                'horarios' => $r->windows->map(fn ($w) => $dayNames[$w->weekday].' '.substr($w->start_time, 0, 5).'-'.substr($w->end_time, 0, 5))->values()->all(),
                'precios' => $r->prices->map(fn ($p) => [
                    'categoria' => $p->roomCategory?->name,
                    'duracion_min' => $p->duration_minutes,
                    'precio' => $p->price,
                ])->values()->all(),
            ])->all(),
            'ofertas_y_cupones_activos' => Coupon::withCount('redemptions')->where('is_active', true)->get()->map(fn (Coupon $c) => [
                'nombre' => $c->label(),
                'se_aplica_sola' => (bool) $c->auto_apply,
                'tipo_descuento' => $c->discount_type,
                'valor' => $c->discount_value,
                'cuando' => $c->constraintsLabel() ?: 'Siempre',
                'duraciones' => $c->allowed_durations,
                'vigencia' => trim(($c->starts_at?->toDateString() ?? '').' a '.($c->ends_at?->toDateString() ?? ''), ' a'),
                'usos_totales' => $c->redemptions_count,
            ])->all(),
            'upsells_activos' => UpsellOffer::with(['fromCategory', 'toCategory', 'combo'])->active()->get()->map(fn (UpsellOffer $u) => [
                'nombre' => $u->name,
                'tipo' => $u->type,
                'precio' => $u->price,
                'de_categoria' => $u->fromCategory?->name,
                'a_categoria' => $u->toCategory?->name,
                'minutos_extra' => $u->extra_minutes,
                'pack' => $u->combo?->name,
            ])->all(),
            'estadia_corta' => [
                'activa' => (bool) $short->enabled,
                'en_recepcion' => (bool) $short->reception_enabled,
                'en_linea' => (bool) $short->catalog_enabled,
                'minimo_min' => $short->min_minutes,
                'maximo_min' => $short->max_minutes,
                'modo_precio' => ShortStaySetting::MODES[$short->pricing_mode] ?? $short->pricing_mode,
                'porcentaje' => $short->percent,
            ],
            'dias_con_tarifa_forzada_proximos' => CalendarOverride::with('forcedRateRule')
                ->where('date', '>=', now(self::TZ)->toDateString())->orderBy('date')->take(20)->get()
                ->map(fn ($o) => ['fecha' => $o->date->toDateString(), 'tarifa' => $o->forcedRateRule?->name, 'motivo' => $o->reason])->all(),
        ];
    }

    /** Últimos 12 meses calendario, mes a mes. */
    public function monthlyTrend(): array
    {
        $first = now(self::TZ)->startOfMonth()->subMonths(11);
        $sales = $this->paidSales($first, now(self::TZ)->endOfMonth(), null);

        return collect(range(11, 0))->map(function (int $i) use ($sales) {
            $month = now(self::TZ)->startOfMonth()->subMonths($i);
            $rows = $sales->filter(fn (Booking $b) => $this->local($b)->isSameMonth($month));

            return ['mes' => $month->format('Y-m')] + $this->totals($rows);
        })->all();
    }

    private function paidSales(Carbon $from, Carbon $to, ?int $categoryId): Collection
    {
        return Booking::with(['room.category', 'addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$from->copy(), $to->copy()])
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale())
            ->values();
    }

    private function totals(Collection $rows): array
    {
        $count = $rows->count();
        $revenue = (int) $rows->sum(fn (Booking $b) => $this->revenue($b));
        $rooms = (int) $rows->sum('price_final');

        return [
            'reservas' => $count,
            'venta_total' => $revenue,
            'venta_habitaciones' => $rooms,
            'venta_consumos' => $revenue - $rooms,
            'ticket_promedio' => $count ? (int) round($revenue / $count) : 0,
            'descuentos' => (int) $rows->sum('discount_amount'),
            'duracion_promedio_min' => $count ? (int) round($rows->avg('duration_minutes')) : 0,
        ];
    }

    private function group(Collection $rows, callable $key): array
    {
        return $rows->groupBy($key)->map(fn (Collection $g, $k) => [
            'grupo' => (string) $k,
            'reservas' => $g->count(),
            'venta' => (int) $g->sum(fn (Booking $b) => $this->revenue($b)),
            'precio_habitacion_promedio' => (int) round($g->avg('price_final')),
            'descuento_promedio' => (int) round($g->avg('discount_amount')),
        ])->sortByDesc('venta')->values()->all();
    }

    /** Reordena un corte según un orden fijo (días, horas), con ceros donde no hubo ventas. */
    private function ordered(array $groups, array $order): array
    {
        $byKey = collect($groups)->keyBy('grupo');

        return collect($order)->map(fn ($k) => $byKey[$k] ?? ['grupo' => $k, 'reservas' => 0, 'venta' => 0, 'precio_habitacion_promedio' => 0, 'descuento_promedio' => 0])->all();
    }

    private function heatmap(Collection $rows): array
    {
        $out = [];
        foreach (self::WEEKDAYS as $d) {
            $out[$d] = array_fill_keys(array_map(fn ($h) => sprintf('%02d', $h), range(0, 23)), 0);
        }
        foreach ($rows as $b) {
            $l = $this->local($b);
            $out[self::WEEKDAYS[$l->dayOfWeekIso - 1]][sprintf('%02d', $l->hour)]++;
        }

        return $out;
    }

    private function leadTimeBucket(Booking $b): string
    {
        $minutes = $b->created_at ? (int) $b->created_at->diffInMinutes($b->starts_at, false) : null;

        return match (true) {
            $minutes === null => 'Sin dato',
            $minutes < 30 => 'Al momento (menos de 30 min)',
            $minutes < 180 => 'Mismo día (30 min a 3 h)',
            $minutes < 1440 => 'Mismo día o noche anterior (3 a 24 h)',
            default => 'Con más de un día',
        };
    }

    /** Clientes nuevos vs. que vuelven, solo en cantidades. */
    private function customerMix(Collection $rows): array
    {
        $ids = $rows->pluck('customer_id')->filter()->unique();
        if ($ids->isEmpty()) {
            return ['clientes_distintos' => 0, 'nuevos' => 0, 'que_vuelven' => 0];
        }
        $firstStart = $rows->min('starts_at');
        $returning = Booking::whereIn('customer_id', $ids)
            ->where('starts_at', '<', $firstStart)
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->distinct()->pluck('customer_id');

        return [
            'clientes_distintos' => $ids->count(),
            'nuevos' => $ids->diff($returning)->count(),
            'que_vuelven' => $returning->count(),
            'reservas_por_cliente' => round($rows->count() / $ids->count(), 2),
        ];
    }

    private function revenue(Booking $b): int
    {
        return $b->price_final + $b->paidAddonsAmount();
    }

    private function local(Booking $b): Carbon
    {
        return $b->starts_at->copy()->timezone(self::TZ);
    }

    private function period(Carbon $from, Carbon $to): string
    {
        return $from->toDateString().' a '.$to->toDateString();
    }
}
