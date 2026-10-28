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

    /**
     * En qué grupo cuenta la línea: la planilla mezcla en "extra core" la hora
     * extra con packs y servicios (Goloso, lavandería…), y aparte van los
     * extras (productos) y los pagos fraccionados.
     */
    public function concept(): string
    {
        return match (true) {
            $this->sale_type === 'ALOJAMIENTO' => 'alojamiento',
            $this->sale_type === 'EXTRAS' => 'extras',
            $this->sale_type === 'PAGO FRACCIONADO' => 'fraccionado',
            $this->sale_type === 'EXTRA CORE' && str_contains(mb_strtoupper((string) $this->detail), 'HORA EXTRA') => 'hora_extra',
            $this->sale_type === 'EXTRA CORE' => 'packs',
            default => 'otros',
        };
    }

    /** Venta total de la línea (alojamiento/extras principales + extras). */
    public function total(): int
    {
        return (int) $this->amount_main + (int) $this->amount_extras;
    }
}
