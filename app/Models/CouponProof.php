<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto del documento que respalda un cupón con verificación (ej. credencial de
 * estudiante), tomada en el check-in. Se borra sola a los RETENTION_DAYS días.
 */
#[Fillable(['booking_id', 'coupon_id', 'uploaded_by', 'mime', 'data', 'size', 'created_at'])]
class CouponProof extends Model
{
    public const RETENTION_DAYS = 30;

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
