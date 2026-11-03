<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cómo quedó un Playroom al cierre de un día operativo (ver RoomClosingLog). */
#[Fillable(['operating_date', 'room_id', 'operational_status', 'operational_note', 'occupied', 'offered', 'taken_at'])]
class RoomClosingSnapshot extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occupied' => 'boolean',
            'offered' => 'boolean',
            'taken_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
