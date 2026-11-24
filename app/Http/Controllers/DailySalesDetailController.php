<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Detalle de ventas del día en el formato de la planilla de recepción, sacado del
 * sistema. Las ventas se agrupan por el DÍA DE LA ESTADÍA (no por el día del cobro):
 * una reserva online se paga el día en que se hace, pero en la planilla va el día
 * del check-in. Así la planilla se copia desde acá y no se digita a mano.
 */
class DailySalesDetailController extends Controller
{
    private const TZ = 'America/Santiago';

    private const GROUPS = [
        'debito' => 'Tarjeta · máquina (débito)',
        'credito' => 'Tarjeta · máquina (crédito)',
        'mercado_pago' => 'Mercado Pago · online',
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
    ];

    public function show(Request $request, ?string $date = null): View|StreamedResponse
    {
        try {
            $day = $date ? Carbon::createFromFormat('Y-m-d', $date, self::TZ)->startOfDay() : now(self::TZ)->startOfDay();
        } catch (\Throwable) {
            abort(404);
        }
        // 2026-13-45 "se corrige solo" a otra fecha: una fecha que no existe es un 404.
        abort_if($date && $day->format('Y-m-d') !== $date, 404);
        $end = $day->copy()->endOfDay();

        $bookings = Booking::with(['room.category', 'customer', 'payments.paymentMethod', 'addons'])
            ->whereNotNull('checked_in_at')
            ->whereBetween('starts_at', [$day->copy(), $end])
            ->orderBy('starts_at')
            ->get();

        // Una fila por cobro, como la planilla. Una venta sin pagos igual aparece (monto 0).
        $rows = $bookings->flatMap(function (Booking $b) use ($day) {
            $approved = $b->payments->where('status', 'aprobado')->sortBy('created_at')->values();
            $extras = (int) $b->paidAddonsAmount();
            $base = [
                'start' => $b->starts_at->copy()->timezone(self::TZ),
                'code' => $b->code,
                'customer' => $b->customer?->name,
                'doc' => $b->customer?->documentNumber(),
                'room' => $b->room->name,
                'category' => $b->room->category->name,
                'lodging' => (int) $b->price_final,
                'extras' => $extras,
                'total' => (int) $b->price_final + $extras,
            ];
            if ($approved->isEmpty()) {
                return [$base + ['first' => true, 'method' => '— sin cobro —', 'code_m' => null, 'amount' => 0, 'voucher' => '', 'receipt' => '', 'paid_at' => null, 'prepaid' => false]];
            }

            return $approved->map(function (Payment $p, int $i) use ($base, $day) {
                $paidAt = $p->created_at->copy()->timezone(self::TZ);

                return $base + [
                    'first' => $i === 0,
                    'method' => self::GROUPS[$p->paymentMethod?->code] ?? (string) $p->paymentMethod?->name,
                    'code_m' => $p->paymentMethod?->code,
                    'amount' => (int) $p->amount,
                    'voucher' => trim((string) $p->voucher_number),
                    'receipt' => trim((string) $p->receipt_number),
                    'paid_at' => $paidAt,
                    // Cobrado otro día (típico de una reserva online prepagada).
                    'prepaid' => ! $paidAt->isSameDay($day),
                ];
            })->all();
        })->values();

        $byMethod = collect(self::GROUPS)->map(fn ($label, $code) => [
            'label' => $label,
            'count' => $rows->where('code_m', $code)->count(),
            'total' => (int) $rows->where('code_m', $code)->sum('amount'),
        ])->filter(fn ($g) => $g['count'] > 0);
        $stays = $rows->where('first', true);

        // Lo cobrado ESTE día por estadías de OTRO día: no está arriba, pero sí entró al sistema hoy.
        $otherDays = Payment::with(['paymentMethod', 'booking.room', 'booking.customer'])
            ->where('status', 'aprobado')
            ->whereBetween('created_at', [$day->copy(), $end])
            ->whereHas('booking', fn ($q) => $q->where(fn ($w) => $w->whereNull('starts_at')->orWhereNotBetween('starts_at', [$day->copy(), $end])))
            ->orderBy('created_at')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('detalle-de-ventas-'.$day->format('Y-m-d'), $rows);
        }

        return view('cash.detail', [
            'day' => $day,
            'prev' => $day->copy()->subDay()->format('Y-m-d'),
            'next' => $day->copy()->addDay()->format('Y-m-d'),
            'isToday' => $day->isSameDay(now(self::TZ)),
            'rows' => $rows,
            'byMethod' => $byMethod,
            'stays' => $stays->count(),
            'staysTotal' => (int) $stays->sum('total'),
            'collectedTotal' => (int) $rows->sum('amount'),
            'otherDays' => $otherDays,
        ]);
    }

    private function csv(string $name, Collection $rows): StreamedResponse
    {
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
        $headers = ['Fecha estadía', 'Hora ingreso', 'Reserva', 'Cliente', 'RUT / documento', 'Playroom', 'Categoría', 'Alojamiento', 'Extras', 'Total venta', 'Medio de pago', 'Monto cobrado', 'N° voucher', 'N° boleta', 'Cobrado el'];

        return response()->streamDownload(function () use ($rows, $headers, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $r) {
                fputcsv($out, array_map($safe, [
                    $r['start']->format('d/m/Y'), $r['start']->format('H:i'), $r['code'], $r['first'] ? $r['customer'] : '', $r['first'] ? $r['doc'] : '',
                    $r['room'], $r['category'], $r['first'] ? $r['lodging'] : '', $r['first'] ? $r['extras'] : '', $r['first'] ? $r['total'] : '',
                    $r['method'], $r['amount'], $r['voucher'], $r['receipt'], $r['paid_at']?->format('d/m/Y H:i') ?? '',
                ]), ';');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
