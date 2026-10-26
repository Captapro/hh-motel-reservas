<?php

namespace App\Http\Controllers;

use App\Support\Img;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reduce y guarda las fotos externas del catálogo. Solo acepta imágenes de los hosts permitidos
 * (nunca una URL cualquiera) y anchos de una lista fija. Si algo falla, redirige al original,
 * así una foto nunca queda rota.
 */
class ImageResizeController extends Controller
{
    private const MAX_SOURCE_BYTES = 15 * 1024 * 1024;

    public function show(Request $request): Response
    {
        $url = (string) $request->query('u');
        $host = parse_url($url, PHP_URL_HOST);
        if (! str_starts_with($url, 'https://') || ! in_array($host, Img::HOSTS, true)) {
            abort(404);
        }
        $width = Img::snap((int) $request->query('w', 640));

        $dir = storage_path('app/img-cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $base = $dir.'/'.sha1($url).'-'.$width;

        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png'] as $ext => $mime) {
            if (is_file($base.'.'.$ext)) {
                return $this->file($base.'.'.$ext, $mime);
            }
        }

        // Un solo proceso reduce cada imagen; los demás esperan y la encuentran lista.
        $lock = fopen($base.'.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            foreach (['jpg' => 'image/jpeg', 'png' => 'image/png'] as $ext => $mime) {
                if (is_file($base.'.'.$ext)) {
                    return $this->file($base.'.'.$ext, $mime);
                }
            }

            $made = $this->build($url, $width, $base);
            if ($made) {
                return $this->file($made[0], $made[1]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return redirect()->away($url);
    }

    /** @return array{0: string, 1: string}|null  ruta y tipo del archivo creado */
    private function build(string $url, int $width, string $base): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        @ini_set('memory_limit', '384M');

        try {
            $response = Http::timeout(25)->get($url);
        } catch (\Throwable $e) {
            return null;
        }
        if (! $response->successful() || strlen($response->body()) > self::MAX_SOURCE_BYTES) {
            return null;
        }

        $source = @imagecreatefromstring($response->body());
        if (! $source) {
            return null;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $newW = min($width, $srcW);
        $newH = (int) round($srcH * ($newW / $srcW));

        // PNG con transparencia (logos): se conserva PNG. El resto va como JPEG, mucho más liviano.
        $keepPng = str_contains((string) $response->header('Content-Type'), 'png') && $this->hasTransparency($source, $srcW, $srcH);

        $target = imagecreatetruecolor($newW, $newH);
        if ($keepPng) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        }
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        $path = $base.($keepPng ? '.png' : '.jpg');
        $ok = $keepPng ? imagepng($target, $path, 7) : imagejpeg($target, $path, 78);
        imagedestroy($source);
        imagedestroy($target);

        return $ok ? [$path, $keepPng ? 'image/png' : 'image/jpeg'] : null;
    }

    private function hasTransparency($image, int $w, int $h): bool
    {
        foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1], [intdiv($w, 2), 0], [0, intdiv($h, 2)]] as [$x, $y]) {
            if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                return true;
            }
        }

        return false;
    }

    private function file(string $path, string $mime): Response
    {
        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=2592000, immutable',
        ]);
    }
}
