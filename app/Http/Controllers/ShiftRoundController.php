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

        $pending = $rooms->filter(fn (Room $r) => ! $r->latestInspection || $r->latestInspection->created_at->lt($today))->values();
        $done = $rooms->filter(fn (Room $r) => $r->latestInspection && $r->latestInspection->created_at->gte($today))->values();

        return view('rooms.shift-round', [
            'pending' => $pending,
            'done' => $done,
            'total' => $rooms->count(),
            'aseo' => $groups['aseo'],
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
