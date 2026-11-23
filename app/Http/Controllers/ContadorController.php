<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\MercadoPagoClient;
use App\Services\Payments\MercadoPagoHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Panel del contador / auditor: solo lectura. Resumen del mes, libro de
 * ventas, libro de cobros y conciliación con Mercado Pago, cada uno con su
 * planilla (CSV con tildes bien para Excel). Cada descarga queda en la
 * bitácora de auditoría.
 */
class ContadorController extends Controller
{
    private const TZ = 'America/Santiago';

    private function month(Request $request): Carbon
    {
        $m = (string) $request->query('mes');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)
            ? Carbon::createFromFormat('Y-m-d', $m.'-01', self::TZ)->startOfDay()
            : now(self::TZ)->startOfMonth();
    }

    /** Variables comunes: navegación entre meses. */
    private function nav(Carbon $first): array
    {
        return [
            'first' => $first,
            'prev' => $first->copy()->subMonth()->format('Y-m'),
            'next' => $first->copy()->addMonth()->format('Y-m'),
            'isCurrent' => $first->format('Y-m') === now(self::TZ)->format('Y-m'),
        ];
    }

    /** Ventas del mes: por fecha de la estadía, sin canceladas, solo pagadas completas (igual que Ventas). */
    private function sales(Carbon $first): Collection
    {
        return Booking::with(['room.category', 'addons', 'customer', 'payments.paymentMethod'])
            ->whereBetween('starts_at', [$first->copy(), $first->copy()->endOfMonth()])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale())
            ->values();
    }

    /** Cobros aprobados del mes, por fecha de pago. */
    private function payments(Carbon $first): Collection
    {
        return Payment::with(['paymentMethod', 'booking:id,code', 'registeredBy:id,name', 'refunds'])
            ->where('status', 'aprobado')
            ->whereBetween('created_at', [$first->copy(), $first->copy()->endOfMonth()])
            ->orderBy('created_at')
            ->get();
    }

    private function refunds(Carbon $first): Collection
    {
        return Refund::with(['payment.paymentMethod', 'payment.booking:id,code', 'processedBy:id,name'])
            ->where('status', 'procesado')
            ->whereBetween('created_at', [$first->copy(), $first->copy()->endOfMonth()])
            ->orderBy('created_at')
            ->get();
    }

    /** IVA contenido en un monto con IVA incluido (19 %). Referencial: la fuente tributaria es la boleta. */
    private function iva(int $gross): int
    {
        return (int) round($gross * 19 / 119);
    }

    public function index(Request $request): View|StreamedResponse
    {
        $first = $this->month($request);
        $bookings = $this->sales($first);
        $payments = $this->payments($first);
        $refunds = $this->refunds($first);

        $lodging = (int) $bookings->sum('price_final');
        $extras = (int) $bookings->sum(fn (Booking $b) => $b->paidAddonsAmount());
        $discounts = (int) $bookings->sum('discount_amount');
        $sold = $lodging + $extras;
        $collected = (int) $payments->sum('amount');

        $byMethod = $payments->groupBy(fn (Payment $p) => $p->methodLabel())
            ->map(fn (Collection $rows, $label) => ['label' => $label, 'count' => $rows->count(), 'amount' => (int) $rows->sum('amount')])
            ->sortByDesc('amount')->values();

        $daily = collect();
        $sellByDay = $bookings->groupBy(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->toDateString());
        $payByDay = $payments->groupBy(fn (Payment $p) => $p->created_at->copy()->timezone(self::TZ)->toDateString());
        for ($d = $first->copy(); $d->lte($first->copy()->endOfMonth()); $d->addDay()) {
            $k = $d->toDateString();
            $s = $sellByDay->get($k, collect());
            $c = $payByDay->get($k, collect());
            if ($s->isEmpty() && $c->isEmpty()) {
                continue;
            }
            $daily->push([
                'date' => $d->copy(), 'rooms' => $s->count(),
                'lodging' => (int) $s->sum('price_final'),
                'extras' => (int) $s->sum(fn (Booking $b) => $b->paidAddonsAmount()),
                'collected' => (int) $c->sum('amount'),
            ]);
        }

        if ($request->query('export') === 'csv') {
            $this->audit('resumen', $first);

            return $this->csv('resumen-diario-'.$first->format('Y-m'), ['Fecha', 'Playrooms vendidos', 'Alojamiento', 'Extras', 'Venta total', 'Cobrado ese día'],
                $daily->map(fn ($r) => [$r['date']->format('d/m/Y'), $r['rooms'], $r['lodging'], $r['extras'], $r['lodging'] + $r['extras'], $r['collected']])
                    ->push(['TOTAL', $bookings->count(), $lodging, $extras, $sold, $collected]));
        }

        $noDoc = $payments->filter(fn (Payment $p) => ! $p->voucher_number && ! $p->receipt_number && $p->paymentMethod?->code !== 'mercado_pago')->count();

        return view('contador.index', $this->nav($first) + [
            'sold' => $sold, 'lodging' => $lodging, 'extras' => $extras, 'discounts' => $discounts, 'rooms' => $bookings->count(),
            'collected' => $collected, 'refunded' => (int) $refunds->sum('amount'), 'refundCount' => $refunds->count(),
            'iva' => $this->iva($sold), 'net' => $sold - $this->iva($sold),
            'byMethod' => $byMethod, 'daily' => $daily, 'noDoc' => $noDoc,
        ]);
    }

    public function ventas(Request $request): View|StreamedResponse
    {
        $first = $this->month($request);
        $rows = $this->sales($first)->map(function (Booking $b) {
            $approved = $b->payments->where('status', 'aprobado');
            $extras = (int) $b->paidAddonsAmount();

            return [
                'date' => $b->starts_at->copy()->timezone(self::TZ),
                'code' => $b->code,
                'customer' => $b->customer?->name,
                'doc' => $b->customer?->documentNumber(),
                'room' => $b->room->name,
                'category' => $b->room->category->name,
                'minutes' => $b->duration_minutes,
                'lodging' => (int) $b->price_final,
                'discount' => (int) $b->discount_amount,
                'coupon' => $b->coupon_code_snapshot,
                'extras' => $extras,
                'total' => (int) $b->price_final + $extras,
                'methods' => $approved->map(fn ($p) => $p->methodLabel())->unique()->implode(' + '),
                'vouchers' => $approved->pluck('voucher_number')->filter()->implode(' / '),
                'receipts' => $approved->pluck('receipt_number')->filter()->implode(' / '),
                'status' => $b->booking_status,
            ];
        });

        if ($request->query('export') === 'csv') {
            $this->audit('ventas', $first);

            return $this->csv('libro-de-ventas-'.$first->format('Y-m'),
                ['Fecha', 'Reserva', 'Cliente', 'RUT / documento', 'Playroom', 'Categoría', 'Duración (min)', 'Alojamiento', 'Descuento', 'Cupón', 'Extras', 'Total', 'IVA contenido (19%, ref.)', 'Medios de pago', 'N° voucher', 'N° boleta', 'Estado'],
                $rows->map(fn ($r) => [$r['date']->format('d/m/Y H:i'), $r['code'], $r['customer'], $r['doc'], $r['room'], $r['category'], $r['minutes'], $r['lodging'], $r['discount'], $r['coupon'], $r['extras'], $r['total'], $this->iva($r['total']), $r['methods'], $r['vouchers'], $r['receipts'], $r['status']]));
        }

        return view('contador.ventas', $this->nav($first) + ['rows' => $rows]);
    }

    public function pagos(Request $request): View|StreamedResponse
    {
        $first = $this->month($request);
        $payments = $this->payments($first);
        $refunds = $this->refunds($first);

        if ($request->query('export') === 'csv') {
            $this->audit('cobros', $first);
            $rows = $payments->map(fn (Payment $p) => [
                $p->created_at->copy()->timezone(self::TZ)->format('d/m/Y H:i'), 'Cobro', $p->booking?->code, $p->methodLabel(), (int) $p->amount,
                $p->voucher_number, $p->receipt_number, $p->mercadoPagoOperation(), $p->registeredBy?->name, '',
            ])->concat($refunds->map(fn (Refund $r) => [
                $r->created_at->copy()->timezone(self::TZ)->format('d/m/Y H:i'), 'Devolución', $r->payment?->booking?->code, $r->payment?->methodLabel(), -(int) $r->amount,
                $r->payment?->voucher_number, $r->payment?->receipt_number, $r->payment?->mercadoPagoOperation(), $r->processedBy?->name, $r->reason,
            ]))->sortBy(fn ($r) => Carbon::createFromFormat('d/m/Y H:i', $r[0], self::TZ))->values();

            return $this->csv('libro-de-cobros-'.$first->format('Y-m'),
                ['Fecha', 'Tipo', 'Reserva', 'Medio de pago', 'Monto', 'N° voucher', 'N° boleta', 'N° operación MP', 'Registrado por', 'Motivo (devoluciones)'], $rows);
        }

        return view('contador.pagos', $this->nav($first) + ['payments' => $payments, 'refunds' => $refunds]);
    }

    public function conciliacion(Request $request, MercadoPagoClient $client, MercadoPagoHistory $history): View|StreamedResponse
    {
        $first = $this->month($request);
        $data = null;
        $error = null;
        if (! $client->enabled()) {
            $error = 'Mercado Pago no está conectado (falta la credencial en el servidor).';
        } else {
            try {
                $data = $history->month($first);
            } catch (Throwable $e) {
                Log::warning('Contador: no se pudo leer Mercado Pago', ['error' => $e->getMessage()]);
                $error = 'Mercado Pago no respondió. Intenta de nuevo en un minuto.';
            }
        }

        if ($data && $request->query('export') === 'movimientos') {
            $this->audit('conciliacion-movimientos', $first);

            return $this->csv('mercado-pago-movimientos-'.$first->format('Y-m'),
                ['Fecha', 'N° operación', 'Estado', 'Medio', 'Canal', 'Monto cobrado', 'Devuelto', 'Comisión MP', 'Neto recibido', 'Reserva', 'Código autorización', 'N° voucher', 'En el sistema', 'Observación'],
                $data['rows']->sortBy('at')->map(fn (array $r) => [
                    $r['at']->format('d/m/Y H:i'), $r['id'], $r['status_label'], $r['type'], $r['channel'], $r['amount'], $r['refunded'], $r['fee'], $r['net'],
                    $r['code'], $r['auth'], $r['voucher'], $r['in_system'] ? 'Sí' : 'No',
                    $r['missing'] ? 'Aprobado en MP y falta en el sistema' : ($r['deleted_booking'] ? 'Reserva ya no existe' : ''),
                ]));
        }
        if ($data && $request->query('export') === 'dias') {
            $this->audit('conciliacion-dias', $first);

            return $this->csv('conciliacion-diaria-'.$first->format('Y-m'),
                ['Fecha', 'Movimientos MP', 'Cobrado MP (bruto)', 'Neto MP', 'Máquina (Point)', 'Tarjeta anotada en sistema', 'Diferencia máquina − sistema', 'Estado'],
                $data['days']->sortBy(fn ($d) => $d['day']->timestamp)->map(fn (array $d) => [
                    $d['day']->format('d/m/Y'), $d['count'], $d['gross'], $d['net'], $d['machine'], $d['system'], $d['machine'] - $d['system'],
                    $d['machine'] === $d['system'] ? 'Cuadra' : ($d['compensated_with'] ? 'Compensada con '.$d['compensated_with']->format('d/m') : 'Diferencia'),
                ]));
        }

        // Detalle de un día: el elegido o, si no, el último del mes con movimientos.
        $day = null;
        $dayRows = collect();
        if ($data) {
            $wanted = (string) $request->query('dia');
            $available = $data['rows']->map(fn (array $r) => $r['at']->toDateString())->unique()->sortDesc()->values();
            $day = $available->contains($wanted) ? $wanted : $available->first();
            $dayRows = $data['rows']->filter(fn (array $r) => $r['at']->toDateString() === $day)->sortBy(fn (array $r) => $r['at']->getTimestamp())->values();
        }

        return view('contador.conciliacion', $this->nav($first) + ['data' => $data, 'error' => $error, 'day' => $day, 'dayRows' => $dayRows]);
    }

    private function audit(string $what, Carbon $first): void
    {
        AuditLog::record(auth()->id(), 'contador.exportar', 'Planilla', $what.'-'.$first->format('Y-m'), null, ['mes' => $first->format('Y-m')]);
    }

    /** CSV con BOM (Excel respeta tildes y ñ) y celdas de texto a salvo de fórmulas. */
    private function csv(string $name, array $headers, iterable $rows): StreamedResponse
    {
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;

        return response()->streamDownload(function () use ($headers, $rows, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map($safe, $row), ';');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
