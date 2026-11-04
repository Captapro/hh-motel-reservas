<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Quien entró con PIN solo puede usar la ronda: 4 dígitos no abren caja, reservas ni clientes. */
class RestrictRondaSession
{
    public const SESSION_KEY = 'ronda_only';

    private const ALLOWED = [
        'ronda.*',
        'shift_round.*',
        'rooms.qr.show',
        'rooms.qr.confirm_scan',
        'rooms.qr.acknowledge_occupied',
        'rooms.inspections.create',
        'rooms.inspections.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->session()->get(self::SESSION_KEY) || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Esta sesión es solo para la ronda.'], 403)
            : redirect()->route('shift_round.index');
    }
}
