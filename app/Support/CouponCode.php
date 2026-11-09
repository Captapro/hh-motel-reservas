<?php

namespace App\Support;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Busca un cupón por lo que escribió el cliente, sin exigir el código exacto: ignora mayúsculas,
 * espacios y signos ("Morning escape", "MORNINGESCAPE" y "MORNING ESCAPE" son lo mismo).
 *
 * Los alias cubren cómo se nombra el cupón afuera del sistema: el botón "Activar cupón" de
 * hhmotel.cl habla del "cupón AM" (2026-11-09: un cliente escribió AM y el catálogo lo rechazó).
 */
class CouponCode
{
    /** Alias normalizado => código normalizado. */
    public const ALIASES = [
        'AM' => 'MORNINGESCAPE',
    ];

    public static function normalize(?string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim((string) $code))) ?? '';
    }

    /** Primer cupón (de la consulta dada) cuyo código coincide con lo escrito. */
    public static function find(Builder $query, ?string $input): ?Coupon
    {
        $wanted = self::normalize($input);
        if ($wanted === '') {
            return null;
        }
        $wanted = self::ALIASES[$wanted] ?? $wanted;

        // Son pocos cupones: se comparan acá para no depender de funciones de texto de cada base.
        $id = (clone $query)->get(['id', 'code'])
            ->first(fn (Coupon $c) => self::normalize($c->code) === $wanted)?->id;

        return $id ? $query->whereKey($id)->first() : null;
    }
}
