<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PaymentDateController extends Controller
{
    /**
     * Ponerle a un pago el día y la hora en que de verdad se cobró. Un cobro
     * anotado después del cierre (o tarde) queda con la fecha de hoy y la caja
     * del día, el cuadre con la máquina de Mercado Pago y las ventas lo cuentan
     * en el día equivocado. Solo administrador; queda en auditoría.
     */
    public function update(Request $request, string $code, Payment $payment): RedirectResponse
    {
        abort_unless($payment->booking && $payment->booking->code === $code, 404);
        $validated = $request->validate([
            'paid_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['paid_at' => 'fecha y hora del cobro', 'reason' => 'motivo']);

        $at = Carbon::createFromFormat('Y-m-d\TH:i', $validated['paid_at'], 'America/Santiago');
        if ($at->isFuture()) {
            return back()->withErrors(['booking' => 'La fecha del cobro no puede ser futura.']);
        }

        $old = ['created_at' => $payment->created_at?->toIso8601String()];
        $payment->created_at = $at;
        $payment->save();
        AuditLog::record(auth()->id(), 'pago.fecha', 'Payment', $payment->id,
            $old + ['reason' => $validated['reason']], ['created_at' => $payment->created_at->toIso8601String()]);

        return back()->with('status', 'Fecha del cobro actualizada: '.$at->format('d/m/Y H:i').'.');
    }
}
