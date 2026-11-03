<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'product_id', 'combo_id', 'description', 'quantity', 'amount', 'components', 'added_by'])]
class BookingAddon extends Model
{
    protected function casts(): array
    {
        // Productos que descontó un combo: [{product_id, units}].
        return ['components' => 'array'];
    }

    /** Extra core: lo que no es producto ni combo (hora extra, persona adicional, lavandería, upsells). */
    public function isCoreExtra(): bool
    {
        return $this->product_id === null && $this->combo_id === null;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
