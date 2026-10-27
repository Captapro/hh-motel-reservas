<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Componente de un combo: un producto fijo (product_id) o una elección entre
 * varios productos (choice_product_ids), o entre todos los productos activos
 * de una categoría (choice_category: los que se agreguen después entran solos).
 */
#[Fillable(['combo_id', 'product_id', 'quantity', 'choice_product_ids', 'choice_category'])]
class ComboItem extends Model
{
    protected function casts(): array
    {
        return ['choice_product_ids' => 'array'];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isChoice(): bool
    {
        return ! $this->product_id && (! empty($this->choice_product_ids) || filled($this->choice_category));
    }

    /** Productos entre los que se elige (vacío si es un producto fijo). */
    public function options(): Collection
    {
        if (! $this->isChoice()) {
            return collect();
        }

        $query = filled($this->choice_category)
            ? Product::where('category', $this->choice_category)->where('is_active', true)
            : Product::whereIn('id', $this->choice_product_ids);

        return $query->orderBy('display_order')->orderBy('name')->get();
    }

    /** Ids entre los que se puede elegir ahora mismo. */
    public function optionIds(): array
    {
        return $this->options()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Texto para listas: "2 × Cerveza" o "2 a elegir: Coca-Cola / Sprite". */
    public function label(): string
    {
        if ($this->isChoice() && filled($this->choice_category)) {
            return $this->quantity.' a elegir de '.$this->choice_category;
        }

        return $this->isChoice()
            ? $this->quantity.' a elegir: '.$this->options()->pluck('name')->join(' / ')
            : $this->quantity.' × '.($this->product->name ?? '');
    }
}
