<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\Staff;
use App\Models\StaffShiftLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * "Dónde anda cada mucama ahora" para recepción -- distinto del panel de
 * mucamas (rooms.qr.mucama_panel), que es la vista operativa DE ELLAS. Esto
 * es la vista de seguimiento PARA recepción: a qué habitación fue cada una
 * la última vez y cuándo, con el estado actual de esa pieza como contexto,
 * la ruta de habitaciones que recorrió hoy, y el registro real de entrada/
 * salida de turno (StaffShiftLog).
 */
class MucamaActivityController extends Controller
{
    public function index(): View
    {
        // orderByDesc a secas ordena distinto según el motor: en Postgres los
        // NULL quedan PRIMERO en DESC (al revés que en MySQL/SQLite), así que
        // una mucama que nunca escaneó aparecería arriba de una que sí lo
        // hizo hace un rato -- se fuerza el orden explícito para que sea el
        // mismo sin importar el motor.
        StaffShiftLog::closeStale();

        // Solo las que están en turno real: el turno empieza cuando entran
        // al panel con su PIN y termina cuando salen.
        $mucamas = Staff::activeMucamas()
            ->whereHas('shiftLogs', fn ($q) => $q->whereNull('ended_at'))
            ->with(['lastQrRoom.category', 'shiftLogs' => fn ($q) => $q->whereNull('ended_at')])
            ->orderByRaw('last_qr_seen_at IS NULL')
            ->orderByDesc('last_qr_seen_at')
            ->get();

        $today = now('America/Santiago')->startOfDay();
        // Además de "empezó hoy", incluye cualquier turno que siga abierto
        // aunque haya empezado ayer -- si no, una mucama que entró antes de
        // medianoche y sigue trabajando desaparece de esta tabla mientras
        // el turno no se cierre.
        $todayLogs = StaffShiftLog::with(['staff', 'user'])
            ->where(fn ($q) => $q->where('started_at', '>=', $today)->orWhereNull('ended_at'))
            ->orderByDesc('started_at')
            ->get();

        $routesByStaff = $this->todaysRoutes($today);

        return view('mucamas.activity', ['mucamas' => $mucamas, 'todayLogs' => $todayLogs, 'routesByStaff' => $routesByStaff]);
    }

    /**
     * Resumen liviano para el polling del navegador (ver el <script> de la
     * vista) -- así recepción se entera de un cambio sin esperar el
     * refresco completo de la página cada 30s.
     */
    public function status(): JsonResponse
    {
        StaffShiftLog::closeStale();
        $mucamas = Staff::activeMucamas()->whereHas('shiftLogs', fn ($q) => $q->whereNull('ended_at'))->get(['id', 'name', 'last_qr_room_id', 'last_qr_seen_at']);
        $roomNames = Room::whereIn('id', $mucamas->pluck('last_qr_room_id')->filter())->pluck('name', 'id');

        return response()->json($mucamas->map(fn (Staff $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'room' => $m->last_qr_room_id ? ($roomNames[$m->last_qr_room_id] ?? null) : null,
            'seen_at' => $m->last_qr_seen_at?->toIso8601String(),
        ])->values());
    }

    /**
     * Historial de hoy por mucama: no solo POR DÓNDE pasó (qr_visita,
     * "entrada"), sino también qué DEJÓ ENTREGADO ahí -- aseo_reportado
     * (limpieza lista) y revision_previa (habitación confirmada para una
     * próxima reserva). Los tres eventos comparten room_id/staff_id en
     * new_value, así que se mezclan en una sola línea de tiempo por orden
     * de hora real, cada uno con su propia etiqueta.
     *
     * @return Collection<int, Collection>
     */
    private function todaysRoutes(Carbon $today): Collection
    {
        $labels = [
            'habitacion.qr_visita' => ['icon' => '🚪', 'text' => 'entrada'],
            'habitacion.aseo_reportado' => ['icon' => '✓', 'text' => 'aseo entregado'],
            'habitacion.revision_previa' => ['icon' => '✓', 'text' => 'revisión confirmada'],
        ];

        $logs = AuditLog::whereIn('action', array_keys($labels))
            ->where('created_at', '>=', $today)
            ->orderBy('created_at')
            ->get();

        $roomIds = $logs->map(fn (AuditLog $log) => (int) $log->entity_id)->unique();
        $roomNames = Room::whereIn('id', $roomIds)->pluck('name', 'id');

        return $logs->groupBy(fn (AuditLog $log) => $log->new_value['staff_id'] ?? 0)
            ->map(fn ($group) => $group->map(fn (AuditLog $log) => [
                'room' => $roomNames[(int) $log->entity_id] ?? '?',
                'at' => $log->created_at->timezone('America/Santiago')->format('H:i'),
                'icon' => $labels[$log->action]['icon'],
                'label' => $labels[$log->action]['text'],
            ]));
    }
}
