<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Contacto de GHL que es del equipo de HH: en Conversaciones va aparte de los clientes. */
#[Fillable(['ghl_contact_id', 'created_by'])]
class TeamContact extends Model {}
