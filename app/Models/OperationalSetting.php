<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con interruptores operativos globales del motel.
 */
#[Fillable([
    'piso_1_enabled',
    'piso_2_norte_enabled', 'piso_2_sur_enabled',
    'piso_3_norte_enabled', 'piso_3_sur_enabled',
    'conversations_since',
])]
class OperationalSetting extends Model
{
    protected function casts(): array
    {
        return [
            'piso_1_enabled' => 'boolean',
            'piso_2_norte_enabled' => 'boolean',
            'piso_2_sur_enabled' => 'boolean',
            'piso_3_norte_enabled' => 'boolean',
            'piso_3_sur_enabled' => 'boolean',
            'conversations_since' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * Combinaciones piso+ala actualmente deshabilitadas. El piso 1 (GO
     * 101-103) no tiene ala física -- wing null ahí, así que va con
     * wing => null. Se usa tanto para el scope de Eloquent (asignación de
     * habitación al crear una reserva) como para el filtro en memoria del
     * tablero -- misma fuente de verdad en los dos lugares.
     *
     * @return array<int, array{floor: int, wing: ?string}>
     */
    public function disabledFloorWings(): array
    {
        return collect([
            ['floor' => 1, 'wing' => null, 'enabled' => $this->piso_1_enabled],
            ['floor' => 2, 'wing' => 'norte', 'enabled' => $this->piso_2_norte_enabled],
            ['floor' => 2, 'wing' => 'sur', 'enabled' => $this->piso_2_sur_enabled],
            ['floor' => 3, 'wing' => 'norte', 'enabled' => $this->piso_3_norte_enabled],
            ['floor' => 3, 'wing' => 'sur', 'enabled' => $this->piso_3_sur_enabled],
        ])
            ->reject(fn (array $row) => $row['enabled'])
            ->map(fn (array $row) => ['floor' => $row['floor'], 'wing' => $row['wing']])
            ->values()
            ->all();
    }
}
