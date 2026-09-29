<?php

namespace App\Services\Booking;

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

            $target = $rooms->first(function (Room $room) use ($locked, $offer) {
                return $room->id !== $locked->room_id
                    && (int) $room->room_category_id === (int) $offer->to_room_category_id
                    && $room->isOperational() && $room->category->is_active
                    && ! $this->availability->hasGuestInside($room, $locked->id)
                    && $this->availability->isAvailable($room, $locked->starts_at, $locked->ends_at, $locked->id)
                    && $room->isFloorWingEnabled(OperationalSetting::current());
            });
            if (! $target) {
                return false;
            }
            $locked->update(['room_id' => $target->id]);
            if ($offer->price > 0) {
                $this->consumption->addCustom($locked, $offer->name, (int) $offer->price, $userId);
            }
            $booking->refresh();

            return true;
        }, 3);
    }
}
