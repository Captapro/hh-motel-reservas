<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Caja: cuánto efectivo debería haber físicamente en el mesón ahora mismo
 * (saldo acumulado, no solo del día) más el detalle día a día de qué entró
 * y qué salió — pagos en efectivo de reservas + movimientos manuales de
 * caja chica (ingresos y egresos que no vienen de una reserva).
 */
class CashRegisterController extends Controller
{
    public function show(?string $date = null): View
    {
        $day = $date ? Carbon::parse($date, 'America/Santiago')->startOfDay() : now('America/Santiago')->startOfDay();

        $cashMethodId = PaymentMethod::where('code', 'efectivo')->value('id');

        $balance = CashMovement::balance();
        $totalCashFromSales = $balance['sales'];
        $totalIngresos = $balance['ingresos'];
        $totalEgresos = $balance['egresos'];
        $saldoEnCaja = $balance['saldo'];

        // Detalle del día elegido, para el desglose día a día.
        $dayPayments = Payment::with(['booking.room', 'booking.customer'])
            ->where('payment_method_id', $cashMethodId)
            ->where('status', 'aprobado')
            ->whereBetween('created_at', [$day->copy(), $day->copy()->endOfDay()])
            ->get();
        $dayMovements = CashMovement::whereBetween('created_at', [$day->copy(), $day->copy()->endOfDay()])->get();

        $timeline = $dayPayments->map(fn (Payment $p) => [
            'time' => $p->created_at->timezone('America/Santiago'),
            'kind' => 'ingreso',
            'label' => 'Pago en efectivo — '.($p->booking?->room?->name ?? '—').' · '.($p->booking?->customer?->name ?? '—'),
            'code' => $p->booking?->code,
            'amount' => $p->amount,
        ])->concat($dayMovements->map(fn (CashMovement $m) => [
            'time' => $m->created_at->timezone('America/Santiago'),
            'kind' => $m->type,
            'label' => ($m->type === 'ingreso' ? 'Ingreso manual' : 'Egreso').($m->description ? ' — '.$m->description : ''),
            'code' => null,
            'amount' => $m->amount,
        ]))->sortByDesc('time')->values();

        $dayCashIn = (int) $dayPayments->sum('amount') + (int) $dayMovements->where('type', 'ingreso')->sum('amount');
        $dayCashOut = (int) $dayMovements->where('type', 'egreso')->sum('amount');

        // Todo lo que se cobró este día, en cualquier medio de pago, y de
        // dónde viene: estadías de este mismo día, anticipos de reservas
        // futuras o saldos de días anteriores.
        $dayEnd = $day->copy()->endOfDay();
        $collected = Payment::with(['paymentMethod', 'booking.room', 'booking.customer'])
            ->where('status', 'aprobado')
            ->whereBetween('created_at', [$day->copy(), $dayEnd])
            ->get();
        // Mercado Pago va aparte de la máquina: al cerrar el turno se cuadra
        // la máquina por un lado y lo que entró por internet por el otro.
        $groupOf = fn (?string $code) => match ($code) {
            'debito', 'credito' => 'Tarjeta (máquina)',
            'mercado_pago' => 'Mercado Pago (online)',
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            default => 'Otro',
        };
        $collectedByGroup = collect(['Tarjeta (máquina)', 'Mercado Pago (online)', 'Efectivo', 'Transferencia', 'Otro'])->mapWithKeys(
            fn ($g) => [$g => $collected->filter(fn (Payment $p) => $groupOf($p->paymentMethod?->code) === $g)->sum('amount')]
        );
        $stayOf = fn (Payment $p) => $p->booking?->starts_at->copy()->timezone('America/Santiago');
        $advance = $collected->filter(fn (Payment $p) => $stayOf($p)?->gt($dayEnd));
        $sameDay = $collected->filter(fn (Payment $p) => $stayOf($p)?->between($day, $dayEnd));
        $late = $collected->reject(fn (Payment $p) => $stayOf($p)?->gt($dayEnd) || $stayOf($p)?->between($day, $dayEnd));

        return view('cash.show', [
            'collectedByGroup' => $collectedByGroup,
            'collectedTotal' => $collected->sum('amount'),
            'collectedSameDay' => $sameDay->sum('amount'),
            'collectedAdvance' => $advance->sum('amount'),
            'collectedLate' => $late->sum('amount'),
            'advancePayments' => $advance->values(),
            'day' => $day,
            'saldoEnCaja' => $saldoEnCaja,
            'totalCashFromSales' => $totalCashFromSales,
            'totalIngresos' => $totalIngresos,
            'totalEgresos' => $totalEgresos,
            'timeline' => $timeline,
            'dayCashIn' => $dayCashIn,
            'dayCashOut' => $dayCashOut,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:ingreso,egreso'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        CashMovement::create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $label = $validated['type'] === 'ingreso' ? 'Ingreso' : 'Egreso';

        return redirect()->route('cash.show')
            ->with('status', $label.' de $'.number_format($validated['amount'], 0, ',', '.').' registrado en caja.');
    }
}
