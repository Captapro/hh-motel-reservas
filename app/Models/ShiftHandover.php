<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuadre de caja chica y stock que hace el anfitrión al recibir o entregar
 * su turno. Lo contado pasa a ser la verdad: el stock se corrige y la
 * diferencia de efectivo se registra como movimiento de caja.
 */
#[Fillable(['user_id', 'staff_shift_log_id', 'kind', 'expected_cash', 'counted_cash', 'has_differences', 'notes', 'reviewed_at', 'reviewed_by'])]
class ShiftHandover extends Model
{
    protected function casts(): array
    {
        return [
            'has_differences' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShiftHandoverItem::class);
    }

    /** Entregas con diferencias que el administrador todavía no revisa. */
    public static function pendingReviewCount(): int
    {
        try {
            return static::where('has_differences', true)->whereNull('reviewed_at')->count();
        } catch (\Throwable $e) {
            return 0; // sin tabla todavía
        }
    }

    public function cashDifference(): int
    {
        return $this->counted_cash - $this->expected_cash;
    }
}
