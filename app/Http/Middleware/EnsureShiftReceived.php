<?php

namespace App\Http\Middleware;

use App\Models\ShiftHandover;
use App\Models\StaffShiftLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El anfitrión no puede trabajar hasta recibir su turno: contar los
 * productos (ShiftHandoverController). Un turno = un StaffShiftLog;
 * si la sesión sigue abierta de un día para otro y el turno anterior ya se
 * cerró solo, parte uno nuevo y tiene que volver a recibir.
 * Si hay gente esperando puede dejar el conteo para después: trabaja normal
 * y la barra de arriba le recuerda que le falta recibir el turno.
 */
class EnsureShiftReceived
{
    public function handle(Request $request, Closure $next): Response
    {
        view()->share('shiftReceivePending', false);
        $user = $request->user();
        $route = $request->route();

        // En la vista de anfitrión de soporte no hay turno real que recibir.
        if ($request->session()->get(ApplyHostView::SESSION_KEY) || ! $user?->isRecepcion() || ! $route
            || ! in_array('auth', $route->gatherMiddleware(), true)
            || $request->routeIs('shift.*', 'logout', 'help.ask')) {
            return $next($request);
        }

        try {
            $log = $user->openShiftLog() ?? StaffShiftLog::create(['user_id' => $user->id, 'started_at' => now()]);
            $received = ShiftHandover::where('staff_shift_log_id', $log->id)->where('kind', 'recibir')->exists();
        } catch (\Illuminate\Database\QueryException $e) {
            // Sin las tablas de turnos (migración pendiente) no se bloquea el trabajo.
            return $next($request);
        }

        if (! $received && (int) $request->session()->get('shift_receive_later') === $log->id) {
            view()->share('shiftReceivePending', true);

            return $next($request);
        }

        if (! $received) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Primero recibe tu turno.'], 409)
                : redirect()->route('shift.receive');
        }

        return $next($request);
    }
}
