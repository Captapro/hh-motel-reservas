<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'pass_token', 'customer_id', 'room_id', 'created_by', 'starts_at', 'ends_at', 'duration_minutes', 'guests_count',
    'booking_status', 'payment_status',
    'rate_rule_id', 'rate_rule_name_snapshot', 'price_original', 'extra_guests_fee',
    'coupon_id', 'coupon_code_snapshot', 'discount_amount', 'price_final', 'deposit_amount',
    'notes', 'expires_at', 'consumption_offered_at', 'consumption_offered_by',
    'checked_in_at', 'checked_out_at', 'payment_instructions_sent_at',
    'room_checked_at', 'room_checked_by',
])]
class Booking extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumption_offered_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'payment_instructions_sent_at' => 'datetime',
            'room_checked_at' => 'datetime',
        ];
    }

    /**
     * Minutos de atraso respecto a la hora agendada — negativo si llegó antes.
     * Null si todavía no se marca el check-in.
     */
    public function checkInDelayMinutes(): ?int
    {
        if (! $this->checked_in_at) {
            return null;
        }

        return (int) $this->starts_at->diffInMinutes($this->checked_in_at, false);
    }

    public function consumptionOfferedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumption_offered_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rateRule(): BelongsTo
    {
        return $this->belongsTo(RateRule::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(BookingGuest::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(GuestReview::class);
    }

    public function couponRedemption(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Suma de pagos aprobados — nunca se resta el saldo directo, siempre se recalcula. */
    public function paidAmount(): int
    {
        return (int) $this->payments()->where('status', 'aprobado')->sum('amount');
    }

    /** Consumo/extras cargados durante la estadía — no altera el snapshot de precio de la habitación. */
    public function addonsTotal(): int
    {
        return (int) $this->addons()->sum('amount');
    }

    /** Total a cobrar: precio final de la habitación + consumo, menos lo ya pagado. */
    public function balanceDue(): int
    {
        return max(0, $this->price_final + $this->addonsTotal() - $this->paidAmount());
    }

    /**
     * Separa el consumo entre lo que ya quedó cubierto por pagos existentes
     * ("congelado", como pidió el usuario) y lo que es una orden nueva
     * todavía sin cobrar. Se calcula al vuelo, en el orden en que se pidió
     * cada ítem — el dinero ya pagado cubre primero la habitación y luego
     * el consumo más antiguo, línea por línea.
     *
     * @return array{paid: \Illuminate\Support\Collection, pending: \Illuminate\Support\Collection}
     */
    public function addonsByPaymentStatus(): array
    {
        $remaining = max(0, $this->paidAmount() - $this->price_final);

        $ordered = $this->addons->sortBy('created_at')->values();
        $paid = collect();
        $pending = collect();

        foreach ($ordered as $addon) {
            if ($remaining >= $addon->amount) {
                $remaining -= $addon->amount;
                $paid->push($addon);
            } else {
                $pending->push($addon);
            }
        }

        return ['paid' => $paid, 'pending' => $pending];
    }
}
