<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['staff_id', 'user_id', 'started_at', 'ended_at'])]
class StaffShiftLog extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** Un turno abierto por más horas que esto se da por olvidado y se cierra. */
    public const MAX_SHIFT_HOURS = 16;

    /**
     * Si alguien cerró la pestaña sin apretar Salir, su turno quedaba
     * abierto para siempre (y sumaba cientos de horas). Pasadas
     * MAX_SHIFT_HOURS se cierra solo, con ese tope como hora de salida.
     */
    public static function closeStale(): void
    {
        static::whereNull('ended_at')
            ->where('started_at', '<', now()->subHours(self::MAX_SHIFT_HOURS))
            ->get()
            ->each(fn (self $log) => $log->update(['ended_at' => $log->started_at->copy()->addHours(self::MAX_SHIFT_HOURS)]));
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mucama (staff_id) o cuenta de acceso (user_id) -- una fila siempre
     * usa una sola de las dos, nunca ambas.
     */
    public function who(): Staff|User|null
    {
        return $this->staff ?? $this->user;
    }

    public function durationMinutes(): int
    {
        return (int) $this->started_at->diffInMinutes($this->ended_at ?? now());
    }
}
