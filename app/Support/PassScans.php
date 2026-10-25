<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Pases de reserva escaneados con el celular y todavía sin atender en el
 * computador de recepción. Vive en el caché unos minutos: es un aviso de
 * "llegó este huésped", no un registro permanente.
 */
class PassScans
{
    private const KEY = 'pass_scans';

    private const TTL_SECONDS = 600;

    public static function record(string $code): void
    {
        $scans = self::recent();
        $scans[$code] = now()->timestamp;
        Cache::put(self::KEY, $scans, self::TTL_SECONDS);
    }

    /** @return array<string,int> código => momento del escaneo, solo los de los últimos minutos */
    public static function recent(): array
    {
        $limit = now()->timestamp - self::TTL_SECONDS;

        return array_filter((array) Cache::get(self::KEY, []), fn ($at) => $at >= $limit);
    }

    public static function forget(string $code): void
    {
        $scans = self::recent();
        unset($scans[$code]);
        Cache::put(self::KEY, $scans, self::TTL_SECONDS);
    }
}
