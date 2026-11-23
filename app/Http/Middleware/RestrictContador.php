<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El contador / auditor solo puede usar su panel (contador.*), cambiar su
 * clave y cerrar sesión. Todo lo demás del sistema -- reservas, caja,
 * clientes, configuración -- queda cerrado aunque conozca la dirección.
 */
class RestrictContador
{
    private const ALLOWED = ['contador.*', 'account.password*', 'logout', 'login', 'login.store'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isContador() || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Esta cuenta es solo del panel del contador.'], 403)
            : redirect()->route('contador.index');
    }
}
