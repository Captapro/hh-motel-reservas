<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Celular de un administrador que aceptó recibir notificaciones. */
#[Fillable(['user_id', 'endpoint', 'public_key', 'auth_token'])]
class PushSubscription extends Model {}
