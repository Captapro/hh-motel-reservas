<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Room;
use App\Services\Booking\AvailabilityChecker;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reabre una reserva PAGADA que se canceló por error, en la pieza que se indique
 * (la misma u otra libre), con su horario y su pago intactos. Solo administrador.
 */
class BookingReopenController extends Controller
{
    public function store(Request $request, string $code, AvailabilityChecker $availability): RedirectResponse
    {
        $validated = $request->validate(['room_id' => ['required', 'exists:rooms,id']]);
        $booking = Booking::where('code', $code)->firstOrFail();

        if ($booking->booking_status !== 'CANCELADA' || $booking->checked_in_at || $booking->paidAmount() <= 0) {
            return back()->withErrors(['booking' => 'Solo se puede reabrir una reserva cancelada que tenga un pago registrado y sin check-in.']);
        }

        $room = Room::findOrFail($validated['room_id']);

        if ((int) $room->room_category_id !== (int) $booking->room->room_category_id) {
            return back()->withErrors(['booking' => 'Reábrela en una pieza de la misma categoría para no cambiar el precio.']);
        }
        if (! $room->isOperational()) {
            return back()->withErrors(['booking' => "El Playroom {$room->name} no está operativo ahora mismo."]);
        }
        if (! $availability->isAvailable($room, $booking->starts_at, $booking->ends_at, $booking->id)) {
            return back()->withErrors(['booking' => "El Playroom {$room->name} no está disponible en el horario de esta reserva."]);
        }

        try {
            DB::transaction(function () use ($booking, $room) {
                $old = $booking->only(['booking_status', 'room_id']);
                // Estado y pieza en un solo paso: la base no admite dos reservas activas cruzadas en la misma pieza.
                $booking->update(['booking_status' => 'CONFIRMADA', 'room_id' => $room->id]);
                AuditLog::record(auth()->id(), 'reserva.reabrir', 'Booking', $booking->id, $old, $booking->fresh()->only(['booking_status', 'room_id']));
            });
        } catch (QueryException) {
            return back()->withErrors(['booking' => "El Playroom {$room->name} se cruza con otra reserva en ese horario."]);
        }

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Reserva reabierta en '.$room->name.' con su pago intacto.');
    }
}
