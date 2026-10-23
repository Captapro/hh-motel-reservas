<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Secciones del panel y qué perfiles pueden entrar a cada una. El
 * administrador siempre entra a todo; los demás perfiles se configuran en
 * Administración, Perfiles y accesos (tabla access_permissions).
 */
class AccessSections
{
    /** Perfiles configurables, con su nombre visible. El administrador no se configura. */
    public const ROLES = ['recepcion' => 'Anfitrión', 'marketing' => 'Marketing'];

    /** Clave => [nombre visible, ruta de entrada, patrones de nombre de ruta que la componen]. */
    public const SECTIONS = [
        'tablero' => ['Tablero', 'rooms.board', ['rooms.board', 'rooms.upcoming', 'rooms.bookings']],
        'calendario' => ['Calendario', 'calendar.index', ['calendar.*']],
        'clientes' => ['Clientes', 'admin.customers.index', ['admin.customers.*']],
        'productos' => ['Productos y combos', 'admin.products.index', ['admin.products.*', 'admin.combos.*']],
        'caja' => ['Caja', 'cash.show', ['cash.*']],
        'ventas' => ['Ventas e historial', 'sales.monthly', ['sales.*']],
        'operaciones' => ['Operaciones (aseo, rondas, mantención)', 'aseo.daily', ['aseo.*', 'shift_round.*', 'mucamas.activity*', 'rooms.inspections.*']],
        'analytics' => ['Analytics', 'admin.analytics.index', ['admin.analytics.*']],
        'opiniones' => ['Opiniones', 'admin.reviews.index', ['admin.reviews.*']],
    ];

    /** Punto de partida: lo que cada perfil ve si nadie lo ha configurado. */
    public const DEFAULTS = [
        'recepcion' => ['tablero' => true, 'calendario' => true, 'clientes' => true, 'caja' => true, 'operaciones' => true],
        'marketing' => ['clientes' => true, 'analytics' => true, 'opiniones' => true],
    ];

    private static ?array $matrix = null;

    public static function forRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }
        foreach (self::SECTIONS as $key => [, , $patterns]) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function allows(?string $role, string $section): bool
    {
        if ($role === 'administrador') {
            return true;
        }
        if (! isset(self::ROLES[$role])) {
            return false;
        }

        return self::matrix()[$role][$section] ?? (self::DEFAULTS[$role][$section] ?? false);
    }

    /** @return array<string, array<string, bool>> */
    public static function matrix(): array
    {
        if (self::$matrix === null) {
            self::$matrix = [];
            try {
                foreach (DB::table('access_permissions')->get() as $row) {
                    self::$matrix[$row->role][$row->section] = (bool) $row->allowed;
                }
            } catch (\Throwable $e) {
                // sin tabla todavía: valen los valores por defecto
            }
        }

        return self::$matrix;
    }

    public static function forget(): void
    {
        self::$matrix = null;
    }
}
