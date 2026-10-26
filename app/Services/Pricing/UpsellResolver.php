<?php

namespace App\Services\Pricing;

use App\Models\Room;
use App\Models\UpsellOffer;
use App\Services\Booking\AvailabilityChecker;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Qué upsells se le pueden ofrecer a una reserva concreta (habitación,
 * duración, horario), ya chequeada la factibilidad: para subir de categoría
 * tiene que haber una habitación libre de la categoría destino; para sumar
 * tiempo, la habitación tiene que estar libre ese rato extra.
 */
class UpsellResolver
{
    public function __construct(private AvailabilityChecker $availability, private RateRuleResolver $rates) {}

    /**
     * $endsAt: salida real si no es inicio + duración (estadía corta por cierre).
     *
     * @return Collection<int, array{offer: UpsellOffer, target_room_id: ?int, label: string, price: int, detail: string}>
     */
    public function applicableFor(Room $room, int $durationMinutes, Carbon $startsAt, ?int $excludeBookingId = null, ?Carbon $endsAt = null): Collection
    {
        $endsAt ??= $startsAt->copy()->addMinutes($durationMinutes);

        return UpsellOffer::active()
            ->with(['fromCategory', 'toCategory', 'combo'])
            ->orderBy('display_order')
            ->get()
            ->map(fn (UpsellOffer $o) => $this->evaluate($o, $room, $durationMinutes, $startsAt, $endsAt, $excludeBookingId))
            ->filter()
            ->values();
    }

    /**
     * @return array{offer: UpsellOffer, target_room_id: ?int, label: string, price: int, detail: string}|null
     */
    private function evaluate(UpsellOffer $o, Room $room, int $durationMinutes, Carbon $startsAt, Carbon $endsAt, ?int $excludeBookingId): ?array
    {
        if ($o->type === 'combo') {
            if (! $o->combo || ! $o->combo->is_active || $o->combo->isOutOfStock()) {
                return null;
            }

            return [
                'offer' => $o,
                'target_room_id' => null,
                'label' => $o->name,
                'price' => (int) $o->combo->price,
                'detail' => $o->combo->description ?: 'Pack',
            ];
        }

        if ($o->type === 'time_extension') {
            if (! $o->extra_minutes) {
                return null;
            }
            $extendedEnd = $endsAt->copy()->addMinutes($o->extra_minutes);
            // Más tiempo que pasa la hora de cierre no se puede vender.
            if (! $this->rates->resolve($startsAt, $extendedEnd)) {
                return null;
            }
            if (! $this->availability->isAvailable($room, $startsAt, $extendedEnd, $excludeBookingId)) {
                return null;
            }

            return [
                'offer' => $o,
                'target_room_id' => null,
                'label' => $o->name,
                'price' => (int) $o->price,
                'detail' => '+'.($o->extra_minutes / 60).' h · quedas hasta '.$extendedEnd->timezone('America/Santiago')->format('H:i'),
            ];
        }

        // category_upgrade
        if ($o->from_room_category_id !== $room->room_category_id || ! $o->to_room_category_id) {
            return null;
        }

        $targetRoom = Room::where('room_category_id', $o->to_room_category_id)
            ->where('operational_status', 'activa')
            ->where('id', '!=', $room->id)
            ->orderBy('name')
            ->get()
            ->first(fn (Room $r) => $this->availability->isAvailable($r, $startsAt, $endsAt, $excludeBookingId));

        if (! $targetRoom) {
            return null;
        }

        return [
            'offer' => $o,
            'target_room_id' => $targetRoom->id,
            'label' => $o->name,
            'price' => (int) $o->price,
            'detail' => 'Pasas a '.$targetRoom->name.' ('.$o->toCategory?->name.')',
        ];
    }
}
