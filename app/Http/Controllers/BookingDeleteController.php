<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingDeleteController extends Controller
{
    /** Eliminar una reserva de prueba que nunca ocurrió (solo administrador). */
    public function destroy(Request $request, string $code): RedirectResponse
    {
        $booking = Booking::with(['payments', 'addons', 'customer', 'room'])->where('code', $code)->firstOrFail();

        if (! $booking->canBeDeleted()) {
            return back()->withErrors(['booking' => 'Solo se puede eliminar una reserva cancelada, vencida o no show que nunca tuvo check-in. Si es una reserva vigente, primero cancélala.']);
        }
        if (trim((string) $request->input('confirm_text')) !== 'ELIMINAR') {
            return back()->withErrors(['booking' => 'Para eliminar la reserva tienes que escribir ELIMINAR en el cuadro de confirmación.']);
        }

        $old = [
            'code' => $booking->code, 'estado' => $booking->booking_status, 'cliente' => $booking->customer?->name, 'habitacion' => $booking->room?->name,
            'inicio' => $booking->starts_at?->toDateTimeString(), 'precio' => $booking->price_final,
            'pagos' => $booking->payments->map(fn ($p) => ['monto' => $p->amount, 'estado' => $p->status, 'voucher' => $p->voucher_number, 'boleta' => $p->receipt_number])->all(),
            'consumos' => $booking->addons->map(fn ($a) => ['detalle' => $a->description, 'monto' => $a->amount])->all(),
        ];

        DB::transaction(function () use ($booking) {
            DB::table('payments')->where('booking_id', $booking->id)->delete();
            $booking->delete();
        });
        AuditLog::record(auth()->id(), 'reserva.eliminar', 'Booking', $booking->id, $old, null);

        return redirect()->route('rooms.board')->with('status', 'Reserva '.$old['code'].' eliminada.');
    }
}
