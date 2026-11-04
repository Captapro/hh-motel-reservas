<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vista de anfitrión: soporte recorre el sistema con los permisos de
 * recepción para ver lo mismo que ella. El perfil cambia solo en memoria y
 * durante esta petición (nunca se guarda en la cuenta), y es solo para mirar:
 * no deja hacer cambios, porque quedarían como reales y a nombre de soporte.
 */
class ApplyHostView
{
    public const SESSION_KEY = 'host_view';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        view()->share('hostView', false);

        if (! $user || $user->role !== 'soporte' || ! $request->session()->get(self::SESSION_KEY)) {
            return $next($request);
        }

        $user->role = 'recepcion';
        $user->syncOriginalAttribute('role');
        view()->share('hostView', true);

        if (! $request->isMethodSafe() && ! $request->routeIs('host_view.stop', 'logout')) {
            $message = 'Estás en vista de anfitrión: es solo para mirar y no guarda cambios.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : back()->with('host_view_blocked', $message);
        }

        return $next($request);
    }
}
