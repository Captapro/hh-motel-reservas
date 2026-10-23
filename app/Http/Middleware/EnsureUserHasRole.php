<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 'soporte' es superior: cumple el requisito de cualquier rol.
        if (! $request->user() || ($request->user()->role !== 'soporte' && ! in_array($request->user()->role, $roles, true))) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
