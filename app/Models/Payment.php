<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['request_token', 'booking_id', 'payment_method_id', 'amount', 'status', 'external_id', 'voucher_number', 'receipt_number', 'raw_payload', 'registered_by', 'notes'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
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
