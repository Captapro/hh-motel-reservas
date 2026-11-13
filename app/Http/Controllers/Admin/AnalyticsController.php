<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\RoomCategory;
use App\Services\Analyst\HistoricalDataService;
use App\Services\Marketing\Attribution;
use App\Support\SalesProjection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    /**
     * Todo el dashboard responde a UNA barra de filtros: rango de fechas
     * (preset o desde/hasta), métrica (reservas o venta) y categoría. Cada
     * corte se compara además contra el período inmediatamente anterior del
     * mismo largo. Cálculo en PHP sobre starts_at en hora de Santiago.
     */
    public function index(Request $request): View
    {
        [$start, $end, $preset] = $this->resolveRange($request);
        // Siempre en venta ($): la cantidad de reservas ya se ve en las tarjetas de arriba.
        $metric = 'revenue';
        $categoryId = (int) $request->query('category', 0) ?: null;

        $spanDays = max(1, (int) ceil($start->diffInDays($end)) + 1);
        $prevStart = $start->copy()->subDays($spanDays);
        $prevEnd = $start->copy()->subSecond();

        $all = Booking::with(['room.category', 'addons.product', 'addons.combo', 'payments.paymentMethod', 'adOrigin', 'trackingData'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$prevStart->copy(), $end->copy()])
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale());

        $current = $all->filter(fn (Booking $b) => $this->inRange($b, $start, $end))->values();
        $previous = $all->filter(fn (Booking $b) => $this->inRange($b, $prevStart, $prevEnd))->values();

        $val = fn (Booking $b) => $metric === 'revenue' ? $b->price_final + $b->paidAddonsAmount() : 1;
        $sum = fn (Collection $c) => (int) $c->sum($val);

        $weekdayNames = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

        return view('admin.analytics.index', array_merge([
            'preset' => $preset,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'metric' => $metric,
            'metricLabel' => $metric === 'revenue' ? 'Venta' : 'Reservas',
            'categoryId' => $categoryId,
            'categories' => RoomCategory::orderBy('display_order')->get(),
            'rangeLabel' => $this->rangeLabel($start, $end),

            'kpis' => $this->kpis($current, $previous, $metric),

            'byCategory' => $this->byCategory($current, $previous),
            'byRoom' => $this->rank($current, fn (Booking $b) => $b->room->name, $val, fn (Booking $b) => $b->room->category->name),
            'byWeekday' => $this->overlay($current, $previous, fn (Booking $b) => $weekdayNames[$b->starts_at->timezone('America/Santiago')->dayOfWeekIso - 1], $val, $weekdayNames),
            'weekdayDates' => $this->weekdayDates($start, $end, $weekdayNames),
            'byHour' => $this->buckets($current, fn (Booking $b) => $b->starts_at->timezone('America/Santiago')->hour, $val, range(0, 23)),
            'topDays' => $current->groupBy(fn (Booking $b) => $b->starts_at->timezone('America/Santiago')->toDateString())
                ->map(fn ($g, $k) => ['date' => Carbon::parse($k), 'value' => $sum($g), 'count' => $g->count()])
                ->sortByDesc('value')->take(8)->values(),

            'byOrigin' => $this->byOrigin($current, $val),
            'byChannel' => $this->byChannel($current, $val),
            'monthly' => $this->monthlyComparison($metric, $categoryId),
            'projection' => $this->monthProjection($categoryId),
            'operations' => $this->operations($current) + $this->pending($start, $end, $categoryId),
            'refreshedAt' => now('America/Santiago'),
            'isLive' => $end->isFuture() || $end->isToday(),
            'paymentMethods' => $this->paymentMethods($current),
            'bookingStatuses' => $current->groupBy('booking_status')->map->count()->sortDesc(),
            'segments' => $this->segments(),
            // "2 x Agua sin gas" y "Agua sin gas" son el mismo extra: se agrupan sin el "N x".
            'extras' => $current->flatMap(fn (Booking $b) => $b->paidAddons())
                ->groupBy(fn ($a) => trim((string) preg_replace('/^\s*\d+\s*x\s+/iu', '', (string) $a->description)))
                ->map(fn ($rows) => ['quantity' => (int) $rows->sum('quantity'), 'value' => (int) $rows->sum('amount')])->sortByDesc('value'),
        ]));
    }

    /**
     * Una fila por reserva, con todas las dimensiones sueltas (Playroom,
     * categoría, ala, cliente, fecha, precios, pago) para que se pueda armar
     * tablas dinámicas y cruces en Excel/Sheets -- no intentamos adivinar
     * qué cruce quiere ver el usuario, le damos el detalle crudo.
     */
    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveRange($request);
        $categoryId = (int) $request->query('category', 0) ?: null;

        $bookings = Booking::with(['room.category', 'customer', 'addons', 'payments.paymentMethod'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$start->copy(), $end->copy()])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale());

        $filename = 'ventas_hh_motel_'.$start->format('Y-m-d').'_a_'.$end->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM -- sin esto Excel rompe las tildes/ñ
            fputcsv($out, [
                'Código', 'Fecha', 'Hora', 'Día semana', 'Playroom', 'Categoría', 'Ala',
                'Duración (min)', 'Cliente', 'Teléfono', 'Tarifa aplicada',
                'Precio original', 'Descuento', 'Precio final Playroom', 'Extras',
                'Total', 'Métodos de pago', 'Estado reserva', 'Estado pago',
            ]);
            foreach ($bookings as $b) {
                $startsAt = $b->starts_at->timezone('America/Santiago');
                $extrasTotal = (int) $b->paidAddonsAmount();
                fputcsv($out, [
                    $b->code,
                    $startsAt->format('d-m-Y'),
                    $startsAt->format('H:i'),
                    ucfirst($startsAt->locale('es')->isoFormat('dddd')),
                    $b->room->name,
                    $b->room->category->name,
                    $b->room->wing ? ucfirst($b->room->wing) : 'Sin ala',
                    $b->duration_minutes,
                    $b->customer->name,
                    $b->customer->phone_e164,
                    $b->rate_rule_name_snapshot,
                    $b->price_original,
                    $b->discount_amount,
                    $b->price_final,
                    $extrasTotal,
                    $b->price_final + $extrasTotal,
                    $b->payments->where('status', 'aprobado')->pluck('paymentMethod.name')->unique()->implode('; '),
                    $b->booking_status,
                    $b->payment_status,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function resolveRange(Request $request): array
    {
        $tz = 'America/Santiago';
        $preset = (string) $request->query('preset', 'this_month');

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->query('from'), $tz)->startOfDay();
            $to = Carbon::parse($request->query('to'), $tz)->endOfDay();

            return [$from, $to, 'custom'];
        }

        $now = now($tz);

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'],
            'week' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY), 'week'],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), '7d'],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), '30d'],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), '90d'],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'last_month'],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'this_year'],
            'all' => [Carbon::parse('2000-01-01', $tz), $now->copy()->endOfDay(), 'all'],
            // Por defecto, el mes en curso hasta hoy (si es día 8, los 8 primeros días del mes).
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'this_month'],
        };
    }

    private function inRange(Booking $b, Carbon $start, Carbon $end): bool
    {
        $local = $b->starts_at->timezone('America/Santiago');

        return $local->betweenIncluded($start, $end);
    }

    private function rangeLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('d/m/Y').' – '.$end->format('d/m/Y');
    }

    /**
     * @return array<string, array{value: int, prev: int, delta: ?int, is_money: bool}>
     */
    private function kpis(Collection $current, Collection $previous, string $metric): array
    {
        $rev = fn (Collection $c) => (int) $c->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount());
        $disc = fn (Collection $c) => (int) $c->sum('discount_amount');

        $mk = function (int $now, int $prev, bool $money) {
            $delta = $prev > 0 ? (int) round((($now - $prev) / $prev) * 100) : null;

            return ['value' => $now, 'prev' => $prev, 'delta' => $delta, 'is_money' => $money];
        };

        $cRev = $rev($current);
        $pRev = $rev($previous);
        $cCount = $current->count();
        $pCount = $previous->count();

        return [
            'reservas' => $mk($cCount, $pCount, false),
            'venta' => $mk($cRev, $pRev, true),
            'ticket' => $mk($cCount ? (int) round($cRev / $cCount) : 0, $pCount ? (int) round($pRev / $pCount) : 0, true),
            'descuentos' => $mk($disc($current), $disc($previous), true),
        ];
    }

    /**
     * Categorías preferidas: cuántas reservas tuvo cada una en el rango, su parte del total,
     * la venta y cuántas tuvo en el período anterior del mismo largo (para ver si sube o baja).
     */
    private function byCategory(Collection $current, Collection $previous): Collection
    {
        $total = max(1, $current->count());
        $prev = $previous->groupBy(fn (Booking $b) => $b->room->room_category_id)->map->count();

        return $current->groupBy(fn (Booking $b) => $b->room->room_category_id)
            ->map(fn (Collection $g, $id) => [
                'label' => $g->first()->room->category->name,
                'count' => $g->count(),
                'share' => $g->count() / $total,
                'value' => (int) $g->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount()),
                'prev' => (int) ($prev[$id] ?? 0),
            ])
            ->sortByDesc('count')->values();
    }

    private function rank(Collection $rows, callable $key, callable $val, ?callable $sub = null): Collection
    {
        return $rows->groupBy($key)
            ->map(fn ($g, $k) => [
                'label' => $k,
                'sub' => $sub ? $sub($g->first()) : null,
                'value' => (int) $g->sum($val),
                'count' => $g->count(),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Un valor por bucket (día de semana, hora…) para el período actual y el
     * anterior, en el orden fijo de $order.
     */
    private function overlay(Collection $current, Collection $previous, callable $key, callable $val, array $order): Collection
    {
        $agg = function (Collection $c) use ($key, $val) {
            $out = [];
            foreach ($c as $b) {
                $k = $key($b);
                $out[$k] = ($out[$k] ?? 0) + $val($b);
            }

            return $out;
        };
        $cur = $agg($current);
        $prev = $agg($previous);

        return collect($order)->map(fn ($label) => [
            'label' => $label,
            'value' => (int) ($cur[$label] ?? 0),
            'prev' => (int) ($prev[$label] ?? 0),
        ]);
    }

    /**
     * Qué fechas del rango (hasta hoy) caen en cada día de la semana: el gráfico suma todos
     * los lunes, martes… y sin decir cuáles no se entendía de qué días hablaba (12-nov).
     *
     * @return array<string, list<string>> ['Lunes' => ['2/11', '9/11'], ...]
     */
    private function weekdayDates(Carbon $start, Carbon $end, array $names): array
    {
        $out = array_fill_keys($names, []);
        $last = $end->copy()->min(now('America/Santiago')->endOfDay());
        if ($last->diffInDays($start, true) > 120) {
            return $out; // rangos largos: demasiadas fechas para mostrar
        }
        for ($d = $start->copy()->startOfDay(); $d->lte($last); $d->addDay()) {
            $out[$names[$d->dayOfWeekIso - 1]][] = $d->format('j/n');
        }

        return $out;
    }

    private function buckets(Collection $rows, callable $key, callable $val, array $keys): Collection
    {
        $out = array_fill_keys($keys, 0);
        foreach ($rows as $b) {
            $k = $key($b);
            if (array_key_exists($k, $out)) {
                $out[$k] += $val($b);
            }
        }

        return collect($keys)->map(fn ($k) => ['key' => $k, 'value' => (int) $out[$k]]);
    }

    /**
     * Comparativo de los últimos 12 meses con la métrica elegida.
     */
    private function monthlyComparison(string $metric, ?int $categoryId): array
    {
        $firstMonth = now('America/Santiago')->startOfMonth()->subMonths(11);

        $bookings = Booking::with(['addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->where('starts_at', '>=', $firstMonth->copy())
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale());

        $val = fn (Booking $b) => $metric === 'revenue' ? $b->price_final + $b->paidAddonsAmount() : 1;

        // Meses antes del sistema: se completan con la planilla histórica cargada (solo sin
        // filtro de categoría; la planilla no trae las categorías de hoy). Antes salían en $0.
        $planilla = $categoryId ? collect() : app(HistoricalDataService::class)->monthsForHistory()
            ->keyBy(fn (array $m) => $m['month']->format('Y-m'));

        $monthly = collect();
        $prev = null;

        for ($i = 11; $i >= 0; $i--) {
            $month = now('America/Santiago')->startOfMonth()->subMonths($i);
            $rows = $bookings->filter(fn (Booking $b) => $b->starts_at->timezone('America/Santiago')->isSameMonth($month));
            $value = (int) $rows->sum($val);
            $count = $rows->count();
            $source = 'sistema';
            $old = $planilla->get($month->format('Y-m'));
            if ($count === 0 && $old) {
                [$value, $count, $source] = [$metric === 'revenue' ? (int) $old['total'] : (int) $old['rooms'], (int) $old['rooms'], 'planilla'];
            }
            // Los meses vacíos del comienzo (antes de cualquier dato) no se muestran.
            if ($monthly->isEmpty() && $count === 0 && $i > 0) {
                continue;
            }

            $delta = ($prev !== null && $prev > 0) ? (int) round((($value - $prev) / $prev) * 100) : null;

            $monthly->push([
                'label' => ucfirst($month->locale('es')->isoFormat('MMM YYYY')),
                'count' => $count,
                'value' => $value,
                'delta_pct' => $delta,
                'source' => $source,
            ]);
            $prev = $value;
        }

        return [
            'rows' => $monthly,
            'max' => (int) $monthly->max('value'),
        ];
    }

    /**
     * Proyección de venta a fin del mes calendario en curso: ritmo diario
     * (venta hasta hoy / días transcurridos) × días del mes, con piso en lo
     * que ya está agendado para el resto del mes.
     */
    private function monthProjection(?int $categoryId): array
    {
        $tz = 'America/Santiago';
        $now = now($tz);
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $bookings = Booking::with(['addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$monthStart->copy(), $monthEnd->copy()])
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale());

        $rev = fn (Booking $b) => $b->price_final + $b->paidAddonsAmount();

        $soFar = (int) $bookings->filter(fn (Booking $b) => $b->starts_at->timezone($tz)->lte($now))->sum($rev);
        $bookedRest = (int) $bookings->filter(fn (Booking $b) => $b->starts_at->timezone($tz)->gt($now))->sum($rev);

        // Mismo cálculo que el resumen mensual (App\Support\SalesProjection): solo días cerrados.
        $byDay = $bookings->groupBy(fn (Booking $b) => $b->starts_at->timezone($tz)->toDateString())->map(fn ($g) => (int) $g->sum($rev));
        $daily = collect(range(1, $now->daysInMonth))->map(fn ($d) => ['date' => $monthStart->copy()->day($d), 'total' => $byDay[$monthStart->copy()->day($d)->toDateString()] ?? 0]);
        $p = SalesProjection::forMonth($daily, $monthStart);
        $base = $p['closed_days'] > 0 ? $p['simple'] : 0;

        return [
            'label' => ucfirst($monthStart->locale('es')->isoFormat('MMMM YYYY')),
            'so_far' => $soFar,
            'booked_rest' => $bookedRest,
            // Nunca baja de lo vendido más lo ya agendado para el resto del mes.
            'projected' => max($base, $soFar + $bookedRest),
            'by_weekday' => $p['closed_days'] > 0 ? max($p['by_weekday'], $soFar + $bookedRest) : null,
            'per_day' => $p['per_day'] ?? 0,
            'closed_days' => $p['closed_days'],
            'days_in_month' => $p['days'],
        ];
    }

    /**
     * Cuántos llegan por cada vía: reserva en línea (catálogo), ingreso
     * presencial y reserva tomada por recepción. Las antiguas sin dato quedan
     * aparte para no inflar ninguna de las tres.
     *
     * @return array{rows: Collection, total: int}
     */
    private function byOrigin(Collection $bookings, callable $val): array
    {
        $rows = collect(['catalogo' => 'Reserva en línea', 'recepcion' => 'Reserva asistida', 'presencial' => 'Presencial', '' => 'Sin dato (antiguas)'])
            ->map(function (string $label, string $key) use ($bookings, $val) {
                $group = $bookings->filter(fn (Booking $b) => (string) $b->origin === $key);

                return ['key' => $key, 'label' => $label, 'count' => $group->count(), 'value' => (int) $group->sum($val)];
            })
            ->filter(fn (array $r) => $r['count'] > 0 || $r['key'] !== '')
            ->values();

        return ['rows' => $rows, 'total' => $bookings->count()];
    }

    /**
     * Ventas por canal de marketing (Meta, Google, sitio, presencial…) y, dentro de Meta,
     * por anuncio. Usa lo que el sistema averigua en GHL por teléfono: las reservas sin
     * ese dato quedan en su origen de siempre.
     *
     * @return array{rows: Collection, total: int}
     */
    private function byChannel(Collection $bookings, callable $val): array
    {
        $rows = $bookings
            ->map(fn (Booking $b) => [$b, ...Attribution::channelOf($b)])
            ->groupBy(fn (array $row) => $row[1])
            ->map(function (Collection $group, string $channel) use ($val) {
                $ads = $group->filter(fn (array $r) => $r[2] !== null)
                    ->groupBy(fn (array $r) => $r[2])
                    ->map(fn (Collection $g, string $ad) => ['label' => $ad, 'count' => $g->count(), 'value' => (int) $g->sum(fn (array $r) => $val($r[0]))])
                    ->sortByDesc('value')->values();

                return ['label' => $channel, 'count' => $group->count(), 'value' => (int) $group->sum(fn (array $r) => $val($r[0])), 'ads' => $ads];
            })
            ->sortByDesc('value')->values();

        return ['rows' => $rows, 'total' => $bookings->count()];
    }

    private function operations(Collection $bookings): array
    {
        $revenue = (int) $bookings->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount());
        $collected = (int) $bookings->sum(fn (Booking $b) => $b->payments->where('status', 'aprobado')->sum('amount'));

        return [
            'revenue' => $revenue,
            'collected' => $collected,
            'addons' => (int) $bookings->sum(fn (Booking $b) => $b->paidAddonsAmount()),
            'cancelled' => 0,
        ];
    }

    /**
     * Lo que falta por cobrar: estadías del rango (no canceladas, vencidas ni no-show) cuyo
     * precio más consumos supera lo pagado. Antes se calculaba solo sobre las ya pagadas
     * completas, así que siempre daba $0 (12-nov).
     *
     * @return array{balance: int, balance_count: int}
     */
    private function pending(Carbon $start, Carbon $end, ?int $categoryId): array
    {
        $owing = Booking::with(['payments', 'addons'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$start->copy(), $end->copy()])
            ->get()
            ->map(fn (Booking $b) => max(0, $b->price_final + (int) $b->addons->sum('amount')
                - (int) $b->payments->where('status', 'aprobado')->sum('amount')))
            ->filter(fn (int $due) => $due > 0);

        return ['balance' => (int) $owing->sum(), 'balance_count' => $owing->count()];
    }

    private function paymentMethods(Collection $bookings): Collection
    {
        $totals = $bookings->flatMap->payments->where('status', 'aprobado')->groupBy(fn ($payment) => $payment->paymentMethod?->name ?? 'Sin método')->map(fn ($rows) => (int) $rows->sum('amount'));
        $methods = PaymentMethod::where('is_active', true)->orderBy('name')->pluck('name');

        return $methods->mapWithKeys(fn ($name) => [$name => (int) ($totals[$name] ?? 0)])->sortDesc();
    }

    private function segments(): array
    {
        $counts = ['nuevo' => 0, 'frecuente' => 0, 'ocasional' => 0, 'esporadico' => 0];
        Customer::query()->get()->each(function (Customer $customer) use (&$counts) {
            $counts[$customer->segment()['type']]++;
        });

        return $counts;
    }
}
