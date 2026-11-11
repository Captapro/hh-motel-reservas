<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anuncio de Meta del que llegó el cliente de una reserva (ver GhlAdOrigin). */
#[Fillable(['booking_id', 'meta_ad_id', 'meta_ad_name', 'wa_source', 'attempts', 'checked_at', 'contact_found'])]
class BookingAdOrigin extends Model
{
    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'contact_found' => 'boolean'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** ¿Llegó por un anuncio de pago? */
    public function isFromAd(): bool
    {
        return filled($this->meta_ad_id) || $this->wa_source === 'Paid Social';
    }
}
