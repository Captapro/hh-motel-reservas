<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingHoldController extends Controller
{
    private const REASONS = [
        'recepcion' => 'Pagará en recepción',
        'tardio' => 'Pago tardío (transferencia por confirmar)',
    ];

    /**
     * Recepción deja una reserva pendiente sin vencimiento porque el pago se hará
     * más tarde o en el local: ya no la libera el límite de 10 minutos.
     */
    public function store(Request $request, string $code): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'in:'.implode(',', array_keys(self::REASONS))]]);

        $booking = Booking::where('code', $code)->firstOrFail();
        if ($booking->booking_status !== 'PENDIENTE_PAGO') {
            return back()->withErrors(['booking' => 'Esta reserva ya no está pendiente de pago.']);
        }

        $label = self::REASONS[$validated['reason']];
        $old = ['expires_at' => $booking->expires_at?->toDateTimeString()];
        $booking->update([
            'expires_at' => null,
            'notes' => trim(($booking->notes ? $booking->notes."\n" : '').'Retención mantenida por recepción: '.$label.'.'),
        ]);
        AuditLog::record(auth()->id(), 'reserva.mantener_retencion', 'Booking', $booking->id, $old, ['expires_at' => null, 'motivo' => $label]);

        return back()->with('status', 'Reserva mantenida sin vencimiento — '.$label.'.');
    }
}
