<?php

namespace App\Services\Pricing;

use App\Exceptions\PricingException;
use App\Models\RateRule;
use App\Models\Room;
use Carbon\Carbon;

class PriceCalculator
{
    public function __construct(private RateRuleResolver $resolver) {}

    /**
     * @return array{rate_rule: RateRule, price_original: int, extra_guests_fee: int}
     */
    public function calculate(Room $room, Carbon $startsAt, Carbon $endsAt, int $durationMinutes, int $guestsCount): array
    {
        $rateRule = $this->resolver->resolve($startsAt, $endsAt);

        if (! $rateRule) {
            throw new PricingException('El horario solicitado no corresponde a ninguna tarifa vigente.');
        }

        return $this->priceWith($rateRule, $room, $durationMinutes, $guestsCount);
    }

    /**
     * Precio de una tarifa ya elegida (sin exigir que cubra el rango
     * completo) -- lo usa la estadía corta por cierre.
     *
     * @return array{rate_rule: RateRule, price_original: int, extra_guests_fee: int}
     */
    public function priceWith(RateRule $rateRule, Room $room, int $durationMinutes, int $guestsCount): array
    {
        $price = $rateRule->prices()
            ->where('room_category_id', $room->room_category_id)
            ->where('duration_minutes', $durationMinutes)
            ->first();

        if (! $price) {
            throw new PricingException("No hay un precio configurado para {$room->category->name} a {$durationMinutes} minutos en tarifa {$rateRule->name}.");
        }

        $extraGuests = max(0, $guestsCount - $room->category->base_capacity);
        $extraGuestsFee = $extraGuests * $rateRule->extra_person_price;

        return [
            'rate_rule' => $rateRule,
            'price_original' => $price->price,
            'extra_guests_fee' => $extraGuestsFee,
        ];
    }
}
