<?php

namespace App\Http\Middleware;

use App\Support\AccessSections;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $section = AccessSections::forRoute($request->route()?->getName());

        if ($section && $request->user() && ! $request->user()->canAccess($section)) {
            abort(403, 'Tu perfil no tiene acceso a esta sección.');
        }

        return $next($request);
    }
}
