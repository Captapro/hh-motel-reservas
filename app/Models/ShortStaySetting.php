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
    public const MODES = [
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

    /** ¿Se ofrece por este canal ('recepcion' o 'catalogo')? */
    public function offeredOn(string $channel): bool
    {
        return $this->enabled && ($channel === 'catalogo' ? $this->catalog_enabled : $this->reception_enabled);
    }
}
