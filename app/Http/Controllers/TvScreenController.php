<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ComboItem;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Pantalla para el TV de recepción: pasa sola entre los combos activos y dos
 * QR (reservar la próxima visita y dejar opinión). Pública y de solo lectura:
 * muestra lo mismo que el catálogo. Se recarga sola para tomar cambios de
 * combos y precios sin tocar el TV.
 */
class TvScreenController extends Controller
{
    public function show(): View
    {
        $combos = Combo::with('items.product')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->filter(fn (Combo $combo) => $combo->availableCount() !== 0)
            ->map(fn (Combo $combo) => [
                'name' => $combo->name,
                'description' => $combo->description,
                'price' => (int) $combo->price,
                'items' => $combo->items->map(fn (ComboItem $item) => $this->itemLabel($item))->filter()->values(),
            ])
            ->values();

        $photos = collect(File::glob(public_path('images/rooms/*/1.jpg')))
            ->map(fn (string $path) => asset(str_replace(public_path().'/', '', $path)))
            ->values();

        return view('tv.show', [
            'combos' => $combos,
            'photos' => $photos,
            'reserveQr' => $this->qr(route('catalog.index')),
            'reviewQr' => $this->qr(route('reviews.create')),
        ]);
    }

    private function itemLabel(ComboItem $item): ?string
    {
        $qty = max(1, (int) $item->quantity);
        if ($item->isChoice()) {
            $names = $item->options()->pluck('name');

            return $qty.' a elección'.($names->isNotEmpty() && $names->count() <= 4 ? ' ('.$names->implode(', ').')' : ($item->choice_category ? ' de '.$item->choice_category : ''));
        }

        return $item->product ? ($qty > 1 ? $qty.' × ' : '').$item->product->name : null;
    }

    private function qr(string $url): string
    {
        $result = (new Builder(writer: new PngWriter, data: $url, size: 520, margin: 16))->build();

        return $result->getDataUri();
    }
}
