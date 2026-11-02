<?php

namespace App\Http\Controllers;

use App\Models\TvScreenSetting;
use App\Services\Tv\TvScreenSlides;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Pantalla para el TV de recepción: pasa sola por las diapositivas elegidas en
 * Administración → Pantalla TV (combos del catálogo, QR de reservar y de
 * opinión, mensajes propios). Pública y de solo lectura: muestra lo mismo que
 * el catálogo. Se recarga sola para tomar cambios sin tocar el TV.
 */
class TvScreenController extends Controller
{
    public function show(TvScreenSlides $slides): View
    {
        $setting = TvScreenSetting::current();

        $photos = collect(File::glob(public_path('images/rooms/*/1.jpg')))
            ->map(fn (string $path) => asset(str_replace(public_path().'/', '', $path)))
            ->values();

        return view('tv.show', [
            'slides' => $slides->slides($setting),
            'setting' => $setting,
            'photos' => $photos,
            'reserveQr' => $this->qr(route('catalog.index')),
            'reviewQr' => $this->qr(route('reviews.create')),
        ]);
    }

    private function qr(string $url): string
    {
        $result = (new Builder(writer: new PngWriter, data: $url, size: 1000, margin: 24))->build();

        return $result->getDataUri();
    }
}
