<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * is_active solo se chequeaba al hacer login -- una cuenta desactivada
 * mientras la sesión ya estaba abierta (ex-empleado, PIN comprometido)
 * seguía teniendo acceso completo hasta que la sesión expirara sola.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Esta cuenta está desactivada.']);
        }

        return $next($request);
    }
}
