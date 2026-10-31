<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CouponProof;
use App\Models\CouponRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Respaldo de los cupones con verificación (ej. estudiante): en el check-in se
 * saca una foto del documento con el celular de recepción. Sin foto no hay
 * check-in con descuento — o se sube la foto o se quita el cupón y se cobra
 * la diferencia.
 */
class CouponProofs
{
    public function __construct(
        private readonly InspectionPhotoStore $images,
        private readonly PaymentService $payments,
    ) {}

    /** La reserva tiene un cupón que pide verificar y todavía no tiene foto. */
    public function missing(Booking $booking): bool
    {
        return $this->required($booking) && ! $this->latest($booking);
    }

    public function required(Booking $booking): bool
    {
        return (bool) $booking->coupon?->requires_verification;
    }

    public function latest(Booking $booking): ?CouponProof
    {
        return CouponProof::where('booking_id', $booking->id)->latest('id')->first(['id', 'booking_id', 'created_at', 'uploaded_by']);
    }

    public function store(Booking $booking, string $contents, ?int $userId): CouponProof
    {
        [$data, $mime] = $this->images->compress($contents);
        $proof = CouponProof::create([
            'booking_id' => $booking->id,
            'coupon_id' => $booking->coupon_id,
            'uploaded_by' => $userId,
            'mime' => $mime,
            'data' => base64_encode($data),
            'size' => strlen($data),
            'created_at' => now(),
        ]);
        AuditLog::record($userId, 'cupon.respaldo_foto', 'Booking', $booking->id, null, ['coupon' => $booking->coupon_code_snapshot, 'proof_id' => $proof->id]);

        return $proof;
    }

    /** No trae el documento: se quita el cupón y la reserva vuelve al precio sin descuento. */
    public function removeCoupon(Booking $booking, ?int $userId): void
    {
        DB::transaction(function () use ($booking, $userId) {
            $old = $booking->only(['coupon_id', 'coupon_code_snapshot', 'discount_amount', 'price_final']);
            $booking->update([
                'coupon_id' => null,
                'coupon_code_snapshot' => null,
                'discount_amount' => 0,
                'price_final' => $booking->price_original,
            ]);
            CouponRedemption::where('booking_id', $booking->id)->delete();
            $this->payments->recalculateStatus($booking->fresh());
            AuditLog::record($userId, 'cupon.quitado_sin_respaldo', 'Booking', $booking->id, $old, $booking->fresh()->only(['coupon_id', 'discount_amount', 'price_final']));
        });
    }

    /** Borra las fotos que pasaron RETENTION_DAYS días. Devuelve cuántas borró. */
    public function prune(): int
    {
        if (! Schema::hasTable('coupon_proofs')) {
            return 0;
        }

        return CouponProof::where('created_at', '<', now()->subDays(CouponProof::RETENTION_DAYS))->delete();
    }
}
