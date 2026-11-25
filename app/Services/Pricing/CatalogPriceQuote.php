<?php

namespace App\Services\Pricing;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Room;
use App\Support\CouponCode;
use Carbon\Carbon;

/** Vista previa sin crear reservas, consumir cupones ni reservar stock. */
class CatalogPriceQuote
{
    public function __construct(
        private PriceCalculator $calculator,
        private PromotionResolver $promotions,
        private CouponValidator $coupons,
    ) {}

    public function forStay(Room $room, Carbon $startsAt, int $duration, int $guests, ?array $short, ?string $code): array
    {
        $pricing = $short
            ? $this->calculator->priceWith($short['rate_rule'], $room, $duration, $guests)
            : $this->calculator->calculate($room, $startsAt, $startsAt->copy()->addMinutes($duration), $duration, $guests);
        $original = (int) ($pricing['price_original'] + $pricing['extra_guests_fee']);
        $discount = 0;
        $label = null;
        $note = null;
        $conditional = false;
        $offer = $short ? null : $this->promotions->bestFor($room, $startsAt, $duration, null, $original, $guests, (int) $pricing['rate_rule']->extra_person_price);

        // Mismo orden que BookingService: cierre, oferta automática, código.
        if ($short) {
            $discount = max(0, $short['full_price'] - $short['price']);
            $label = ShortStayPricer::LABEL;
        } elseif ($offer) {
            $original = (int) $offer['price_original'];
            $discount = (int) $offer['discount'];
            $label = $offer['coupon']->label();
            $conditional = $this->needsCustomer($offer['coupon']);
            $note = $conditional ? 'La oferta se validará con tus datos al confirmar.' : null;
            if ($code) {
                $note = 'La oferta vigente no se acumula con el cupón.';
                if ($conditional) {
                    $note .= ' Se validará con tus datos al confirmar.';
                }
            }
        } elseif ($code) {
            $coupon = CouponCode::find(Coupon::where('auto_apply', false), $code);
            $conditional = true;
            if (! $coupon) {
                $note = 'El cupón no existe. Revisa el código antes de continuar.';
            } elseif ($this->needsCustomer($coupon)) {
                // No adivinar identidad, edad o usos previos en un endpoint público.
                $note = 'Precio sin descuento personal. El cupón se validará con tus datos al confirmar.';
            } else {
                try {
                    $discount = $this->coupons->validate($coupon, $room, $startsAt, $duration, null, $original);
                    $label = $coupon->label();
                    $conditional = false;
                } catch (InvalidCouponException $e) {
                    $note = $e->getMessage();
                }
            }
        }

        return [
            'currency' => 'CLP',
            'original' => $original,
            'discount' => $discount,
            'stay_total' => max(0, $original - $discount),
            'discount_label' => $label,
            'conditional' => $conditional,
            'note' => $note,
        ];
    }

    private function needsCustomer(Coupon $coupon): bool
    {
        return (bool) ($coupon->customer_id || $coupon->min_age || $coupon->requires_verification || $coupon->max_uses_per_customer !== null
            || $coupon->origin === CouponValidator::FIRST_VISIT_ORIGIN);
    }
}
