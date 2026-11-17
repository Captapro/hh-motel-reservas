<?php

namespace App\Http\Controllers;

use App\Exceptions\PricingException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Room;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reabre una reserva PAGADA que se canceló por error, en la pieza que se indique
 * (la misma u otra libre), con su horario y su pago intactos. Solo administrador.
 */
class BookingReopenController extends Controller
{
    public function store(Request $request, string $code, BookingService $bookingService): RedirectResponse
    {
        $validated = $request->validate(['room_id' => ['required', 'exists:rooms,id']]);
        $booking = Booking::where('code', $code)->firstOrFail();

        if ($booking->booking_status !== 'CANCELADA' || $booking->checked_in_at || $booking->paidAmount() <= 0) {
            return back()->withErrors(['booking' => 'Solo se puede reabrir una reserva cancelada que tenga un pago registrado y sin check-in.']);
        }

        $room = Room::findOrFail($validated['room_id']);

        try {
            DB::transaction(function () use ($booking, $room, $bookingService) {
                $old = $booking->only(['booking_status', 'room_id']);
                $booking->update(['booking_status' => 'CONFIRMADA']);
                $bookingService->reschedule($booking->fresh(), [
                    'room' => $room,
                    'starts_at' => $booking->starts_at,
                    'duration_minutes' => (int) $booking->duration_minutes,
                    'guests_count' => (int) $booking->guests_count,
                    'updated_by' => auth()->id(),
                ]);
                AuditLog::record(auth()->id(), 'reserva.reabrir', 'Booking', $booking->id, $old, $booking->fresh()->only(['booking_status', 'room_id']));
            });
        } catch (RoomNotAvailableException|PricingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Reserva reabierta en '.$room->name.' con su pago intacto.');
    }
}
