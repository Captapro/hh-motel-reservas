<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'role', 'pin', 'last_qr_room_id', 'last_qr_seen_at', 'legal_hours_per_week', 'is_active'])]
class Staff extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pin' => 'hashed',
            'last_qr_seen_at' => 'datetime',
        ];
    }

    /**
     * "Mucama activa" se repetía como Staff::where('role','Mucama')->where
     * ('is_active', true) en medio docena de sitios (login, middleware,
     * paneles) -- centralizado acá. El rol se carga como texto libre en
     * /admin/personal (sin un selector fijo), así que compara sin distinguir
     * mayúsculas/tildes de más -- "mucama", "Mucama", "MUCAMA " todos deben
     * calzar, si no la persona queda cargada pero invisible para el login
     * por PIN sin ningún aviso de por qué.
     */
    public function scopeActiveMucamas(Builder $query): Builder
    {
        return $query->whereRaw('lower(trim(role)) = ?', ['mucama'])->where('is_active', true);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function lastQrRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'last_qr_room_id');
    }

    public function shiftLogs(): HasMany
    {
        return $this->hasMany(StaffShiftLog::class);
    }

    public function openShiftLog(): ?StaffShiftLog
    {
        StaffShiftLog::closeStale();

        return $this->shiftLogs()->whereNull('ended_at')->latest('started_at')->first();
    }
}
