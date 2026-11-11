<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\Integrations\GhlReservationNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingCancelController extends Controller
{
    public function store(Request $request, string $code): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'notify_customer' => ['nullable', 'boolean'],
        ]);
        $notify = (bool) ($validated['notify_customer'] ?? false);

        return DB::transaction(function () use ($code, $validated, $notify) {
            $booking = Booking::where('code', $code)->lockForUpdate()->firstOrFail();

            if (in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true)) {
                return redirect()->route('reservations.show', $booking->code)
                    ->withErrors(['booking' => 'Esta reserva ya está cerrada — no se puede cancelar.']);
            }

            // Cancelar es "esto no pasó" -- pone la reserva en un estado
            // terminal, así que RoomBoardService deja de contarla como
            // ocupación real y la pieza vuelve a verse libre de inmediato,
            // aunque el huésped siga físicamente adentro y operational_status
            // nunca pasó a "aseo". Con check-in ya hecho hay que salir por la
            // puerta de checkout (finalizar), no por cancelar.
            if ($booking->checked_in_at) {
                return redirect()->route('reservations.show', $booking->code)
                    ->withErrors(['booking' => 'Esta reserva ya tiene check-in -- no se puede cancelar. Usa "Finalizar" para hacerle el check-out.']);
            }

            $old = $booking->only(['booking_status']);
            $booking->update([
                'booking_status' => 'CANCELADA',
                'notes' => trim(($booking->notes ? $booking->notes."\n" : '').'Cancelada: '.(($validated['reason'] ?? null) ?: 'sin motivo indicado')),
            ]);

            AuditLog::record(auth()->id(), 'reserva.cancelar', 'Booking', $booking->id, $old, $booking->fresh()->only(['booking_status', 'notes']));

            // Aviso al cliente por WhatsApp (vía GHL) solo si recepción lo marcó; después de guardar.
            if ($notify) {
                DB::afterCommit(fn () => app(GhlReservationNotifier::class)->cancelled($booking->fresh()));
            }

            $paid = $booking->paidAmount();

            return redirect()->route('reservations.show', $booking->code)->with('status', 'Reserva cancelada.'.($notify ? ' Se avisó al cliente por WhatsApp.' : '').($paid > 0 ? ' Tiene $'.number_format($paid, 0, ',', '.').' pagados: si corresponde devolverlos, usa "Devolver este pago" en Pagos registrados.' : ''));
        }, 3);
    }
}
