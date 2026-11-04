<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Booking\RoomBoardService;
use Illuminate\View\View;

/**
 * "Ronda de turno" -- protocolo del motel: al tomar el turno, el anfitrión
 * recorre todas las Playrooms que tiene a disposición (activas y libres en
 * este momento -- una ocupada no se puede inspeccionar) y llena la pauta de
 * inspección (rooms.inspections, ya existente) en cada una. Esta pantalla es
 * solo la lista/checklist de la ronda; el formulario en sí se reutiliza tal
 * cual -- ver RoomInspectionController.
 */
class ShiftRoundController extends Controller
{
    public function index(RoomBoardService $board): View
    {
        $today = now('America/Santiago')->startOfDay();

        // Solo las libres y listas admiten levantamiento: las demás se muestran
        // aparte, sin acción, para saber en qué está cada Playroom.
        $groups = $board->byCondition();
        $rooms = $groups['free']->map(fn (array $e) => $e['room'])->values();

        $reviewed = fn (Room $r) => $r->latestInspection && $r->latestInspection->created_at->gte($today);
        $byArrival = fn (array $e) => $e['status']['next_booking']?->starts_at?->timestamp ?? PHP_INT_MAX;

        // Lo que llega primero va arriba: las próximas salen de Pendientes para no repetirse.
        $upcoming = $groups['free']->filter(fn (array $e) => $e['status']['next_booking'])->sortBy($byArrival)->values();
        $upcomingIds = $upcoming->map(fn (array $e) => $e['room']->id);

        return view('rooms.shift-round', [
            'upcoming' => $upcoming,
            'pending' => $rooms->reject($reviewed)->whereNotIn('id', $upcomingIds)->values(),
            'pendingTotal' => $rooms->reject($reviewed)->count(),
            'done' => $rooms->filter($reviewed)->values(),
            'total' => $rooms->count(),
            'aseo' => $groups['aseo']->sortBy($byArrival)->values(),
            'occupied' => $groups['occupied'],
            'out' => $groups['out'],
        ]);
    }

    /**
     * Cámara para escanear el QR de la puerta -- decodifica en el navegador
     * (jsQR) y navega directo a esa habitación; no pasa por el servidor
     * hasta que ya se sabe qué habitación es.
     */
    public function scan(): View
    {
        return view('rooms.qr-scan');
    }
}
