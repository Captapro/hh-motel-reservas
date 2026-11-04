<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con las reglas de la estadía corta por cierre. Se edita
 * en Administración → Estadía corta.
 */
#[Fillable([
    'enabled', 'reception_enabled', 'catalog_enabled',
    'min_minutes', 'max_minutes', 'pricing_mode', 'percent', 'tiers',
])]
class ShortStaySetting extends Model
{
    /** Tramos por defecto: minutos que quedan para el cierre (hasta) y % de descuento. */
    public const TIERS = [
        ['up_to' => 90, 'percent' => 25],
        ['up_to' => 120, 'percent' => 15],
        ['up_to' => 151, 'percent' => 10],
    ];

    public const MODES = [
        'tramos' => 'Descuento por tramos (10%, 15% y 25%)',
        'proporcional' => 'Proporcional al tiempo que queda (con piso)',
        'porcentaje' => 'Porcentaje fijo del precio',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'reception_enabled' => 'boolean',
            'catalog_enabled' => 'boolean',
            'min_minutes' => 'integer',
            'max_minutes' => 'integer',
            'percent' => 'integer',
            'tiers' => 'array',
        ];
    }

    public static function current(): self
    {
        $setting = static::firstOrCreate(['id' => 1]);

        // Recién creada solo trae el id: se recarga para tener los valores
        // por defecto de la tabla (si no, "enabled" queda null = apagada).
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }

    /** @return list<array{up_to: int, percent: int}> del tramo más corto al más largo */
    public function discountTiers(): array
    {
        return collect($this->tiers ?: self::TIERS)->sortBy('up_to')->values()->all();
    }

    /** % de descuento según lo que queda; nunca pasa del descuento máximo. */
    public function discountForMinutes(int $minutes): int
    {
        foreach ($this->discountTiers() as $tier) {
            if ($minutes <= $tier['up_to']) {
                return min((int) $tier['percent'], (int) $this->percent);
            }
        }

        return 0;
    }

    /** ¿Se ofrece por este canal ('recepcion' o 'catalogo')? */
    public function offeredOn(string $channel): bool
    {
        return $this->enabled && ($channel === 'catalogo' ? $this->catalog_enabled : $this->reception_enabled);
    }
}
