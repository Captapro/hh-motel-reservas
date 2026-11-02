<?php

namespace App\Support;

/**
 * Prepara la foto de un producto: la reduce a MAX_SIDE px y la guarda en PNG
 * conservando la transparencia (para las fotos "sin fondo").
 */
class ProductImageFile
{
    public const MAX_SIDE = 700;

    /** @return array{0: string, 1: string} contenido y tipo; si GD no puede leerla, se deja tal cual. */
    public static function prepare(string $contents): array
    {
        $info = @getimagesizefromstring($contents);
        $image = $info ? @imagecreatefromstring($contents) : false;
        if (! $image) {
            return [$contents, $info['mime'] ?? 'image/png'];
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $w = (int) round($width * $scale);
        $h = (int) round($height * $scale);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagepng($out, null, 8);

        return [(string) ob_get_clean(), 'image/png'];
    }
}
