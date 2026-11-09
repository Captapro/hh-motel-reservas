<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corregir venta cerrada (solo administrador): cuando lo registrado no calza con lo que de verdad
 * se cobró (un descuento que se dio en recepción y no se cargó, un precio mal tomado), se deja el
 * precio real de la estadía y el monto real de cada pago. Así la venta, la caja y el cruce con
 * Mercado Pago quedan iguales a la máquina.
 *
 * Reglas: solo estadías FINALIZADAS; los pagos en efectivo no se tocan (moverían el efectivo del
 * mes); y solo se guarda si lo pagado queda exactamente igual a lo vendido (sin saldo ni sobrepago).
 * Siempre con motivo, y todo queda en la bitácora con el antes y el después.
 */
class BookingSaleCorrectionController extends Controller
{
    public function update(Request $request, string $code): RedirectResponse
    {
        $booking = Booking::with(['payments.paymentMethod', 'addons'])->where('code', $code)->firstOrFail();
        $v = $request->validate([
            'price_final' => ['required', 'integer', 'min:0'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'payments' => ['array'],
            'payments.*' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ], [
            'reason.required' => 'Escribe el motivo de la corrección.',
            'reason.min' => 'Escribe el motivo de la corrección.',
        ]);
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($booking->booking_status !== 'FINALIZADA') {
            $fail('price_final', 'Solo se corrigen ventas cerradas (estadía finalizada).');
        }
        $priceOriginal = (int) $booking->price_original;
        $priceFinal = (int) $v['price_final'];
        if ($priceFinal > $priceOriginal) {
            $fail('price_final', 'El precio real no puede ser mayor que el de la tarifa ($'.number_format($priceOriginal, 0, ',', '.').'). Si se cobró más, agrégalo como consumo.');
        }

        $approved = $booking->payments->where('status', 'aprobado')->keyBy('id');
        $newAmounts = [];
        foreach ($v['payments'] ?? [] as $id => $amount) {
            $payment = $approved->get((int) $id);
            if (! $payment || $amount === null || (int) $amount === (int) $payment->amount) {
                continue;
            }
            if ($payment->paymentMethod?->code === 'efectivo') {
                $fail('payments', 'Los pagos en efectivo no se corrigen aquí: cambiarían el efectivo del mes.');
            }
            $newAmounts[$payment->id] = (int) $amount;
        }

        $paidAfter = $approved->sum(fn ($p) => $newAmounts[$p->id] ?? (int) $p->amount);
        $soldAfter = $priceFinal + (int) $booking->addonsTotal();
        if ($paidAfter !== $soldAfter) {
            $diff = $paidAfter - $soldAfter;
            $fail('payments', 'Así quedaría '.($diff > 0 ? 'un sobrepago' : 'un saldo').' de $'.number_format(abs($diff), 0, ',', '.')
                .': lo pagado ($'.number_format($paidAfter, 0, ',', '.').') tiene que ser igual a lo vendido ($'.number_format($soldAfter, 0, ',', '.').').');
        }

        $coupon = filled($v['coupon_id'] ?? null) ? Coupon::find($v['coupon_id']) : null;
        $fields = ['price_final', 'discount_amount', 'coupon_id', 'coupon_code_snapshot', 'payment_status'];
        $before = $booking->only($fields) + ['pagos' => $approved->map->only(['id', 'amount', 'voucher_number'])->values()->all()];

        DB::transaction(function () use ($booking, $approved, $newAmounts, $priceOriginal, $priceFinal, $coupon, $v) {
            foreach ($newAmounts as $id => $amount) {
                $approved->get($id)->update(['amount' => $amount]);
            }
            $update = [
                'discount_amount' => $priceOriginal - $priceFinal,
                'price_final' => $priceFinal,
                'payment_status' => 'PAGADA',
            ];
            if ($coupon) {
                $update += ['coupon_id' => $coupon->id, 'coupon_code_snapshot' => $coupon->label()];
                CouponRedemption::firstOrCreate(
                    ['coupon_id' => $coupon->id, 'booking_id' => $booking->id],
                    ['customer_id' => $booking->customer_id, 'discount_amount' => $priceOriginal - $priceFinal, 'redeemed_at' => $booking->checked_in_at ?? $booking->starts_at],
                );
            }
            $booking->update($update);
            $booking->update(['notes' => trim(($booking->notes ? $booking->notes.' ' : '').'Venta corregida: '.$v['reason'])]);
        });

        $fresh = $booking->fresh('payments');
        AuditLog::record(auth()->id(), 'reserva.corregir_venta', 'Booking', $booking->id, $before,
            $fresh->only($fields) + ['pagos' => $fresh->payments->where('status', 'aprobado')->map->only(['id', 'amount', 'voucher_number'])->values()->all(), 'motivo' => $v['reason']]);

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Venta corregida. Lo pagado quedó igual a lo vendido.');
    }
}
