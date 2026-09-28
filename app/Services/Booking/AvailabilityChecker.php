<?php

namespace App\Services\Booking;

use App\Models\Room;
use Carbon\Carbon;

/**
 * Chequeo "de cortesía" para la UI — muestra al instante si probablemente hay
 * choque, antes de intentar crear la reserva. La garantía real contra doble
 * reserva es la restricción EXCLUDE de PostgreSQL en la tabla bookings, que
 * se dispara igual aunque este método diga que sí había disponibilidad.
 */
class AvailabilityChecker
{
    public function isAvailable(Room $room, Carbon $startsAt, Carbon $endsAt, ?int $excludeBookingId = null): bool
    {
        // El margen tiene que cuidar los dos lados: que la reserva nueva no
        // empiece muy pegada a que termine una existente, Y que no termine
        // muy pegada a que empiece una existente -- antes solo se restaba
        // el margen del lado de inicio, así que una reserva podía terminar
        // 10 minutos antes de que empezara la siguiente aunque el margen
        // configurado fuera de 30.
        $bufferStart = $startsAt->copy()->subMinutes($room->buffer_minutes);
        $bufferEnd = $endsAt->copy()->addMinutes($room->buffer_minutes);

        return ! $room->bookings()
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where('starts_at', '<', $bufferEnd)
            ->where('ends_at', '>', $bufferStart)
            ->exists();
    }
}
