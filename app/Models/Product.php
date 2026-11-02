<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'price', 'track_inventory', 'stock', 'low_stock_threshold', 'display_order', 'is_active'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->stock <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_inventory && $this->stock <= 0;
    }
}
