<?php

namespace App\Services\Booking;

use App\Exceptions\RoomNotAvailableException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\OperationalSetting;
use App\Models\Room;
use App\Models\UpsellOffer;
use Illuminate\Support\Facades\DB;

class BookingAllocationService
{
    public function __construct(private AvailabilityChecker $availability, private ConsumptionService $consumption) {}

    /** El traslado y su cargo se confirman juntos, tras revalidar el destino bloqueado. */
    public function upgrade(Booking $booking, UpsellOffer $offer, ?int $userId): bool
    {
        return DB::transaction(function () use ($booking, $offer, $userId) {
            $rooms = Room::where(fn ($q) => $q->where('room_category_id', $offer->to_room_category_id)->orWhere('id', $booking->room_id))
                ->orderBy('id')->lockForUpdate()->get();
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $offer = $offer->fresh();
            if (! $offer || ! $offer->is_active || $offer->type !== 'category_upgrade'
                || (int) $offer->from_room_category_id !== (int) $locked->room->room_category_id
                || in_array($locked->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true)) {
                return false;
            }

            $occupancyStartsAt = $locked->checked_in_at && $locked->checked_in_at->lt($locked->starts_at)
                ? $locked->checked_in_at : $locked->starts_at;
            $target = $rooms->sortBy(fn (Room $room) => [$room->wingPriority(), $room->name])->values()->first(function (Room $room) use ($locked, $offer, $occupancyStartsAt) {
                return $room->id !== $locked->room_id
                    && (int) $room->room_category_id === (int) $offer->to_room_category_id
                    && $room->isOperational() && $room->category->is_active
                    && (! $locked->checked_in_at || ! $this->availability->hasGuestInside($room, $locked->id))
                    && $this->availability->isAvailable($room, $occupancyStartsAt, $locked->ends_at, $locked->id)
                    && $room->isFloorWingEnabled(OperationalSetting::current());
            });
            if (! $target) {
                return false;
            }
            $locked->update(['room_id' => $target->id]);
            if ($offer->price > 0) {
                $this->consumption->addCustom($locked, $offer->name, (int) $offer->price, $userId, $offer->id);
            }
            $booking->refresh();

            return true;
        }, 3);
    }

    /**
     * Cambio de pieza decidido por el motel: se mueve la reserva a otra pieza SIN recalcular el
     * precio base (lo contratado se conserva) y la diferencia, si la hay, queda como un concepto
     * propio ("Diferencia por cambio de pieza") para cobrarla con su propio voucher en la misma reserva.
     *
     * @throws RoomNotAvailableException si la reserva está cerrada o la pieza nueva no sirve.
     */
    public function changeRoom(Booking $booking, Room $target, int $difference, ?string $label, ?int $userId, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $target, $difference, $label, $userId, $reason) {
            Room::whereIn('id', [$booking->room_id, $target->id])->orderBy('id')->lockForUpdate()->get();
            $locked = Booking::with('room')->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $target = Room::with('category')->findOrFail($target->id);

            if (in_array($locked->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true)) {
                throw new RoomNotAvailableException('Esta reserva ya está cerrada: no se le puede cambiar la pieza.');
            }
            if ((int) $target->id === (int) $locked->room_id) {
                throw new RoomNotAvailableException('Elige una pieza distinta a la actual.');
            }
            $problem = $this->roomProblem($target, $locked);
            if ($problem) {
                throw new RoomNotAvailableException($problem);
            }

            $oldName = $locked->room->name;
            $locked->update(['room_id' => $target->id]);
            if ($difference > 0) {
                $this->consumption->addCustom($locked, $label ?: 'Diferencia por cambio de pieza: '.$oldName.' → '.$target->name, $difference, $userId);
            }
            AuditLog::record($userId, 'reserva.cambiar_pieza', 'Booking', $locked->id,
                ['room' => $oldName],
                ['room' => $target->name, 'difference' => $difference, 'reason' => $reason]);

            return $locked->fresh();
        }, 3);
    }

    /** Por qué una pieza no sirve como destino de esta reserva (null = sirve). */
    public function roomProblem(Room $room, Booking $booking): ?string
    {
        $room->loadMissing('category');
        if (! $room->isOperational() || ! $room->category->is_active) {
            return "La {$room->name} no está operativa ahora mismo.";
        }
        if (! $room->isFloorWingEnabled(OperationalSetting::current())) {
            return "La {$room->name} está en un ala deshabilitada.";
        }
        $max = (int) $room->category->max_capacity;
        if ($max > 0 && (int) $booking->guests_count > $max) {
            return "La {$room->name} admite como máximo {$max} personas.";
        }
        $from = $booking->checked_in_at && $booking->checked_in_at->lt($booking->starts_at) ? $booking->checked_in_at : $booking->starts_at;
        if (! $this->availability->isAvailable($room, $from, $booking->ends_at, $booking->id)) {
            return "La {$room->name} no está disponible en el horario de esta reserva.";
        }
        if ($booking->checked_in_at && $this->availability->hasGuestInside($room, $booking->id)) {
            return "La {$room->name} todavía tiene un huésped sin check-out.";
        }

        return null;
    }
}
