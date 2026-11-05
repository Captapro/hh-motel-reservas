<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Cuaderno de novedades: lo que un turno le deja al siguiente. Lo escrito no se
 * edita; solo administración puede eliminarlo, y queda en el registro de cambios.
 * Las temporales salen de la aleta a las 24 horas; las fijas y las pendientes se
 * quedan hasta quitarlas o resolverlas. En el libro quedan todas.
 */
#[Fillable(['user_id', 'staff_id', 'category', 'body', 'room_id', 'urgent', 'pending', 'pinned', 'resolved_at', 'resolved_by', 'resolution'])]
class ShiftNote extends Model
{
    public const CATEGORIES = ['Playroom', 'Cliente', 'Caja', 'Mantención', 'Insumos', 'Otro'];

    /** Cuánto dura a la vista una novedad temporal. */
    public const TEMPORARY_HOURS = 24;

    protected function casts(): array
    {
        return ['urgent' => 'boolean', 'pending' => 'boolean', 'pinned' => 'boolean', 'resolved_at' => 'datetime'];
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
            ->active();
    }

    /** A la vista del turno: temporales de las últimas 24 horas, fijas y pendientes. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('created_at', '>=', now()->subHours(self::TEMPORARY_HOURS))
            ->orWhere('pinned', true)
            ->orWhere(fn ($open) => $open->where('pending', true)->whereNull('resolved_at')));
    }

    /** Lo que está a la vista del turno, con fijas y urgentes primero. */
    public static function today(): Collection
    {
        try {
            return self::with(['user', 'staff', 'room'])->active()
                ->orderByDesc('pinned')->orderByDesc('urgent')->orderByDesc('created_at')->get();
        } catch (QueryException) {
            return collect();
        }
    }

    /** Vista de anfitrión de soporte: lo que le saldría a una anfitriona, sin contar lecturas. */
    public static function previewAlert(): Collection
    {
        return self::today();
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
                ->orderByDesc('pinned')->orderByDesc('urgent')->orderByDesc('created_at')->get();
        } catch (QueryException) {
            return collect();
        }
    }
}
