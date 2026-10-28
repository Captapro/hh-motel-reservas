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
     * extra, la persona adicional ("Goloso") y otros servicios (lavandería…),
     * y aparte van los extras (productos) y los pagos fraccionados.
     */
    public function concept(): string
    {
        return match (true) {
            $this->sale_type === 'ALOJAMIENTO' => 'alojamiento',
            $this->sale_type === 'EXTRAS' => 'extras',
            $this->sale_type === 'PAGO FRACCIONADO' => 'fraccionado',
            $this->sale_type === 'EXTRA CORE' && str_contains(mb_strtoupper((string) $this->detail), 'HORA EXTRA') => 'hora_extra',
            // En las planillas antiguas "extra core" es el Goloso (persona adicional): una línea
            // sin detalle cuenta como tal, salvo que diga otra cosa (lavandería, toalla…).
            $this->sale_type === 'EXTRA CORE' && ($this->isBlankDetail() || str_contains(mb_strtoupper((string) $this->detail), 'GOLOSO') || str_contains(mb_strtoupper((string) $this->detail), 'PERSONA')) => 'persona_adicional',
            $this->sale_type === 'EXTRA CORE' => 'servicios',
            default => 'otros',
        };
    }

    private function isBlankDetail(): bool
    {
        return trim((string) $this->detail) === '';
    }

    /** Venta total de la línea (alojamiento/extras principales + extras). */
    public function total(): int
    {
        return (int) $this->amount_main + (int) $this->amount_extras;
    }
}
