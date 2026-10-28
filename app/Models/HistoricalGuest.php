<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class HistoricalGuest extends Model
{
    protected function casts(): array
    {
        return ['is_holder' => 'boolean'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(HistoricalSale::class, 'historical_sale_id');
    }
}
