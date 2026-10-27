<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'price', 'display_order', 'is_active', 'show_in_catalog'])]
class Combo extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_catalog' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function bookingAddons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    /**
     * Cuántas unidades del combo se pueden armar hoy con el stock actual —
     * el mínimo entre los componentes que sí controlan inventario. Null si
     * ningún componente controla stock (combo "ilimitado").
     */
    public function availableCount(): ?int
    {
        $limits = $this->items->map(function (ComboItem $item) {
            // Elección: alcanza con la suma de los productos entre los que se
            // elige (si alguno no controla stock, esa parte es ilimitada).
            if ($item->isChoice()) {
                $options = $item->options();
                if ($options->isEmpty() || $options->contains(fn (Product $p) => ! $p->track_inventory)) {
                    return null;
                }

                return intdiv((int) $options->sum('stock'), max(1, $item->quantity));
            }
            if (! $item->product || ! $item->product->track_inventory) {
                return null;
            }

            return intdiv($item->product->stock, max(1, $item->quantity));
        })->filter(fn ($v) => $v !== null);

        return $limits->isEmpty() ? null : $limits->min();
    }

    public function hasChoices(): bool
    {
        return $this->items->contains(fn (ComboItem $item) => $item->isChoice());
    }

    /** "2 × Cerveza · 2 a elegir: Coca-Cola / Sprite" */
    public function itemsLabel(): string
    {
        return $this->items->map(fn (ComboItem $item) => $item->label())->join(' · ');
    }

    /**
     * Lo que necesita la pantalla para pedir los sabores al vender: por cada
     * componente a elección, cuántos se eligen por combo y entre qué productos
     * (con su stock, null si no se controla).
     *
     * @return array<int, array{item_id: int, quantity: int, options: array<int, array{id: int, name: string, stock: ?int}>}>
     */
    public function choicesSpec(): array
    {
        return $this->items->filter(fn (ComboItem $item) => $item->isChoice())->map(fn (ComboItem $item) => [
            'item_id' => $item->id,
            'quantity' => (int) $item->quantity,
            'options' => $item->options()->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stock' => $p->track_inventory ? (int) $p->stock : null,
            ])->values()->all(),
        ])->values()->all();
    }

    public function isOutOfStock(): bool
    {
        $available = $this->availableCount();

        return $available !== null && $available <= 0;
    }
}
