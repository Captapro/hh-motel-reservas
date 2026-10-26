<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'code', 'pass_token', 'customer_id', 'room_id', 'created_by', 'origin', 'starts_at', 'ends_at', 'duration_minutes', 'guests_count',
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

    /** Cómo nació la reserva (columna origin). Las antiguas sin dato se muestran como "—". */
    public const ORIGIN_LABELS = ['presencial' => 'Presencial', 'recepcion' => 'Reserva asistida', 'catalogo' => 'Reserva en línea'];

    public function originLabel(): string
    {
        return self::ORIGIN_LABELS[$this->origin] ?? '—';
    }

    /** Suma de pagos aprobados — nunca se resta el saldo directo, siempre se recalcula. */
    public function paidAmount(): int
    {
        return (int) $this->payments()->where('status', 'aprobado')->sum('amount');
    }

    /** Consumo/extras cargados durante la estadía — no altera el snapshot de precio de la habitación. */
    /**
     * Se puede eliminar (junto con sus pagos y consumos) solo si nunca
     * ocurrió: que nunca tuvo check-in ni se finalizó
     * (vigente, cancelada, vencida o no show). Sirve para
     * limpiar reservas de prueba; una estadía real no se borra.
     */
    /** Minutos que una reserva del catálogo queda retenida esperando el pago. */
    public const HOLD_MINUTES = 10;

    /**
     * Libera las reservas pendientes cuya retención venció: pasan a EXPIRADA y la
     * habitación vuelve a estar disponible. Las que recepción marcó como "pagará
     * en recepción / pago tardío" no tienen vencimiento y no se tocan.
     */
    public static function releaseExpired(): int
    {
        $expired = static::where('booking_status', 'PENDIENTE_PAGO')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $booking) {
            $booking->update(['booking_status' => 'EXPIRADA', 'notes' => trim(($booking->notes ? $booking->notes."\n" : '').'Retención vencida: no se registró el pago a tiempo.')]);
            AuditLog::record(null, 'reserva.expirar', 'Booking', $booking->id, ['booking_status' => 'PENDIENTE_PAGO'], ['booking_status' => 'EXPIRADA']);
        }

        return $expired->count();
    }

    /** Devuelve al inventario los productos (y los de cada combo) que esta reserva había descontado. */
    public function restoreConsumedStock(): void
    {
        foreach ($this->addons as $addon) {
            if ($addon->product_id) {
                Product::where('id', $addon->product_id)->where('track_inventory', true)->increment('stock', (int) $addon->quantity);
            } elseif ($addon->combo_id) {
                foreach (ComboItem::where('combo_id', $addon->combo_id)->get() as $item) {
                    Product::where('id', $item->product_id)->where('track_inventory', true)->increment('stock', (int) $item->quantity * (int) $addon->quantity);
                }
            }
        }
    }

    public function canBeDeleted(): bool
    {
        return ! in_array($this->booking_status, ['CHECK_IN', 'FINALIZADA'], true) && $this->checked_in_at === null;
    }

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
     * @return array{paid: Collection, pending: Collection}
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
