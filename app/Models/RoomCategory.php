<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'former_name', 'description', 'sales_tip', 'features', 'base_capacity', 'max_capacity', 'extra_guest_from', 'display_order', 'is_active'])]
class RoomCategory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function rateRulePrices(): HasMany
    {
        return $this->hasMany(RateRulePrice::class);
    }
}
