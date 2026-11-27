<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Bloqueo por cuenta en los ingresos (correo y clave, PIN de ronda, PIN de
 * mucamas). El límite de las rutas es por conexión: quien cambia de conexión
 * seguía probando sin tope, y un PIN de 4 dígitos se recorre entero. Acá se
 * cuentan los intentos fallidos de cada cuenta, vengan de donde vengan; un
 * ingreso correcto los borra.
 */
final class LoginLockout
{
    private const MAX_FAILURES = 6;

    private const LOCK_SECONDS = 900;

    /** Mensaje para mostrar si la cuenta está bloqueada; null si puede intentar. */
    public static function blocked(string $kind, int|string $account): ?string
    {
        $key = self::key($kind, $account);
        if (! RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            return null;
        }

        $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

        return 'Demasiados intentos fallidos. Espera '.$minutes.($minutes === 1 ? ' minuto' : ' minutos').' y vuelve a probar.';
    }

    public static function fail(string $kind, int|string $account): void
    {
        RateLimiter::hit(self::key($kind, $account), self::LOCK_SECONDS);
    }

    public static function clear(string $kind, int|string $account): void
    {
        RateLimiter::clear(self::key($kind, $account));
    }

    private static function key(string $kind, int|string $account): string
    {
        return 'ingreso:'.$kind.':'.sha1(mb_strtolower(trim((string) $account)));
    }
}
