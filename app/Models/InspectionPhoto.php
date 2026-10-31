<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto de una inspección guardada en la base de datos. En room_inspections.photos
 * se referencia como "db:{id}"; se borra sola a los RETENTION_DAYS días.
 */
#[Fillable(['room_inspection_id', 'mime', 'data', 'size', 'created_at'])]
class InspectionPhoto extends Model
{
    public const RETENTION_DAYS = 30;

    public const PREFIX = 'db:';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Contenido de la imagen (se guarda en base64). */
    public function contents(): string
    {
        return (string) base64_decode((string) $this->data);
    }
}
