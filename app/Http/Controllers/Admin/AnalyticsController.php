<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\RoomCategory;
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

        $all = Booking::with(['room.category', 'addons.product', 'addons.combo', 'payments.paymentMethod'])
            ->where('booking_status', '!=', 'CANCELADA')
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$prevStart->copy()->timezone('UTC'), $end->copy()->timezone('UTC')])
            ->get();

        $current = $all->filter(fn (Booking $b) => $this->inRange($b, $start, $end))->values();
        $previous = $all->filter(fn (Booking $b) => $this->inRange($b, $prevStart, $prevEnd))->values();

        $val = fn (Booking $b) => $metric === 'revenue' ? $b->price_final + $b->addons->sum('amount') : 1;
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

            'byRoom' => $this->rank($current, fn (Booking $b) => $b->room->name, $val, fn (Booking $b) => $b->room->category->name),
            'byWeekday' => $this->overlay($current, $previous, fn (Booking $b) => $weekdayNames[$b->starts_at->timezone('America/Santiago')->dayOfWeekIso - 1], $val, $weekdayNames),
            'byHour' => $this->buckets($current, fn (Booking $b) => $b->starts_at->timezone('America/Santiago')->hour, $val, range(0, 23)),
            'topDays' => $current->groupBy(fn (Booking $b) => $b->starts_at->timezone('America/Santiago')->toDateString())
                ->map(fn ($g, $k) => ['date' => Carbon::parse($k), 'value' => $sum($g), 'count' => $g->count()])
                ->sortByDesc('value')->take(8)->values(),

            'byOrigin' => $this->byOrigin($current, $val),
            'monthly' => $this->monthlyComparison($metric, $categoryId),
            'projection' => $this->monthProjection($categoryId),
            'operations' => $this->operations($current),
            'paymentMethods' => $this->paymentMethods($current),
            'bookingStatuses' => $current->groupBy('booking_status')->map->count()->sortDesc(),
            'segments' => $this->segments(),
            'extras' => $current->flatMap->addons->groupBy('description')->map(fn ($rows) => ['quantity' => (int) $rows->sum('quantity'), 'value' => (int) $rows->sum('amount')])->sortByDesc('value'),
        ]));
    }

    /**
     * Una fila por reserva, con todas las dimensiones sueltas (habitación,
     * categoría, ala, cliente, fecha, precios, pago) para que se pueda armar
     * tablas dinámicas y cruces en Excel/Sheets -- no intentamos adivinar
     * qué cruce quiere ver el usuario, le damos el detalle crudo.
     */
    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveRange($request);
        $categoryId = (int) $request->query('category', 0) ?: null;

        $bookings = Booking::with(['room.category', 'customer', 'addons', 'payments.paymentMethod'])
            ->where('booking_status', '!=', 'CANCELADA')
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$start->copy()->timezone('UTC'), $end->copy()->timezone('UTC')])
            ->orderBy('starts_at')
            ->get();

        $filename = 'ventas_hh_motel_'.$start->format('Y-m-d').'_a_'.$end->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM -- sin esto Excel rompe las tildes/ñ
            fputcsv($out, [
                'Código', 'Fecha', 'Hora', 'Día semana', 'Habitación', 'Categoría', 'Ala',
                'Duración (min)', 'Cliente', 'Teléfono', 'Tarifa aplicada',
                'Precio original', 'Descuento', 'Precio final habitación', 'Extras',
                'Total', 'Métodos de pago', 'Estado reserva', 'Estado pago',
            ]);
            foreach ($bookings as $b) {
                $startsAt = $b->starts_at->timezone('America/Santiago');
                $extrasTotal = (int) $b->addons->sum('amount');
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
        $rev = fn (Collection $c) => (int) $c->sum(fn (Booking $b) => $b->price_final + $b->addons->sum('amount'));
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

        $bookings = Booking::with('addons')
            ->where('booking_status', '!=', 'CANCELADA')
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->where('starts_at', '>=', $firstMonth->copy()->timezone('UTC'))
            ->get();

        $val = fn (Booking $b) => $metric === 'revenue' ? $b->price_final + $b->addons->sum('amount') : 1;

        $monthly = collect();
        $prev = null;

        for ($i = 11; $i >= 0; $i--) {
            $month = now('America/Santiago')->startOfMonth()->subMonths($i);
            $rows = $bookings->filter(fn (Booking $b) => $b->starts_at->timezone('America/Santiago')->isSameMonth($month));
            $value = (int) $rows->sum($val);

            $delta = ($prev !== null && $prev > 0) ? (int) round((($value - $prev) / $prev) * 100) : null;

            $monthly->push([
                'label' => ucfirst($month->locale('es')->isoFormat('MMM YYYY')),
                'count' => $rows->count(),
                'value' => $value,
                'delta_pct' => $delta,
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

        $bookings = Booking::with('addons')
            ->where('booking_status', '!=', 'CANCELADA')
            ->when($categoryId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('room_category_id', $categoryId)))
            ->whereBetween('starts_at', [$monthStart->copy()->timezone('UTC'), $monthEnd->copy()->timezone('UTC')])
            ->get();

        $rev = fn (Booking $b) => $b->price_final + $b->addons->sum('amount');

        $soFar = (int) $bookings->filter(fn (Booking $b) => $b->starts_at->timezone($tz)->lte($now))->sum($rev);
        $bookedRest = (int) $bookings->filter(fn (Booking $b) => $b->starts_at->timezone($tz)->gt($now))->sum($rev);

        $daysElapsed = max(1, (int) $monthStart->diffInDays($now) + 1);
        $daysInMonth = $now->daysInMonth;
        $runRate = $soFar / $daysElapsed;
        $projected = max((int) round($runRate * $daysInMonth), $soFar + $bookedRest);

        return [
            'label' => ucfirst($monthStart->locale('es')->isoFormat('MMMM YYYY')),
            'so_far' => $soFar,
            'booked_rest' => $bookedRest,
            'projected' => $projected,
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
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
        $rows = collect(['catalogo' => 'En línea', 'presencial' => 'Presencial', 'recepcion' => 'Reserva en recepción', '' => 'Sin dato (antiguas)'])
            ->map(function (string $label, string $key) use ($bookings, $val) {
                $group = $bookings->filter(fn (Booking $b) => (string) $b->origin === $key);

                return ['key' => $key, 'label' => $label, 'count' => $group->count(), 'value' => (int) $group->sum($val)];
            })
            ->filter(fn (array $r) => $r['count'] > 0 || $r['key'] !== '')
            ->values();

        return ['rows' => $rows, 'total' => $bookings->count()];
    }

    private function operations(Collection $bookings): array
    {
        $revenue = (int) $bookings->sum(fn (Booking $b) => $b->price_final + $b->addons->sum('amount'));
        $collected = (int) $bookings->sum(fn (Booking $b) => $b->payments->where('status', 'aprobado')->sum('amount'));

        return [
            'revenue' => $revenue,
            'collected' => $collected,
            'balance' => max(0, $revenue - $collected),
            'addons' => (int) $bookings->sum(fn (Booking $b) => $b->addons->sum('amount')),
            'cancelled' => 0,
        ];
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
