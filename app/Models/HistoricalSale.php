<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class HistoricalSale extends Model
{
    protected function casts(): array
    {
        return ['sold_on' => 'date'];
    }

    public function guests(): HasMany
    {
        return $this->hasMany(HistoricalGuest::class);
    }

    /** Venta total de la línea (alojamiento/extras principales + extras). */
    public function total(): int
    {
        return (int) $this->amount_main + (int) $this->amount_extras;
    }
}
