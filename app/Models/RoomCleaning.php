<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un aseo de una mucama: de "Empezar aseo" a "Terminar aseo". */
#[Fillable(['room_id', 'staff_id', 'started_at', 'finished_at'])]
class RoomCleaning extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function minutes(): ?int
    {
        return $this->finished_at ? (int) $this->started_at->diffInMinutes($this->finished_at) : null;
    }
}
