<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingAddon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Deshace un check-in marcado por error (por ejemplo, a la reserva de otra persona):
 * la reserva vuelve a "confirmada" con su horario contratado y sin acompañantes registrados.
 * Solo administrador. No se permite si ya salió, ni si ya se entregó consumo (descontó inventario).
 */
class BookingUndoCheckInController extends Controller
{
    public function store(string $code): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();

        if ($booking->booking_status !== 'CHECK_IN' || ! $booking->checked_in_at || $booking->checked_out_at) {
            return back()->withErrors(['booking' => 'Esta reserva no tiene un check-in vigente que deshacer.']);
        }
        if (BookingAddon::where('booking_id', $booking->id)->whereNotNull('stock_deducted_at')->exists()) {
            return back()->withErrors(['booking' => 'Ya se entregó consumo en esta reserva y bajó el inventario: no se puede deshacer el check-in.']);
        }

        DB::transaction(function () use ($booking) {
            $fields = ['booking_status', 'checked_in_at', 'ends_at'];
            $old = $booking->only($fields);
            $booking->update([
                'booking_status' => 'CONFIRMADA',
                'checked_in_at' => null,
                'ends_at' => $booking->starts_at->copy()->addMinutes((int) $booking->duration_minutes),
            ]);
            $booking->guests()->delete();
            AuditLog::record(auth()->id(), 'reserva.deshacer_check_in', 'Booking', $booking->id, $old, $booking->fresh()->only($fields));
        });

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Check-in deshecho: la reserva volvió a "confirmada".');
    }
}
