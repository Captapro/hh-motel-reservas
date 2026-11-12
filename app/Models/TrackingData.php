<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id', 'session_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer', 'landing_url', 'source_label',
    'fbclid', 'gclid', 'gbraid', 'wbraid', 'fbc', 'fbp', 'ip', 'user_agent', 'google_conversion_sent_at',
])]
class TrackingData extends Model
{
    protected function casts(): array
    {
        return ['google_conversion_sent_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
