<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un dato corto del negocio que lee el analista de ventas. Lo agrega el administrador
 * o lo anota el propio analista cuando el administrador se lo cuenta en la conversación.
 */
#[Fillable(['body', 'source', 'created_by'])]
class AnalystNote extends Model
{
    public const MAX_NOTES = 40;

    public const MAX_LENGTH = 300;
}
