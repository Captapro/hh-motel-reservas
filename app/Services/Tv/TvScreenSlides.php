<?php

namespace App\Services\Tv;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\TvScreenSetting;
use Illuminate\Support\Collection;

/**
 * Arma la vuelta de la pantalla del TV: todas las diapositivas posibles
 * (combos del catálogo, QR de reservar y de opinión, mensajes propios) en el
 * orden que se eligió en Administración → Pantalla TV, cada una encendida o
 * apagada. Lo nuevo que aún no está en la lista guardada (un combo recién
 * puesto en el catálogo) entra encendido al final.
 */
class TvScreenSlides
{
    /** @return Collection<int, array{key: string, label: string, on: bool, slide: array}> */
    public function playlist(TvScreenSetting $setting): Collection
    {
        $available = $this->available($setting);

        if ($setting->playlist === null) {
            return $this->defaultOrder($setting, $available);
        }

        $saved = collect($setting->playlist)->keyBy('key');
        $ordered = $saved->keys()->filter(fn ($key) => $available->has($key))
            ->map(fn ($key) => $available[$key] + ['on' => (bool) ($saved[$key]['on'] ?? false)]);
        $new = $available->except($saved->keys()->all())->map(fn ($entry) => $entry + ['on' => true]);

        return $ordered->concat($new)->values();
    }

    /** Solo las encendidas, listas para la vista del TV. */
    public function slides(TvScreenSetting $setting): Collection
    {
        return $this->playlist($setting)->filter(fn ($entry) => $entry['on'])->pluck('slide')->values();
    }

    /** @return Collection<string, array{key: string, label: string, slide: array}> */
    private function available(TvScreenSetting $setting): Collection
    {
        $entries = Combo::with('items.product')
            ->where('is_active', true)
            ->where('show_in_catalog', true)
            ->orderBy('display_order')
            ->get()
            ->filter(fn (Combo $combo) => $combo->availableCount() !== 0)
            ->map(fn (Combo $combo) => [
                'key' => 'combo:'.$combo->id,
                'label' => 'Combo · '.$combo->name.' · $'.number_format((int) $combo->price, 0, ',', '.'),
                'slide' => [
                    'type' => 'combo',
                    'name' => $combo->name,
                    'description' => $setting->show_combo_contents ? $combo->description : null,
                    'price' => (int) $combo->price,
                    'items' => $setting->show_combo_contents ? $combo->items->map(fn (ComboItem $item) => $this->itemLabel($item))->filter()->values() : collect(),
                ],
            ]);

        $entries->push(['key' => 'reserve', 'label' => 'QR · Reserva desde tu celular', 'slide' => ['type' => 'reserve']]);
        foreach ($setting->messages ?? [] as $i => $message) {
            if (filled($message['title'] ?? null)) {
                $entries->push(['key' => 'message:'.$i, 'label' => 'Mensaje · '.$message['title'], 'slide' => ['type' => 'message', 'title' => $message['title'], 'text' => $message['text'] ?? '']]);
            }
        }
        $entries->push(['key' => 'review', 'label' => 'QR · Danos tu opinión', 'slide' => ['type' => 'review']]);

        return $entries->keyBy('key');
    }

    /** Sin lista guardada: lo que no es combo se intercala, uno cada dos combos. */
    private function defaultOrder(TvScreenSetting $setting, Collection $available): Collection
    {
        $on = fn (array $entry) => $entry + ['on' => match ($entry['slide']['type']) {
            'combo' => (bool) $setting->show_combos,
            'reserve' => (bool) $setting->show_reserve_qr,
            'review' => (bool) $setting->show_review_qr,
            default => (bool) ($setting->messages[(int) substr($entry['key'], 8)]['active'] ?? true),
        }];
        $combos = $available->filter(fn ($e) => $e['slide']['type'] === 'combo')->values();
        $extras = $available->reject(fn ($e) => $e['slide']['type'] === 'combo')->values();

        $list = collect();
        $next = 0;
        foreach ($combos->chunk(2) as $pair) {
            $list = $list->concat($pair);
            if ($next < $extras->count()) {
                $list->push($extras[$next++]);
            }
        }

        return $list->concat($extras->slice($next))->map($on)->values();
    }

    private function itemLabel(ComboItem $item): ?string
    {
        $qty = max(1, (int) $item->quantity);
        if ($item->isChoice()) {
            // Muchas opciones no caben en el TV: "2 a elegir entre 7 opciones".
            $names = $item->options()->pluck('name');
            if ($names->isNotEmpty() && $names->count() <= 3) {
                return $qty.' a elegir: '.$names->implode(' / ');
            }

            return $qty.' a elegir'.($names->isNotEmpty() ? ' entre '.$names->count().' opciones' : '').($item->choice_category ? ' de '.mb_strtolower($item->choice_category) : '');
        }

        return $item->product ? ($qty > 1 ? $qty.' × ' : '').$item->product->name : null;
    }
}
