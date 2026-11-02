<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidCouponException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Services\Booking\PaymentService;
use App\Services\Pricing\CouponValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Código de descuento que se olvidó al crear la reserva: se aplica después, mientras
 * la reserva siga abierta y no tenga ya una promoción. Valida igual que al reservar
 * (días, horario, vigencia, usos) con el horario de la reserva.
 */
class BookingCouponController extends Controller
{
    public function store(Request $request, string $code, CouponValidator $validator, PaymentService $payments): RedirectResponse
    {
        $booking = Booking::with(['room', 'customer'])->where('code', $code)->firstOrFail();
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:60']], ['coupon_code.required' => 'Escribe el código de descuento.']);
        $fail = fn (string $message) => back()->withErrors(['coupon_code' => $message])->withInput();

        if (in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true)) {
            return $fail('Esta reserva ya está cerrada.');
        }
        if ($booking->coupon_code_snapshot) {
            return $fail('Esta reserva ya tiene una promoción ('.$booking->coupon_code_snapshot.'). No se acumulan.');
        }

        $coupon = Coupon::where('auto_apply', false)->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($data['coupon_code']))])->first();
        if (! $coupon) {
            return $fail('El código de cupón no existe.');
        }
        if ($coupon->requires_verification && $booking->checked_in_at) {
            return $fail('Este cupón pide revisar un documento en el check-in, y la reserva ya hizo check-in.');
        }

        try {
            $discount = $validator->validate($coupon, $booking->room, $booking->starts_at->copy(), (int) $booking->duration_minutes, $booking->customer, (int) $booking->price_original, $booking->id);
        } catch (InvalidCouponException $e) {
            return $fail($e->getMessage());
        }

        DB::transaction(function () use ($booking, $coupon, $discount, $payments) {
            $old = $booking->only(['coupon_id', 'coupon_code_snapshot', 'discount_amount', 'price_final']);
            $booking->update([
                'coupon_id' => $coupon->id,
                'coupon_code_snapshot' => $coupon->label(),
                'discount_amount' => $discount,
                'price_final' => max(0, (int) $booking->price_original - $discount),
            ]);
            CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'discount_amount' => $discount,
                'redeemed_at' => now(),
            ]);
            $payments->recalculateStatus($booking->fresh());
            AuditLog::record(auth()->id(), 'cupon.aplicado_despues', 'Booking', $booking->id, $old, $booking->fresh()->only(['coupon_id', 'coupon_code_snapshot', 'discount_amount', 'price_final']));
        });

        return redirect()->route('reservations.show', $booking->code)
            ->with('status', 'Descuento '.$coupon->label().' aplicado: -$'.number_format($discount, 0, ',', '.').'.');
    }
}
