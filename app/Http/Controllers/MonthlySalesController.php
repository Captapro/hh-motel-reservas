<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlySalesController extends Controller
{
    private const TZ = 'America/Santiago';

    private const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /** Agrupa los métodos de pago como en el Excel del motel. */
    private function methodGroup(?string $code): string
    {
        return match ($code) {
            'debito', 'credito', 'mercado_pago' => 'Tarjeta',
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            default => 'Otro',
        };
    }

    public function show(Request $request, ?string $month = null): View|StreamedResponse
    {
        $first = $month && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01', self::TZ)->startOfDay()
            : now(self::TZ)->startOfMonth();
        $last = $first->copy()->endOfMonth();

        // Mismo criterio que la venta diaria: la venta es de la fecha de la
        // ESTADÍA y las canceladas no cuentan; el cobro es de la fecha de pago.
        $bookings = Booking::with(['room.category', 'addons', 'customer', 'payments.paymentMethod', 'payments.registeredBy'])
            ->whereBetween('starts_at', [$first->copy(), $last->copy()])
            ->where('booking_status', '!=', 'CANCELADA')
            ->orderBy('starts_at')
            ->get();

        $payments = Payment::with('paymentMethod')
            ->where('status', 'aprobado')
            ->whereBetween('created_at', [$first->copy(), $last->copy()])
            ->get();

        $stayDay = fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ);
        $total = fn (Booking $b) => $b->price_final + (int) $b->addons->sum('amount');
        $groups = ['Tarjeta', 'Efectivo', 'Transferencia', 'Otro'];

        // ===== Registro diario =====
        $byDay = $bookings->groupBy(fn (Booking $b) => $stayDay($b)->toDateString());
        $cashByDay = $payments->groupBy(fn (Payment $p) => $p->created_at->copy()->timezone(self::TZ)->toDateString());

        $daily = collect();
        for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
            $key = $d->toDateString();
            $dayBookings = $byDay->get($key, collect());
            $dayCash = $cashByDay->get($key, collect());
            $row = [
                'date' => $d->copy(),
                'rooms' => $dayBookings->count(),
                'lodging' => $dayBookings->sum('price_final'),
                'extras' => $dayBookings->sum(fn (Booking $b) => (int) $b->addons->sum('amount')),
                'cash_total' => $dayCash->sum('amount'),
            ];
            $row['total'] = $row['lodging'] + $row['extras'];
            foreach ($groups as $g) {
                $row['cash'][$g] = $dayCash->filter(fn (Payment $p) => $this->methodGroup($p->paymentMethod?->code) === $g)->sum('amount');
            }
            $daily->push($row);
        }

        $totals = [
            'rooms' => $daily->sum('rooms'),
            'lodging' => $daily->sum('lodging'),
            'extras' => $daily->sum('extras'),
            'total' => $daily->sum('total'),
            'cash_total' => $daily->sum('cash_total'),
        ];
        foreach ($groups as $g) {
            $totals['cash'][$g] = $daily->sum(fn ($r) => $r['cash'][$g]);
        }

        $detail = $this->detailRows($bookings);
        $history = $this->monthHistory();

        if ($request->query('export') === 'csv') {
            return $this->csv($first, $daily, $totals, $groups);
        }
        if ($request->query('export') === 'detalle') {
            return $this->detailCsv($first, $detail);
        }

        // ===== Por día de la semana =====
        $today = now(self::TZ)->startOfDay();
        $lastCounted = $last->lt($today) ? $last : ($first->gt($today) ? $first->copy()->subDay() : $today);
        $byWeekday = collect(self::WEEKDAYS)->map(function ($label, $iso) use ($bookings, $stayDay, $total, $first, $lastCounted) {
            $rows = $bookings->filter(fn (Booking $b) => $stayDay($b)->dayOfWeekIso === $iso);
            $days = 0;
            for ($d = $first->copy(); $d->lte($lastCounted); $d->addDay()) {
                $days += $d->dayOfWeekIso === $iso ? 1 : 0;
            }
            $sales = $rows->sum($total);

            return ['label' => $label, 'sales' => $sales, 'rooms' => $rows->count(), 'avg_per_day' => $days ? round($rows->count() / $days, 1) : 0, 'avg_price' => $rows->count() ? (int) round($sales / $rows->count()) : 0];
        });

        // ===== Por duración =====
        $byDuration = $bookings->groupBy('duration_minutes')->map(function (Collection $rows, $minutes) {
            $amount = $rows->sum('price_final');
            $hours = $rows->count() * $minutes / 60;

            return ['minutes' => (int) $minutes, 'times' => $rows->count(), 'hours' => $hours, 'amount' => $amount, 'avg_room' => (int) round($amount / max(1, $rows->count())), 'per_hour' => $hours ? (int) round($amount / $hours) : 0];
        })->sortBy('minutes')->values();

        // ===== Por habitación =====
        $lodgingTotal = max(1, $totals['lodging']);
        $byRoom = $bookings->groupBy(fn (Booking $b) => $b->room->name)->map(function (Collection $rows, $name) use ($lodgingTotal) {
            $amount = $rows->sum('price_final');

            return ['name' => $name, 'category' => $rows->first()->room->category->name, 'times' => $rows->count(), 'amount' => $amount, 'pct' => $amount / $lodgingTotal, 'avg' => (int) round($amount / max(1, $rows->count()))];
        })->sortByDesc('amount')->values();

        // ===== Por tipo de pago (cobros del mes) =====
        $cashTotal = max(1, $totals['cash_total']);
        $byMethod = collect($groups)->map(function ($g) use ($payments, $cashTotal) {
            $rows = $payments->filter(fn (Payment $p) => $this->methodGroup($p->paymentMethod?->code) === $g);

            return ['label' => $g, 'count' => $rows->count(), 'amount' => $rows->sum('amount'), 'pct' => $rows->sum('amount') / $cashTotal];
        })->filter(fn ($r) => $r['count'] > 0)->values();

        // ===== Extras vendidos =====
        $extrasTotal = max(1, $totals['extras']);
        $extras = $bookings->flatMap->addons->groupBy('description')->map(fn (Collection $rows, $name) => [
            'name' => $name, 'units' => $rows->count(), 'amount' => $rows->sum('amount'), 'pct' => $rows->sum('amount') / $extrasTotal,
        ])->sortByDesc('amount')->values();

        return view('sales.monthly', [
            'first' => $first, 'prev' => $first->copy()->subMonth(), 'next' => $first->copy()->addMonth(),
            'isCurrent' => $first->isSameMonth(now(self::TZ)),
            'daily' => $daily, 'totals' => $totals, 'groups' => $groups,
            'byWeekday' => $byWeekday, 'byDuration' => $byDuration, 'byRoom' => $byRoom,
            'byMethod' => $byMethod, 'extras' => $extras, 'detail' => $detail, 'history' => $history,
            'missingCount' => $detail->where('missing', '!=', [])->count(),
            'avgTicket' => $totals['rooms'] ? (int) round($totals['total'] / $totals['rooms']) : 0,
            'cancelled' => Booking::whereBetween('starts_at', [$first->copy(), $last->copy()])->where('booking_status', 'CANCELADA')->count(),
        ]);
    }

    /**
     * Todos los meses con ventas, del más reciente al más antiguo: venta por
     * estadía, habitaciones y lo cobrado ese mes. Es el "historial completo"
     * que se ve al abrir Ventas.
     */
    private function monthHistory(): Collection
    {
        $addons = DB::table('booking_addons')
            ->selectRaw('booking_id, SUM(amount) as total')->groupBy('booking_id')->pluck('total', 'booking_id');

        $sales = Booking::where('booking_status', '!=', 'CANCELADA')->get(['id', 'starts_at', 'price_final'])
            ->groupBy(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->format('Y-m'))
            ->map(fn (Collection $rows) => [
                'rooms' => $rows->count(),
                'lodging' => $rows->sum('price_final'),
                'extras' => (int) $rows->sum(fn (Booking $b) => (int) ($addons[$b->id] ?? 0)),
            ]);

        $cash = Payment::where('status', 'aprobado')->get(['amount', 'created_at'])
            ->groupBy(fn (Payment $p) => $p->created_at->copy()->timezone(self::TZ)->format('Y-m'))
            ->map(fn (Collection $rows) => $rows->sum('amount'));

        return $sales->keys()->merge($cash->keys())->unique()->sortDesc()->values()->map(fn ($ym) => [
            'month' => Carbon::createFromFormat('Y-m-d', $ym.'-01', self::TZ),
            'rooms' => $sales[$ym]['rooms'] ?? 0,
            'lodging' => $sales[$ym]['lodging'] ?? 0,
            'extras' => $sales[$ym]['extras'] ?? 0,
            'total' => ($sales[$ym]['lodging'] ?? 0) + ($sales[$ym]['extras'] ?? 0),
            'cash' => $cash[$ym] ?? 0,
        ]);
    }

    /** Turno según la hora de ingreso, como en el Excel del motel. */
    private function shift(Carbon $at): string
    {
        $h = $at->hour;

        return $h >= 6 && $h < 14 ? 'MAÑANA' : ($h >= 14 && $h < 20 ? 'TARDE' : 'NOCHE');
    }

    /**
     * Una fila por habitación vendida y una por cada extra, en el mismo orden
     * del "Detalle de Ventas" del Excel. 'missing' lista lo que falta
     * completar (documento, RUT, nacionalidad) para destacarlo.
     */
    private function detailRows(Collection $bookings): Collection
    {
        $docCount = [];
        foreach ($bookings as $b) {
            foreach ($b->payments->where('status', 'aprobado') as $p) {
                foreach (['V' => $p->voucher_number, 'B' => $p->receipt_number] as $kind => $num) {
                    if ($num) {
                        $docCount[$kind.$num][$b->id] = true;
                    }
                }
            }
        }

        $rows = collect();
        $n = 0;
        foreach ($bookings as $b) {
            $approved = $b->payments->where('status', 'aprobado');
            $pay = $approved->first();
            $c = $b->customer;
            $start = $b->starts_at->copy()->timezone(self::TZ);
            $in = ($b->checked_in_at ?? $b->starts_at)->copy()->timezone(self::TZ);
            $out = ($b->checked_out_at ?? $b->ends_at)->copy()->timezone(self::TZ);
            $voucher = $approved->pluck('voucher_number')->filter()->implode(' / ');
            $receipt = $approved->pluck('receipt_number')->filter()->implode(' / ');
            $repeated = $approved->contains(fn ($p) => ($p->voucher_number && count($docCount['V'.$p->voucher_number] ?? []) > 1) || ($p->receipt_number && count($docCount['B'.$p->receipt_number] ?? []) > 1));

            $missing = [];
            if ($approved->isNotEmpty() && ! $voucher && ! $receipt) {
                $missing[] = 'documento';
            }
            if (! $c?->documentNumber()) {
                $missing[] = 'RUT / documento';
            }
            if (! $c?->nationality) {
                $missing[] = 'nacionalidad';
            }

            $base = [
                'code' => $b->code, 'date' => $start, 'name' => $c?->name, 'doc_id' => $c?->documentNumber(),
                'nationality' => $c?->nationality, 'age' => $c?->age(), 'room' => $b->room->name, 'category' => $b->room->category->name,
                'guests' => $b->guests_count, 'in' => $in, 'out' => $out, 'minutes' => $b->duration_minutes,
                'tariff' => $b->rate_rule_name_snapshot, 'shift' => $this->shift($in),
                'method' => $approved->map(fn ($p) => $this->methodGroup($p->paymentMethod?->code))->unique()->implode(' + '),
                'by' => $pay?->registeredBy?->name, 'status' => $b->booking_status, 'repeated' => $repeated, 'origin' => $b->originLabel(),
            ];

            $rows->push($base + [
                'n' => ++$n, 'doc_type' => $receipt ? 'Boleta' : ($voucher ? 'Voucher' : ''), 'voucher' => $voucher, 'receipt' => $receipt,
                'kind' => 'ALOJAMIENTO', 'detail' => $b->coupon_code_snapshot ? 'Cupón '.$b->coupon_code_snapshot : '', 'qty' => '',
                'price' => $b->price_final, 'discount' => (int) $b->discount_amount, 'missing' => $missing,
            ]);
            foreach ($b->addons as $a) {
                $rows->push($base + [
                    'n' => ++$n, 'doc_type' => '', 'voucher' => '', 'receipt' => '', 'kind' => 'EXTRAS', 'detail' => $a->description,
                    'qty' => $a->quantity ?? 1, 'price' => $a->amount, 'discount' => 0, 'missing' => [],
                ]);
            }
        }

        return $rows;
    }

    private function detailCsv(Carbon $first, Collection $detail): StreamedResponse
    {
        return response()->streamDownload(function () use ($detail) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['N° REGISTRO', 'Fecha', 'Tipo de documento', 'N° voucher', 'N° boleta', 'Tipo de venta', 'Detalle', 'Cantidad', 'Nombre', 'RUT o documento', 'Nacionalidad', 'Edad', 'Habitación', 'Categoría', 'Personas', 'Hora entrada', 'Hora salida', 'Duración (h)', 'Tarifa', 'Precio', 'Descuento', 'Medio de pago', 'Turno', 'Registrado por', 'Código reserva', 'Estado', 'Origen', 'Datos faltantes']);
            foreach ($detail as $r) {
                fputcsv($out, [$r['n'], $r['date']->format('d/m/Y'), $r['doc_type'], $r['voucher'], $r['receipt'], $r['kind'], $r['detail'], $r['qty'], $r['name'], $r['doc_id'], $r['nationality'], $r['age'], $r['room'], $r['category'], $r['guests'], $r['in']->format('H:i'), $r['out']->format('H:i'), round($r['minutes'] / 60, 2), $r['tariff'], $r['price'], $r['discount'], $r['method'], $r['shift'], $r['by'], $r['code'], $r['status'], $r['origin'], implode(', ', $r['missing'])]);
            }
            fclose($out);
        }, 'detalle-ventas-'.$first->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csv(Carbon $first, Collection $daily, array $totals, array $groups): StreamedResponse
    {
        $name = 'ventas-'.$first->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($daily, $totals, $groups) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Habitaciones vendidas', 'Alojamiento', 'Extras', 'Venta total', 'Cobrado '.$groups[0], 'Cobrado '.$groups[1], 'Cobrado '.$groups[2], 'Cobrado '.$groups[3], 'Cobrado total']);
            foreach ($daily as $r) {
                fputcsv($out, [$r['date']->format('d/m/Y'), $r['rooms'], $r['lodging'], $r['extras'], $r['total'], $r['cash']['Tarjeta'], $r['cash']['Efectivo'], $r['cash']['Transferencia'], $r['cash']['Otro'], $r['cash_total']]);
            }
            fputcsv($out, ['TOTAL', $totals['rooms'], $totals['lodging'], $totals['extras'], $totals['total'], $totals['cash']['Tarjeta'], $totals['cash']['Efectivo'], $totals['cash']['Transferencia'], $totals['cash']['Otro'], $totals['cash_total']]);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
