<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['room_category_id', 'name', 'wing', 'photos', 'videos', 'buffer_minutes', 'operational_status', 'operational_note', 'aseo_override_at', 'aseo_started_at', 'aseo_reported_by', 'aseo_reported_at'])]
class Room extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'videos' => 'array',
            'aseo_override_at' => 'datetime',
            'aseo_started_at' => 'datetime',
            'aseo_reported_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function furniture(): BelongsToMany
    {
        return $this->belongsToMany(FurnitureItem::class, 'room_furniture')
            ->withPivot(['quantity', 'condition', 'notes'])->withTimestamps();
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(RoomInspection::class);
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(RoomInspection::class)->latestOfMany();
    }

    /**
     * Preferencia al asignar sola una habitación libre: primero el ala norte, después el
     * primer piso (sin ala) y al final el ala sur, para llenar el norte antes que el sur.
     */
    public function wingPriority(): int
    {
        return match ($this->wing) {
            'norte' => 0,
            'sur' => 2,
            default => 1,
        };
    }

    public function isOperational(): bool
    {
        return $this->operational_status === 'activa';
    }

    /**
     * Espejo de scopeFloorWingEnabled pero por categoría: cuando se apaga
     * una categoría entera desde el tablero (RoomCategory::is_active =
     * false), sus habitaciones dejan de ofrecerse para reservas nuevas.
     */
    public function scopeCategoryEnabled($query)
    {
        return $query->whereHas('category', fn ($q) => $q->where('is_active', true));
    }

    /**
     * Piso según los últimos 3 dígitos del nombre ("PLUS 201" -> 2). Null si
     * el nombre no sigue ese patrón (no debería pasar con los datos reales,
     * pero no hay que romper si algún día lo hace).
     */
    public function getFloorAttribute(): ?int
    {
        if (! preg_match('/(\d{3})$/', $this->name, $m)) {
            return null;
        }

        return intdiv((int) $m[1], 100);
    }

    /**
     * El motel prioriza el Ala Norte y va abriendo el Ala Sur piso por piso
     * según la capacidad que necesite (ver OperationalSetting) -- cuando un
     * piso+ala está apagado, sus habitaciones dejan de ofrecerse para
     * reservas nuevas. Se usa tanto acá (query SQL, para asignar habitación
     * al crear una reserva) como en isFloorWingEnabled() (en memoria, para
     * el tablero) -- misma fuente de verdad (OperationalSetting::disabledFloorWings()).
     */
    public function scopeFloorWingEnabled($query, OperationalSetting $setting)
    {
        $disabled = $setting->disabledFloorWings();
        if (empty($disabled)) {
            return $query;
        }

        return $query->where(function ($q) use ($disabled) {
            foreach ($disabled as $row) {
                $q->whereRaw(
                    "NOT (name ~ '\\d{3}$' AND (right(name, 3))::int BETWEEN ? AND ? AND wing IS NOT DISTINCT FROM ?)",
                    [$row['floor'] * 100, $row['floor'] * 100 + 99, $row['wing']]
                );
            }
        });
    }

    /**
     * Igual que scopeFloorWingEnabled pero para una habitación ya cargada
     * en memoria (usado por el tablero, que arma "Disponibles" filtrando
     * una colección en vez de una query).
     */
    public function isFloorWingEnabled(OperationalSetting $setting): bool
    {
        foreach ($setting->disabledFloorWings() as $row) {
            if ($this->floor === $row['floor'] && $this->wing === $row['wing']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reserva vigente en este instante (para calcular ocupación en el tablero).
     */
    public function currentBooking(): ?Booking
    {
        return $this->bookings()
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->first();
    }
}
