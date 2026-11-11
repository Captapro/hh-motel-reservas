<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Corrección del administrador: la hora real de entrada y salida de una
 * estadía que recepción registró tarde o no alcanzó a cerrar. La reserva queda
 * finalizada con esas horas, sin tocar el estado del Playroom y sin pedir
 * opinión por WhatsApp (el cliente ya se fue hace rato).
 */
class BookingRealTimesController extends Controller
{
    private const TZ = 'America/Santiago';

    public function update(Request $request, string $code): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();
        $v = $request->validate([
            'checked_in_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'checked_out_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        if (in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW'], true)) {
            throw ValidationException::withMessages(['checked_in_at' => 'Esta reserva no tuvo estadía: no tiene horas que corregir.']);
        }
        if ($booking->balanceDue() > 0) {
            throw ValidationException::withMessages(['checked_in_at' => 'Tiene saldo pendiente: registra el pago antes de cerrarla.']);
        }

        // Eloquent guarda la hora tal cual viene, sin convertir: se pasa a la zona de la app.
        $in = Carbon::parse($v['checked_in_at'], self::TZ)->setTimezone(config('app.timezone'));
        $out = Carbon::parse($v['checked_out_at'], self::TZ)->setTimezone(config('app.timezone'));
        if ($out->lte($in)) {
            throw ValidationException::withMessages(['checked_out_at' => 'La salida tiene que ser después de la entrada.']);
        }
        if ($out->gt(now())) {
            throw ValidationException::withMessages(['checked_out_at' => 'La salida todavía no pasa.']);
        }

        // Sin check-in, el horario anotado nunca fue real: se mueve a la entrada
        // real con la misma duración. Con check-in se respeta el contratado.
        $startsAt = $booking->checked_in_at ? $booking->starts_at : $in;
        $bookedEnds = $booking->checked_in_at
            ? $booking->bookedEndsAt()
            : $in->copy()->addMinutes((int) $booking->starts_at->diffInMinutes($booking->bookedEndsAt()));
        $endsAt = $out->lt($bookedEnds) ? $out : $bookedEnds;
        if ($endsAt->lt($startsAt)) {
            $startsAt = $in->min($endsAt);
        }

        $clash = Booking::where('room_id', $booking->room_id)->where('id', '!=', $booking->id)
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->first();
        if ($clash) {
            throw ValidationException::withMessages(['checked_in_at' => "En ese horario el Playroom ya tiene la reserva {$clash->code}."]);
        }

        $fields = ['booking_status', 'starts_at', 'ends_at', 'booked_ends_at', 'checked_in_at', 'checked_out_at'];
        $old = $booking->only($fields);
        $booking->update([
            'booking_status' => 'FINALIZADA',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'booked_ends_at' => $bookedEnds,
            'checked_in_at' => $in,
            'checked_out_at' => $out,
            'consumption_offered_at' => $booking->consumption_offered_at ?? now(),
            'consumption_offered_by' => $booking->consumption_offered_by ?? auth()->id(),
            'review_requested_at' => $booking->review_requested_at ?? now(),
            // La salida se cargó después del hecho: no corresponde dejarla como aseo pendiente.
            'checkout_regularized' => true,
        ]);
        AuditLog::record(auth()->id(), 'reserva.corregir_horas', 'Booking', $booking->id, $old, $booking->fresh()->only($fields));

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Horas reales corregidas. El estado del Playroom no se tocó.');
    }
}
