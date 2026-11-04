<?php

namespace App\Models;

use App\Services\Rooms\RoomClosingLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Cuaderno de novedades: lo que un turno le deja al siguiente. Lo escrito no se
 * edita ni se borra; una novedad pendiente sigue avisándose hasta resolverse.
 */
#[Fillable(['user_id', 'staff_id', 'category', 'body', 'room_id', 'urgent', 'pending', 'resolved_at', 'resolved_by', 'resolution'])]
class ShiftNote extends Model
{
    public const CATEGORIES = ['Playroom', 'Cliente', 'Caja', 'Mantención', 'Insumos', 'Otro'];

    /** Una anfitriona nueva o que vuelve no recibe novedades más viejas que esto, salvo las pendientes. */
    public const ALERT_DAYS = 3;

    protected function casts(): array
    {
        return ['urgent' => 'boolean', 'pending' => 'boolean', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** Quién la escribió: una cuenta del sistema o una mucama. */
    public function authorName(): string
    {
        return $this->user?->name ?? $this->staff?->name ?? '—';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_note_reads')->withPivot('read_at');
    }

    public function isOpen(): bool
    {
        return $this->pending && ! $this->resolved_at;
    }

    public function scopeUnreadBy(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->whereDoesntHave('readers', fn ($q) => $q->whereKey($user->id))
            ->where(fn ($q) => $q->where('created_at', '>=', now()->subDays(self::ALERT_DAYS))
                ->orWhere(fn ($open) => $open->where('pending', true)->whereNull('resolved_at')));
    }

    /**
     * Las novedades del día operativo (corta a las 03:00) y las pendientes.
     * Quedan a la vista durante el día y salen solas al empezar el siguiente.
     */
    public static function today(): Collection
    {
        $cut = now('America/Santiago')->setTimeFromTimeString(RoomClosingLog::CUT_TIME);
        if ($cut->isFuture()) {
            $cut->subDay();
        }

        try {
            return self::with(['user', 'staff', 'room'])
                ->where(fn ($q) => $q->where('created_at', '>=', $cut)
                    ->orWhere(fn ($open) => $open->where('pending', true)->whereNull('resolved_at')))
                ->orderByDesc('urgent')->orderByDesc('created_at')->get();
        } catch (QueryException) {
            return collect();
        }
    }

    /** Vista de anfitrión de soporte: lo que le saldría a una anfitriona, sin contar lecturas. */
    public static function previewAlert(): Collection
    {
        try {
            return self::with(['user', 'staff', 'room'])
                ->where(fn ($q) => $q->where('created_at', '>=', now()->subDays(self::ALERT_DAYS))
                    ->orWhere(fn ($open) => $open->where('pending', true)->whereNull('resolved_at')))
                ->orderByDesc('urgent')->orderByDesc('created_at')->get();
        } catch (QueryException) {
            return collect();
        }
    }

    /** Reservas que llegan en las próximas 24 horas, para avisarlas al entrar al turno. */
    public static function upcomingFor(?User $user): Collection
    {
        if (! $user?->isRecepcion()) {
            return collect();
        }

        try {
            return Booking::with(['room', 'customer'])
                ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'])
                ->whereNull('checked_in_at')
                ->whereBetween('starts_at', [now(), now()->addDay()])
                ->orderBy('starts_at')
                ->get();
        } catch (QueryException) {
            return collect();
        }
    }

    /** Lo que la anfitriona debe confirmar. Vacío si la tabla aún no existe. */
    public static function alertFor(?User $user): Collection
    {
        if (! $user?->isRecepcion()) {
            return collect();
        }

        try {
            return self::with(['user', 'staff', 'room'])->unreadBy($user)
                ->orderByDesc('urgent')->orderByDesc('created_at')->get();
        } catch (QueryException) {
            return collect();
        }
    }
}
