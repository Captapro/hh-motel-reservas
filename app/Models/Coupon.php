<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'auto_apply', 'internal_name', 'image_url', 'discount_type', 'discount_value', 'min_amount', 'max_discount_amount',
    'starts_at', 'ends_at', 'allowed_weekdays', 'allowed_time_start', 'allowed_time_end', 'allowed_durations',
    'max_uses_total', 'max_uses_per_customer', 'is_stackable', 'requires_verification', 'verification_note',
    'min_age', 'is_active', 'included_extra_guests',
])]
class Coupon extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'allowed_weekdays' => 'array',
            'allowed_durations' => 'array',
            'auto_apply' => 'boolean',
            'is_stackable' => 'boolean',
            'requires_verification' => 'boolean',
            'min_age' => 'integer',
            'is_active' => 'boolean',
            'included_extra_guests' => 'integer',
        ];
    }

    /** Nombre que ve recepción/cliente para esta oferta o cupón. */
    public function label(): string
    {
        return $this->auto_apply ? $this->internal_name : ($this->code ?? $this->internal_name);
    }

    private const WEEKDAY_LABELS = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];

    /**
     * Texto legible de a qué día/horario aplica este cupón -- usado tanto
     * en el banner del catálogo como para avisar en vivo en el formulario
     * de reserva si lo que el cliente eligió no va a calificar.
     */
    public function constraintsLabel(): string
    {
        $parts = [];
        if ($this->allowed_weekdays) {
            $days = collect($this->allowed_weekdays)->sort()->values();
            $isConsecutive = $days->count() > 1 && $days->every(fn ($d, $i) => $i === 0 || $d === $days[$i - 1] + 1);
            $parts[] = $isConsecutive
                ? self::WEEKDAY_LABELS[$days->first()].' a '.self::WEEKDAY_LABELS[$days->last()]
                : $days->map(fn ($d) => self::WEEKDAY_LABELS[$d])->implode(', ');
        }
        if ($this->allowed_time_start && $this->allowed_time_end) {
            $parts[] = substr($this->allowed_time_start, 0, 5).' a '.substr($this->allowed_time_end, 0, 5);
        }

        return implode(' · ', $parts);
    }

    /** Días de la semana permitidos (0=Dom..6=Sáb), vacío = todos. */
    public function allowedWeekdaysArray(): array
    {
        return $this->allowed_weekdays ?? [];
    }

    public function scopeOffers($query)
    {
        return $query->where('auto_apply', true);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'coupon_rooms');
    }

    public function roomCategories(): BelongsToMany
    {
        return $this->belongsToMany(RoomCategory::class, 'coupon_room_categories');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Alcance explícito de habitaciones/categorías vacío = aplica a todas.
     */
    public function appliesToRoom(Room $room): bool
    {
        $roomScope = $this->rooms()->pluck('rooms.id');
        $categoryScope = $this->roomCategories()->pluck('room_categories.id');

        if ($roomScope->isEmpty() && $categoryScope->isEmpty()) {
            return true;
        }

        return $roomScope->contains($room->id) || $categoryScope->contains($room->room_category_id);
    }

    /**
     * ¿Esta oferta está vigente para esta habitación en este momento? — para
     * el badge del tablero. No mira hora ni duración (eso es de cada reserva),
     * solo fecha, día de la semana y alcance. Requiere rooms/roomCategories
     * precargados para no pegarle a la BD por habitación.
     */
    public function offerLiveFor(Room $room, Carbon $when): bool
    {
        if (! $this->auto_apply || ! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $when->toDateString() < $this->starts_at->toDateString()) {
            return false;
        }
        if ($this->ends_at && $when->toDateString() > $this->ends_at->toDateString()) {
            return false;
        }
        if ($this->allowed_weekdays && ! in_array($when->dayOfWeek, $this->allowed_weekdays, true)) {
            return false;
        }

        $roomIds = $this->relationLoaded('rooms') ? $this->rooms->pluck('id') : $this->rooms()->pluck('rooms.id');
        $catIds = $this->relationLoaded('roomCategories') ? $this->roomCategories->pluck('id') : $this->roomCategories()->pluck('room_categories.id');

        if ($roomIds->isEmpty() && $catIds->isEmpty()) {
            return true;
        }

        return $roomIds->contains($room->id) || $catIds->contains($room->room_category_id);
    }
}
