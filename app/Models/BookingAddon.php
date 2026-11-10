<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'product_id', 'combo_id', 'description', 'quantity', 'amount', 'components', 'stock_deducted_at', 'added_by'])]
class BookingAddon extends Model
{
    protected function casts(): array
    {
        // Productos que descontó un combo: [{product_id, units}].
        return ['components' => 'array', 'stock_deducted_at' => 'datetime'];
    }

    /** Lo que hay que entregar y descontar del inventario: [product_id => unidades]. */
    public function stockNeeds(): array
    {
        if ($this->product_id) {
            return [(int) $this->product_id => (int) $this->quantity];
        }
        if ($this->combo_id && $this->components) {
            return collect($this->components)->mapWithKeys(fn ($c) => [(int) $c['product_id'] => (int) $c['units']])->all();
        }

        return [];
    }

    /** Qué incluye este consumo, para que recepción sepa qué entregar: ["2 × Cerveza", "1 × Preservativo"]. */
    public function contentLines(): array
    {
        $needs = $this->stockNeeds();
        if ($this->combo_id && $needs) {
            $names = Product::whereIn('id', array_keys($needs))->pluck('name', 'id');

            return collect($needs)->map(fn ($units, $id) => $units.' × '.($names[$id] ?? 'Producto'))->values()->all();
        }

        // Combo antiguo sin detalle guardado: lo que trae hoy el combo.
        if ($this->combo_id && $this->combo) {
            return $this->combo->items->map(fn ($item) => $item->label())->all();
        }

        return [];
    }

    /** Extra core: lo que no es producto ni combo (hora extra, persona adicional, lavandería, upsells). */
    public function isCoreExtra(): bool
    {
        return $this->product_id === null && $this->combo_id === null;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
