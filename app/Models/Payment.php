<?php

namespace App\Models;

use App\Services\Notifications\AdminPush;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['request_token', 'booking_id', 'payment_method_id', 'amount', 'status', 'external_id', 'voucher_number', 'receipt_number', 'raw_payload', 'registered_by', 'notes'])]
class Payment extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Payment $payment) {
            if ($payment->status === 'aprobado' && ($payment->wasRecentlyCreated || $payment->wasChanged('status'))) {
                AdminPush::newPayment($payment);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    /** El medio con su origen: débito y crédito pasan por la máquina de Mercado Pago. */
    public function methodLabel(): string
    {
        return match ($this->paymentMethod?->code) {
            'mercado_pago' => 'Mercado Pago · Online',
            'debito' => 'Mercado Pago · Máquina · Débito',
            'credito' => 'Mercado Pago · Máquina · Crédito',
            default => (string) $this->paymentMethod?->name,
        };
    }

    public function isMercadoPago(): bool
    {
        return in_array($this->paymentMethod?->code, ['mercado_pago', 'debito', 'credito'], true);
    }

    /** N° de operación de un pago en línea; el saldo de un reembolso parcial conserva el del pago original. */
    public function mercadoPagoOperation(): ?string
    {
        return preg_match('/^MP-(\d+)/', (string) $this->external_id, $match) ? $match[1] : null;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
