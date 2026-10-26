<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con la regla de atrasos al check-in. Se edita en
 * Administración → Atrasos.
 */
#[Fillable(['tolerance_minutes', 'mode'])]
class LateArrivalSetting extends Model
{
    public const MODES = [
        'exceso' => 'Se descuenta solo lo que pasa de la tolerancia',
        'todo' => 'Se descuenta todo el atraso',
    ];

    protected function casts(): array
    {
        return ['tolerance_minutes' => 'integer'];
    }

    public static function current(): self
    {
        $setting = static::firstOrCreate(['id' => 1]);

        // Recién creada solo trae el id: se recarga para tener los valores por defecto de la tabla.
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }
}
