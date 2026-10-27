<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila única (id=1) con el contexto del negocio que lee el analista de ventas.
 */
#[Fillable(['business_context', 'updated_by'])]
class AnalystSetting extends Model
{
    /** Guía con la que parte la caja de contexto si está vacía. */
    public const TEMPLATE = <<<'TXT'
OBJETIVOS (qué quieres lograr este trimestre)
- Ej.: subir la venta mensual a $X / llenar las tardes de semana / subir el ticket promedio

COSTOS (aproximados, por estadía o por mes)
- Costo de aseo y lavandería por habitación usada: $
- Costo fijo mensual (arriendo, sueldos, luz, agua): $
- Comisión de pago en línea (Mercado Pago): %

COMPETENCIA (precios de moteles comparables)
- Motel / categoría / duración / precio

TEMPORADAS Y FECHAS CLAVE
- Ej.: fines de mes, 14 de febrero, fiestas patrias, eventos cercanos

REGLAS QUE NO SE TOCAN
- Ej.: no bajar de $X la habitación más barata / la categoría MAX no lleva descuento

OTRAS NOTAS
-
TXT;

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
