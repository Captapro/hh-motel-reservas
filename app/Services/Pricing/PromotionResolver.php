<?php

namespace App\Services\Pricing;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ofertas programadas: busca el mejor cupón con auto_apply que aplique a una
 * habitación en una fecha/hora/duración dada y lo devuelve ya evaluado. No
 * tira excepciones — una oferta que no aplica simplemente no se ofrece.
 */
class PromotionResolver
{
    public function __construct(private CouponValidator $validator) {}

    /**
     * @return array{coupon: Coupon, discount: int, waived: int, price_original: int}|null
     */
    public function bestFor(Room $room, Carbon $startsAt, int $durationMinutes, ?Customer $customer, int $priceOriginal, ?int $guestsCount = null, int $extraPersonPrice = 0, ?int $excludeBookingId = null): ?array
    {
        $candidates = Coupon::query()
            ->offers()
            ->where('is_active', true)
            ->with(['rooms:id', 'roomCategories:id'])
            ->orderBy('id')
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->get();

        $best = null;

        foreach ($candidates as $coupon) {
            $waived = min(max(0, ($guestsCount ?? $room->category->base_capacity) - $room->category->base_capacity), (int) $coupon->included_extra_guests) * $extraPersonPrice;
            $adjustedPrice = max(0, $priceOriginal - $waived);
            try {
                $discount = $this->validator->validate($coupon, $room, $startsAt, $durationMinutes, $customer, $adjustedPrice, $excludeBookingId);
            } catch (InvalidCouponException) {
                continue;
            }

            if (($discount > 0 || $waived > 0) && ($best === null || $discount + $waived > $best['discount'] + $best['waived'])) {
                $best = ['coupon' => $coupon, 'discount' => $discount, 'waived' => $waived, 'price_original' => $adjustedPrice];
            }
        }

        return $best;
    }
}
