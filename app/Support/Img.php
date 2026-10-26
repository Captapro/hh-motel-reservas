<?php

namespace App\Support;

/**
 * Las fotos antiguas del catálogo viven en el CDN de GoHighLevel como originales de ~2 MB.
 * Img::url() las pasa por /img, que las reduce al ancho pedido y las guarda; las que ya son
 * livianas (carpeta propia /images) se dejan tal cual.
 */
class Img
{
    public const HOSTS = ['assets.cdn.filesafe.space'];

    public const WIDTHS = [160, 320, 480, 640, 960, 1280];

    public static function url(?string $url, int $width): string
    {
        if (! $url || ! str_starts_with($url, 'https://') || ! in_array(parse_url($url, PHP_URL_HOST), self::HOSTS, true)) {
            return (string) $url;
        }

        return route('img.resize', ['u' => $url, 'w' => self::snap($width)]);
    }

    /** Ancho permitido más cercano por arriba (así no se pide una medida a gusto de cualquiera). */
    public static function snap(int $width): int
    {
        foreach (self::WIDTHS as $allowed) {
            if ($width <= $allowed) {
                return $allowed;
            }
        }

        return self::WIDTHS[array_key_last(self::WIDTHS)];
    }
}
