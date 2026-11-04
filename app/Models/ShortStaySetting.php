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
    'min_minutes', 'max_minutes', 'pricing_mode', 'percent',
])]
class ShortStaySetting extends Model
{
    /** Minutos que quedan para el cierre (hasta) => % de descuento. Nunca más de 25%. */
    public const TIERS = [90 => 25, 120 => 15, 151 => 10];

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
        ];
    }

    public static function current(): self
    {
        $setting = static::firstOrCreate(['id' => 1]);

        // Recién creada solo trae el id: se recarga para tener los valores
        // por defecto de la tabla (si no, "enabled" queda null = apagada).
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }

    public function discountForMinutes(int $minutes): int
    {
        foreach (self::TIERS as $upTo => $percent) {
            if ($minutes <= $upTo) {
                return $percent;
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
