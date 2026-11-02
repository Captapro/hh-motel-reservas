<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\TvScreenSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Pantalla para el TV de recepción: pasa sola entre los combos activos del catálogo y dos
 * QR (reservar la próxima visita y dejar opinión). Pública y de solo lectura:
 * muestra lo mismo que el catálogo. Se recarga sola para tomar cambios de
 * combos y precios sin tocar el TV.
 */
class TvScreenController extends Controller
{
    public function show(): View
    {
        $setting = TvScreenSetting::current();

        $combos = ! $setting->show_combos ? collect() : Combo::with('items.product')
            ->where('is_active', true)
            ->where('show_in_catalog', true)
            ->orderBy('display_order')
            ->get()
            ->filter(fn (Combo $combo) => $combo->availableCount() !== 0)
            ->map(fn (Combo $combo) => [
                'type' => 'combo',
                'name' => $combo->name,
                'description' => $setting->show_combo_contents ? $combo->description : null,
                'price' => (int) $combo->price,
                'items' => $setting->show_combo_contents ? $combo->items->map(fn (ComboItem $item) => $this->itemLabel($item))->filter()->values() : collect(),
                'images' => $setting->show_combo_contents ? $this->itemImages($combo) : [],
            ])
            ->values();

        // Lo que no es combo (QR y mensajes propios) se intercala: uno cada dos combos.
        $extras = collect()
            ->when($setting->show_reserve_qr, fn ($c) => $c->push(['type' => 'reserve']))
            ->concat(collect($setting->activeMessages())->map(fn ($m) => ['type' => 'message'] + $m))
            ->when($setting->show_review_qr, fn ($c) => $c->push(['type' => 'review']))
            ->values();

        $slides = collect();
        $next = 0;
        foreach ($combos->chunk(2) as $pair) {
            $slides = $slides->concat($pair);
            if ($extras->isNotEmpty()) {
                $slides->push($extras[$next++ % $extras->count()]);
            }
        }
        // Los que no alcanzaron a salir entre combos van al final de la vuelta.
        $slides = $slides->concat($extras->slice(min($next, $extras->count())));

        $photos = collect(File::glob(public_path('images/rooms/*/1.jpg')))
            ->map(fn (string $path) => asset(str_replace(public_path().'/', '', $path)))
            ->values();

        return view('tv.show', [
            'slides' => $slides->values(),
            'setting' => $setting,
            'photos' => $photos,
            'reserveQr' => $this->qr(route('catalog.index')),
            'reviewQr' => $this->qr(route('reviews.create')),
        ]);
    }

    /**
     * Fotos de los productos del combo (hasta 4). Un producto fijo sale tantas
     * veces como unidades trae (2 cervezas = 2 latas, máx. 3); en las
     * elecciones salen las primeras opciones que tengan foto.
     *
     * @return array<int, string>
     */
    private function itemImages(Combo $combo): array
    {
        $urls = [];
        foreach ($combo->items as $item) {
            if ($item->isChoice()) {
                $withImage = $item->options()->filter(fn ($p) => $p->image_updated_at)->take(max(1, (int) $item->quantity));
                foreach ($withImage as $product) {
                    $urls[] = $product->imageUrl();
                }
            } elseif ($item->product?->image_updated_at) {
                $urls = array_merge($urls, array_fill(0, min(3, max(1, (int) $item->quantity)), $item->product->imageUrl()));
            }
        }

        return array_slice($urls, 0, 4);
    }

    private function itemLabel(ComboItem $item): ?string
    {
        $qty = max(1, (int) $item->quantity);
        if ($item->isChoice()) {
            // Muchas opciones no caben en el TV: "2 a elegir entre 7 bebidas".
            $names = $item->options()->pluck('name');
            if ($names->isNotEmpty() && $names->count() <= 3) {
                return $qty.' a elegir: '.$names->implode(' / ');
            }

            return $qty.' a elegir'.($names->isNotEmpty() ? ' entre '.$names->count().' opciones' : '').($item->choice_category ? ' de '.mb_strtolower($item->choice_category) : '');
        }

        return $item->product ? ($qty > 1 ? $qty.' × ' : '').$item->product->name : null;
    }

    private function qr(string $url): string
    {
        $result = (new Builder(writer: new PngWriter, data: $url, size: 1000, margin: 24))->build();

        return $result->getDataUri();
    }
}
