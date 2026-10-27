<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\MercadoPagoClient;
use App\Services\Payments\MercadoPagoPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentRefundController extends Controller
{
    /**
     * Devolver un pago, completo o una parte. Los de Mercado Pago se
     * devuelven por su API; los de efectivo, transferencia o tarjeta se
     * devuelven por fuera y acá solo queda el registro para que la Caja y el
     * cobrado cuadren.
     */
    public function store(Request $request, string $code, Payment $payment, MercadoPagoClient $mp, MercadoPagoPayments $payments): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();
        abort_unless($payment->booking_id === $booking->id, 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:'.$payment->amount],
        ], [
            'amount.max' => 'No puedes devolver más que el monto del pago ($'.number_format($payment->amount, 0, ',', '.').').',
        ], ['reason' => 'motivo', 'amount' => 'monto a devolver']);
        $refundAmount = (int) ($validated['amount'] ?? $payment->amount);
        $partial = $refundAmount < $payment->amount;

        if ($payment->status !== 'aprobado') {
            return back()->withErrors(['booking' => 'Este pago ya no está vigente: no se puede devolver.']);
        }

        $online = (bool) preg_match('/^MP-(\d+)/', (string) $payment->external_id, $mpMatch);
        if ($online) {
            if (! $mp->enabled()) {
                return back()->withErrors(['booking' => 'Mercado Pago no está configurado: no se puede devolver este pago desde aquí.']);
            }
            try {
                $mp->refund($mpMatch[1], $partial || $payment->external_id !== 'MP-'.$mpMatch[1] ? $refundAmount : null, 'hh-refund-'.$payment->id.'-'.$refundAmount);
            } catch (\Throwable $e) {
                return back()->withErrors(['booking' => $e->getMessage()]);
            }
        }

        if ($partial) {
            $payments->markPartiallyRefunded($payment, $refundAmount, $validated['reason'], auth()->id());
        } else {
            $payments->markRefunded($payment, $validated['reason'], auth()->id());
        }

        $amountText = '$'.number_format($refundAmount, 0, ',', '.');

        return back()->with('status', $online
            ? 'Devolución de '.$amountText.' enviada a Mercado Pago y registrada.'
            : 'Devolución de '.$amountText.' registrada. Recuerda devolver el dinero al cliente por fuera del sistema.');
    }
}
