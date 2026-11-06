<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Conversación dada de alta: sale del seguimiento hasta que llegue un mensaje nuevo. */
#[Fillable(['ghl_conversation_id', 'closed_at', 'closed_by'])]
class ClosedConversation extends Model
{
    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }
}
