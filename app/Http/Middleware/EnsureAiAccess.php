<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Analista IA y Ayuda IA: solo las personas autorizadas en Personal. */
class EnsureAiAccess
{
    public function handle(Request $request, Closure $next, string $tool): Response
    {
        abort_unless($request->user()?->canUseAi($tool), 403, 'No tienes autorización para usar esta herramienta. Pídesela al administrador.');

        return $next($request);
    }
}
