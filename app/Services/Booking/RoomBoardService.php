<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\RateRulePrice;
use App\Models\Room;
use Carbon\Carbon;

/**
 * Dos ejes independientes por habitación: estado operativo (¿se puede usar
 * hoy? — lo cambia una persona a mano) y ocupación (¿hay alguien ahora
 * mismo? — se calcula sola). La ocupación se basa en el check-in REAL, no en
 * el horario agendado: una reserva cuya hora ya empezó pero que nadie marcó
 * como check-in sigue contando como "libre" (aparece en "Por llegar", no en
 * "Disponibles" ni en "Ocupadas") hasta que recepción confirme la llegada.
 * Ver sección 10 del documento de arquitectura.
 */
class RoomBoardService
{
    private const INACTIVE_STATUSES = ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'];

    /** @var array<int, int> */
    private array $minDurationCache = [];

    /**
     * Duración mínima reservable (en minutos) para la categoría de la habitación,
     * según las tarifas cargadas. Se usa para saber si a un walk-in ya no le
     * alcanzaría el hueco antes de la próxima reserva.
     */
    private function minDurationFor(int $roomCategoryId): int
    {
        return $this->minDurationCache[$roomCategoryId] ??= (int) (
            RateRulePrice::where('room_category_id', $roomCategoryId)->min('duration_minutes') ?? 60
        );
    }

    /**
     * @return array{occupancy: string, eta_minutes: ?int, current_booking: ?Booking, next_booking: ?Booking, upcoming_count: int, imminent: bool, minutes_until_next: ?int}
     */
    public function statusFor(Room $room, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        // Cuenta las reservas vigentes — para saber, sin entrar,
        // si vale la pena revisar el listado de esta habitación o está vacío.
        $upcomingCount = $room->bookings()
            ->whereNotIn('booking_status', self::INACTIVE_STATUSES)
            ->where('ends_at', '>', $now)
            ->count();

        // 1) Check-in real hecho y sin check-out todavía: ocupada de verdad,
        //    aunque ya se haya pasado la hora programada de salida.
        $checkedIn = $room->bookings()
            ->whereNotIn('booking_status', self::INACTIVE_STATUSES)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->orderBy('checked_in_at')
            ->first();

        if ($checkedIn) {
            // La estadía en curso ya no es "próxima": solo cuentan las otras.
            $upcomingCount = $room->bookings()
                ->whereNotIn('booking_status', self::INACTIVE_STATUSES)
                ->where('ends_at', '>', $now)
                ->where('id', '!=', $checkedIn->id)
                ->count();

            return [
                'occupancy' => 'ocupada',
                'eta_minutes' => (int) $now->diffInMinutes($checkedIn->ends_at, false),
                'current_booking' => $checkedIn,
                'next_booking' => null,
                'upcoming_count' => $upcomingCount,
                'imminent' => false,
                'minutes_until_next' => null,
            ];
        }

        // El aseo ya NO se calcula acá: al hacer el check-out la habitación
        // pasa a operational_status = 'aseo' (persistente) y queda fuera de
        // disponibles hasta que recepción la marque como activa a mano. Ver
        // BookingFinalizeController y RoomBoardController::markAseoReady.

        // 2) Sin check-in real todavía — ya sea que la hora agendada todavía
        //    no llega, o que ya llegó y nadie confirmó la llegada. En los dos
        //    casos la pieza sigue "libre" para el tablero (no hay nadie
        //    confirmado adentro): la diferencia es que acá aparece en "Por
        //    llegar" en vez de en "Disponibles", para no ofrecerla a un
        //    walk-in ni perderla de vista.
        $next = $room->bookings()
            ->whereNotIn('booking_status', self::INACTIVE_STATUSES)
            ->whereNull('checked_out_at')
            ->where('ends_at', '>', $now)
            ->orderBy('starts_at')
            ->first();

        $minutesUntilNext = null;
        $imminent = false;

        if ($next) {
            $minutesUntilNext = (int) $now->diffInMinutes($next->starts_at, false);
            if ($next->starts_at->lte($now)) {
                // Ya debería haber llegado -- siempre a la vista en "Por
                // llegar", sin depender del umbral de duración mínima.
                $imminent = true;
            } else {
                $threshold = $this->minDurationFor($room->room_category_id) + $room->buffer_minutes;
                $imminent = $minutesUntilNext <= $threshold;
            }
        }

        return [
            'occupancy' => 'libre',
            'eta_minutes' => null,
            'current_booking' => null,
            'next_booking' => $next,
            'upcoming_count' => $upcomingCount,
            'imminent' => $imminent,
            'minutes_until_next' => $minutesUntilNext,
        ];
    }

    /**
     * @return array<int, array{room: Room, status: array}>
     */
    public function board(): array
    {
        return Room::with(['category', 'latestInspection', 'furniture'])
            ->get()
            // Orden por categoría (GO, LITE, PLUS, MAX vía display_order) y luego por nombre —
            // no alfabético puro, porque "MAX" quedaría antes que "PLUS".
            ->sortBy(fn (Room $room) => sprintf('%03d-%s', $room->category->display_order, $room->name))
            ->values()
            ->map(fn (Room $room) => ['room' => $room, 'status' => $this->statusFor($room)])
            ->all();
    }
}
