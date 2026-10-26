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
     * Devolver un pago (total). Los de Mercado Pago se devuelven por su API;
     * los de efectivo, transferencia o tarjeta se devuelven por fuera y acá
     * solo queda el registro para que la Caja y el cobrado cuadren.
     */
    public function store(Request $request, string $code, Payment $payment, MercadoPagoClient $mp, MercadoPagoPayments $payments): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();
        abort_unless($payment->booking_id === $booking->id, 404);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($payment->status !== 'aprobado') {
            return back()->withErrors(['booking' => 'Este pago ya no está vigente: no se puede devolver.']);
        }

        $online = str_starts_with((string) $payment->external_id, 'MP-');
        if ($online) {
            if (! $mp->enabled()) {
                return back()->withErrors(['booking' => 'Mercado Pago no está configurado: no se puede devolver este pago desde aquí.']);
            }
            try {
                $mp->refund(substr((string) $payment->external_id, 3));
            } catch (\Throwable $e) {
                return back()->withErrors(['booking' => $e->getMessage()]);
            }
        }

        $payments->markRefunded($payment, $validated['reason'], auth()->id());

        return back()->with('status', $online
            ? 'Devolución enviada a Mercado Pago y registrada.'
            : 'Devolución registrada. Recuerda devolver el dinero al cliente por fuera del sistema.');
    }
}
