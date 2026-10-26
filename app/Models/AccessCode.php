<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clave de una chapa digital: de una habitación (room_id) o de un recinto (bodega, etc.). */
#[Fillable(['room_id', 'name', 'kind', 'location', 'code', 'notes', 'updated_by'])]
class AccessCode extends Model
{
    public const KINDS = ['bodega' => 'Bodega', 'recinto' => 'Recinto', 'acceso' => 'Acceso / portón', 'otro' => 'Otro'];

    protected $hidden = ['code', 'notes'];

    protected function casts(): array
    {
        return [
            'code' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function label(): string
    {
        return $this->room?->name ?? (string) $this->name;
    }
}
