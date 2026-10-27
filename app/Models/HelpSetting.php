<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila única (id=1) con las reglas de la casa que lee el asistente de ayuda.
 */
#[Fillable(['house_rules', 'updated_by'])]
class HelpSetting extends Model
{
    /** Guía con la que parte la caja de reglas si está vacía. */
    public const TEMPLATE = <<<'TXT'
ATRASOS Y NO LLEGADAS
- Ej.: si llega más de 30 min tarde, se le respeta la hora de salida original

DEVOLUCIONES Y CANCELACIONES
- Ej.: el anfitrión no devuelve dinero; siempre lo autoriza administración

IDENTIFICACIÓN Y EDAD
- Ej.: pedir carnet a todos los acompañantes; no ingresan menores de 18

CLIENTES EN LISTA NEGRA O CONFLICTIVOS
- Ej.: no se les vende; avisar al administrador

EMERGENCIAS Y CONTACTOS
- Administrador: nombre y teléfono
- Mantención / electricidad:
- Seguridad / carabineros: 133

OTRAS REGLAS DEL TURNO
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
