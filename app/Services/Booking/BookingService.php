<?php

namespace App\Services\Booking;

use App\Exceptions\PricingException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Room;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Pricing\CouponValidator;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PromotionResolver;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        private PriceCalculator $priceCalculator,
        private CouponValidator $couponValidator,
        private PromotionResolver $promotionResolver,
        private AvailabilityChecker $availabilityChecker,
        private BookingCodeGenerator $codeGenerator,
        private PaymentService $paymentService,
        private GhlBookingSync $ghlSync,
    ) {
    }

    /**
     * @param  array{
     *     room: Room, customer: Customer, starts_at: Carbon, duration_minutes: int,
     *     guests_count: int, coupon_code: ?string, deposit_amount: int, created_by: ?int,
     *     verified_by: ?int, notes: ?string,
     * }  $data
     */
    public function create(array $data): Booking
    {
        /** @var Room $room */
        $room = $data['room'];
        /** @var Customer $customer */
        $customer = $data['customer'];
        /** @var Carbon $startsAt */
        $startsAt = $data['starts_at'];
        $durationMinutes = $data['duration_minutes'];
        // El upsell "más tiempo" alarga la ocupación real (ends_at) sin tocar
        // la duración tarifada: la habitación queda bloqueada ese rato extra y
        // el cobro va como consumo con precio fijo (no reprecia la tarifa).
        $extraMinutes = (int) ($data['extra_minutes'] ?? 0);
        $baseEndsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes + $extraMinutes);

        // Tolerancia de 5 min por el tiempo que toma llenar el formulario —
        // más que eso ya es intentar cargar una reserva con hora pasada.
        if ($startsAt->lt(now()->subMinutes(5))) {
            throw new RoomNotAvailableException('No se puede crear una reserva con fecha y hora en el pasado.');
        }

        if (! $room->isOperational()) {
            throw new RoomNotAvailableException("La habitación {$room->name} no está operativa ahora mismo.");
        }

        if (! $this->availabilityChecker->isAvailable($room, $startsAt, $endsAt)) {
            throw new RoomNotAvailableException("La habitación {$room->name} ya no está disponible en ese horario.");
        }

        $pricing = $this->priceCalculator->calculate($room, $startsAt, $baseEndsAt, $durationMinutes, $data['guests_count']);
        $priceOriginal = $pricing['price_original'] + $pricing['extra_guests_fee'];

        $coupon = null;
        $discountAmount = 0;

        // Regla: la oferta programada gana. Un código promocional NO acumula
        // sobre una habitación en oferta — se ignora si hay oferta vigente.
        $offer = $this->promotionResolver->bestFor($room, $startsAt, $durationMinutes, $customer, $priceOriginal);

        if ($offer) {
            $coupon = $offer['coupon'];
            $discountAmount = $offer['discount'];
            $waived = min(max(0, (int) $data['guests_count'] - $room->category->base_capacity), (int) $coupon->included_extra_guests);
            $pricing['extra_guests_fee'] = max(0, $pricing['extra_guests_fee'] - ($waived * $pricing['rate_rule']->extra_person_price));
            $priceOriginal = $pricing['price_original'] + $pricing['extra_guests_fee'];
        } elseif (! empty($data['coupon_code'])) {
            $coupon = Coupon::where('auto_apply', false)
                ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($data['coupon_code']))])
                ->first();
            if (! $coupon) {
                throw new PricingException('El código de cupón no existe.');
            }
            $discountAmount = $this->couponValidator->validate($coupon, $room, $startsAt, $durationMinutes, $customer, $priceOriginal);
        }

        $priceFinal = max(0, $priceOriginal - $discountAmount);

        // El código sale de un COUNT + insert, no es atómico: dos reservas
        // creadas al mismo tiempo pueden leer el mismo contador y chocar
        // contra el UNIQUE de bookings.code. Reintenta con un código nuevo
        // en vez de dejar caer la reserva con un 500.
        $attemptsLeft = 5;
        while (true) {
            try {
                $booking = DB::transaction(function () use ($room, $customer, $startsAt, $endsAt, $durationMinutes, $data, $pricing, $coupon, $discountAmount, $priceOriginal, $priceFinal) {
                    $booking = Booking::create([
                        'code' => $this->codeGenerator->generate($startsAt),
                        'pass_token' => Str::random(40),
                        'customer_id' => $customer->id,
                        'room_id' => $room->id,
                        'created_by' => $data['created_by'] ?? null,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'duration_minutes' => $durationMinutes,
                        'guests_count' => $data['guests_count'],
                        'booking_status' => 'PENDIENTE_PAGO',
                        'payment_status' => 'NO_PAGADA',
                        'rate_rule_id' => $pricing['rate_rule']->id,
                        'rate_rule_name_snapshot' => $pricing['rate_rule']->name,
                        'price_original' => $priceOriginal,
                        'extra_guests_fee' => $pricing['extra_guests_fee'],
                        'coupon_id' => $coupon?->id,
                        'coupon_code_snapshot' => $coupon?->label(),
                        'discount_amount' => $discountAmount,
                        'price_final' => $priceFinal,
                        'deposit_amount' => $data['deposit_amount'] ?? 0,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    foreach ($data['guest_names'] ?? [] as $guestName) {
                        if (trim($guestName) !== '') {
                            BookingGuest::create(['booking_id' => $booking->id, 'name' => trim($guestName)]);
                        }
                    }

                    if ($coupon) {
                        CouponRedemption::create([
                            'coupon_id' => $coupon->id,
                            'booking_id' => $booking->id,
                            'customer_id' => $customer->id,
                            'discount_amount' => $discountAmount,
                            'verified_by' => $coupon->requires_verification ? ($data['verified_by'] ?? null) : null,
                            'redeemed_at' => now(),
                        ]);
                    }

                    AuditLog::record($data['created_by'] ?? null, 'reserva.crear', 'Booking', $booking->id, null, $booking->toArray());

                    return $booking;
                });

                // Fuera de la transacción a propósito: si GHL falla o está lento,
                // la reserva ya quedó confirmada igual -- nunca debe poder
                // tumbarla ni demorarla. GhlBookingSync nunca lanza excepción.
                $this->ghlSync->sync($booking);

                return $booking;
            } catch (QueryException $e) {
                // SQLSTATE 23P01 = exclusion_violation (choque con bookings_no_overlap)
                if ($e->getCode() === '23P01' || str_contains($e->getMessage(), 'bookings_no_overlap')) {
                    throw new RoomNotAvailableException("La habitación {$room->name} acaba de ser reservada por otra persona en ese horario.");
                }
                // SQLSTATE 23505 = unique_violation -- dos reservas leyeron el
                // mismo contador de código en el mismo instante.
                if (($e->getCode() === '23505' || str_contains($e->getMessage(), 'bookings_code_unique')) && $attemptsLeft > 0) {
                    $attemptsLeft--;

                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Reprograma una reserva ya creada: cambia habitación/fecha/hora/duración/personas
     * y recalcula el precio con el motor de tarifas vigente para el nuevo horario.
     * El descuento de cupón (si tenía) se conserva como monto fijo, no se revalida.
     * Sigue funcionando después del check-in real — por ejemplo cuando el
     * huésped llega antes de lo reservado y hay que correr la hora de inicio
     * para que coincida con la llegada real.
     *
     * @param  array{room: Room, starts_at: Carbon, duration_minutes: int, guests_count: int, updated_by: ?int}  $data
     */
    public function reschedule(Booking $booking, array $data): Booking
    {
        if (in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true)) {
            throw new RoomNotAvailableException('Esta reserva ya está cerrada — no se puede modificar.');
        }

        /** @var Room $room */
        $room = $data['room'];
        /** @var Carbon $startsAt */
        $startsAt = $data['starts_at'];
        $durationMinutes = $data['duration_minutes'];
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        // Si ya hay check-in real, se permite correr el inicio hacia el pasado
        // reciente para que coincida con la llegada real del huésped — no es
        // un error de tipeo, es una corrección deliberada.
        if (! $booking->checked_in_at && $startsAt->lt(now()->subMinutes(5))) {
            throw new RoomNotAvailableException('No se puede reprogramar una reserva a una fecha y hora en el pasado.');
        }

        if (! $room->isOperational()) {
            throw new RoomNotAvailableException("La habitación {$room->name} no está operativa ahora mismo.");
        }

        if (! $this->availabilityChecker->isAvailable($room, $startsAt, $endsAt, $booking->id)) {
            throw new RoomNotAvailableException("La habitación {$room->name} no está disponible en ese horario.");
        }

        $pricing = $this->priceCalculator->calculate($room, $startsAt, $endsAt, $durationMinutes, $data['guests_count']);
        $priceOriginal = $pricing['price_original'] + $pricing['extra_guests_fee'];

        // El descuento de un cupón CON código se conserva fijo (ya se validó al
        // crear). El de una OFERTA automática se recalcula para el nuevo
        // contexto — puede aparecer, cambiar o dejar de aplicar.
        $existingCoupon = $booking->coupon_id ? Coupon::find($booking->coupon_id) : null;
        $couponId = $booking->coupon_id;
        $couponSnapshot = $booking->coupon_code_snapshot;
        $discountAmount = $booking->discount_amount;
        $reResolveOffer = ($existingCoupon === null || $existingCoupon->auto_apply);

        if ($reResolveOffer) {
            $offer = $this->promotionResolver->bestFor($room, $startsAt, $durationMinutes, $booking->customer, $priceOriginal);
            if ($offer) {
                $couponId = $offer['coupon']->id;
                $couponSnapshot = $offer['coupon']->label();
                $discountAmount = $offer['discount'];
                $waived = min(max(0, (int) $data['guests_count'] - $room->category->base_capacity), (int) $offer['coupon']->included_extra_guests);
                $pricing['extra_guests_fee'] = max(0, $pricing['extra_guests_fee'] - ($waived * $pricing['rate_rule']->extra_person_price));
                $priceOriginal = $pricing['price_original'] + $pricing['extra_guests_fee'];
            } else {
                $couponId = null;
                $couponSnapshot = null;
                $discountAmount = 0;
            }
        }

        $priceFinal = max(0, $priceOriginal - $discountAmount);

        try {
            return DB::transaction(function () use ($booking, $room, $startsAt, $endsAt, $durationMinutes, $data, $pricing, $priceOriginal, $priceFinal, $couponId, $couponSnapshot, $discountAmount, $reResolveOffer) {
                $old = $booking->toArray();

                $booking->update([
                    'room_id' => $room->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $durationMinutes,
                    'guests_count' => $data['guests_count'],
                    'rate_rule_id' => $pricing['rate_rule']->id,
                    'rate_rule_name_snapshot' => $pricing['rate_rule']->name,
                    'price_original' => $priceOriginal,
                    'extra_guests_fee' => $pricing['extra_guests_fee'],
                    'coupon_id' => $couponId,
                    'coupon_code_snapshot' => $couponSnapshot,
                    'discount_amount' => $discountAmount,
                    'price_final' => $priceFinal,
                ]);

                // Solo re-sincroniza la redención cuando la oferta automática
                // se recalculó. Un cupón con código conserva su redención
                // original (incluido el verified_by).
                if ($reResolveOffer) {
                    CouponRedemption::where('booking_id', $booking->id)->delete();
                    if ($couponId) {
                        CouponRedemption::create([
                            'coupon_id' => $couponId,
                            'booking_id' => $booking->id,
                            'customer_id' => $booking->customer_id,
                            'discount_amount' => $discountAmount,
                            'redeemed_at' => now(),
                        ]);
                    }
                }

                // Reprogramar puede subir o bajar price_final — el estado de
                // pago (PAGADA/PARCIALMENTE_PAGADA/NO_PAGADA) se recalcula
                // siempre contra el monto ya cobrado, para que no quede
                // mostrando "PAGADA" con saldo pendiente real.
                $this->paymentService->recalculateStatus($booking->fresh());

                AuditLog::record($data['updated_by'] ?? null, 'reserva.modificar', 'Booking', $booking->id, $old, $booking->fresh()->toArray());

                return $booking->fresh();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01' || str_contains($e->getMessage(), 'bookings_no_overlap')) {
                throw new RoomNotAvailableException("La habitación {$room->name} acaba de ser reservada por otra persona en ese horario.");
            }
            throw $e;
        }
    }
}
