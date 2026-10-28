<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\HistoricalSale;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\View\View;

class DailySalesController extends Controller
{
    /**
     * Dos miradas del mismo día, porque no son lo mismo y mezclarlas es lo
     * que no cuadraba:
     *
     *  - "Habitaciones vendidas" (venta devengada): reservas cuya ESTADÍA
     *    (starts_at) cae este día — cuántas habitaciones se vendieron y por
     *    cuánto, sin importar cuándo se cobró esa plata.
     *  - "Cobros de caja" (venta de caja): pagos cuya FECHA DE COBRO
     *    (created_at) cae este día — la plata que de verdad entró hoy al
     *    mesón, sin importar para qué estadía es. Si alguien paga hoy una
     *    reserva de mañana, ese cobro es un ANTICIPO: entra en la caja de
     *    hoy, pero no es venta de la estadía de hoy.
     *
     * El cuadre es: cobros de caja de hoy = venta de hoy ya cobrada +
     * anticipos de reservas futuras + saldos de estadías de días anteriores
     * que se terminaron de pagar hoy.
     */
    public function show(?string $date = null): View
    {
        $day = $date ? Carbon::parse($date, 'America/Santiago')->startOfDay() : now('America/Santiago')->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        // ===== Habitaciones vendidas (venta devengada por estadía) =====
        $bookings = Booking::with(['room.category', 'customer', 'payments.paymentMethod', 'addons'])
            ->whereBetween('starts_at', [$day->copy(), $dayEnd])
            ->orderBy('starts_at')
            ->get();

        $active = $bookings->reject(fn (Booking $b) => in_array($b->booking_status, ['CANCELADA', 'EXPIRADA'], true));

        // Venta = solo estadías con su pago completo; las pendientes se ven
        // en la tabla y en "Pendiente", pero no suman a la venta.
        $sold = $active->filter(fn (Booking $b) => $b->isPaidSale());

        $revenue = $sold->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount());
        $collected = $active->sum(fn (Booking $b) => $b->paidAmount());
        $pending = $active->sum(fn (Booking $b) => $b->balanceDue());

        $byTariff = $sold
            ->groupBy('rate_rule_name_snapshot')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'revenue' => $group->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount()),
            ]);

        // De la venta de hoy, cuánto ya se había cobrado ANTES de hoy (anticipo
        // recibido en un día anterior) — para no hacer pasar esa plata de nuevo
        // como si hubiese entrado hoy a la caja.
        $collectedInAdvanceForToday = $active
            ->flatMap(fn (Booking $b) => $b->payments->where('status', 'aprobado'))
            ->filter(fn (Payment $p) => $p->created_at->timezone('America/Santiago')->lt($day))
            ->sum('amount');

        // ===== Cobros de caja (venta de caja por fecha real de pago) =====
        $paymentsToday = Payment::with(['paymentMethod', 'booking'])
            ->where('status', 'aprobado')
            ->whereBetween('created_at', [$day->copy(), $dayEnd])
            ->get();

        $cashByMethod = $paymentsToday
            ->groupBy(fn (Payment $p) => $p->paymentMethod->name)
            ->map(fn ($group) => $group->sum('amount'))
            ->sortDesc();
        $cashTotal = $paymentsToday->sum('amount');

        $isSameStayDay = fn (Payment $p) => $p->booking
            && $p->booking->starts_at->timezone('America/Santiago')->between($day, $dayEnd);
        $isFutureStay = fn (Payment $p) => $p->booking
            && $p->booking->starts_at->timezone('America/Santiago')->gt($dayEnd);

        $advancePayments = $paymentsToday->filter($isFutureStay);
        $samedayPayments = $paymentsToday->filter($isSameStayDay);
        $latePayments = $paymentsToday->reject($isSameStayDay)->reject($isFutureStay);

        $advanceTotal = $advancePayments->sum('amount');
        $advanceBookings = $advancePayments->unique('booking_id')->values();
        $lateTotal = $latePayments->sum('amount');

        // Ventas anotadas en la planilla de recepción ese día (antes del sistema).
        $planilla = HistoricalSale::with('guests')->whereDate('sold_on', $day->toDateString())->orderBy('starts_time')->orderBy('source_row')->get();

        return view('sales.daily', [
            'day' => $day,
            'planilla' => $planilla,
            'bookings' => $bookings,
            'sold' => $sold,
            'revenue' => $revenue,
            'collected' => $collected,
            'pending' => $pending,
            'collectedInAdvanceForToday' => $collectedInAdvanceForToday,
            'byTariff' => $byTariff,
            'cancelledCount' => $bookings->count() - $active->count(),
            'cashByMethod' => $cashByMethod,
            'cashTotal' => $cashTotal,
            'samedayTotal' => $samedayPayments->sum('amount'),
            'advanceTotal' => $advanceTotal,
            'advanceBookings' => $advanceBookings,
            'lateTotal' => $lateTotal,
        ]);
    }
}
