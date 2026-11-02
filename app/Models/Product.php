<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'category', 'price', 'track_inventory', 'stock', 'low_stock_threshold', 'display_order', 'is_active'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
            'image_updated_at' => 'datetime',
        ];
    }

    public function image(): HasOne
    {
        return $this->hasOne(ProductImage::class);
    }

    /** URL pública de la foto (cambia al subir otra, para que el TV no muestre la vieja). */
    public function imageUrl(): ?string
    {
        return $this->image_updated_at ? route('products.image', ['product' => $this->id, 'v' => $this->image_updated_at->timestamp]) : null;
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
