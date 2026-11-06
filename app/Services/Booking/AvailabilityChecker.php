<?php

namespace App\Services\Booking;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comprueba disponibilidad y margen de aseo. Las operaciones de reserva deben
 * bloquear la habitación dentro de su transacción antes de consultar este servicio.
 * La restricción EXCLUDE de PostgreSQL protege además contra superposiciones.
 */
class AvailabilityChecker
{
    /** Tiempo libre antes de una reserva que aún no llega: no siempre hay mucamas para un aseo rápido. */
    public const RESERVATION_GAP_MINUTES = 60;

    /** Al asignar sola una habitación, se evitan las que tienen una reserva dentro de estas horas. */
    public const RESERVED_SOON_HOURS = 12;

    public function hasGuestInside(Room $room, ?int $excludeBookingId = null): bool
    {
        return $room->bookings()
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereNotNull('checked_in_at')->whereNull('checked_out_at')->exists();
    }

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

        $tooCloseToNextReservation = $this->waitingReservations($room, $excludeBookingId)
            ->where('starts_at', '>=', $startsAt)
            ->where('starts_at', '<', $endsAt->copy()->addMinutes(self::RESERVATION_GAP_MINUTES))
            ->exists();
        if ($tooCloseToNextReservation) {
            return false;
        }

        return ! $room->bookings()
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            // Un ingreso anticipado ocupa desde la llegada real, aunque se
            // conserve starts_at como horario contratado para precios y atrasos.
            ->where(fn ($q) => $q->where('starts_at', '<', $bufferEnd)
                ->orWhere('checked_in_at', '<', $bufferEnd))
            ->where('ends_at', '>', $bufferStart)
            ->exists();
    }

    /** Si la habitación tiene una reserva por llegar poco después de esta hora. */
    public function hasReservationSoon(Room $room, Carbon $after): bool
    {
        return $this->waitingReservations($room)
            ->where('starts_at', '>=', $after)
            ->where('starts_at', '<', $after->copy()->addHours(self::RESERVED_SOON_HOURS))
            ->exists();
    }

    private function waitingReservations(Room $room, ?int $excludeBookingId = null): HasMany
    {
        return $room->bookings()
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereNull('checked_in_at');
    }
}
