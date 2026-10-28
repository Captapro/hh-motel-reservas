<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Room;
use App\Services\Booking\RoomInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomInspectionController extends Controller
{
    /**
     * Cuánto dura válida la marca de "escaneó este Playroom" (ver
     * RoomQrController::confirmScan) -- suficiente para completar el
     * formulario sin apuro, corto para que no sirva de shortcut permanente
     * guardando el link.
     */
    public const SCAN_VALID_SECONDS = 600;

    public function create(Request $request, Room $room): View|RedirectResponse
    {
        // Si vino marcado como "desde el QR" (Ronda de turno), exige haber
        // pasado de verdad por la cámara de escaneo -- si no, no se puede
        // llegar acá solo con el link guardado o tipeando la URL.
        if ($request->boolean('qr')) {
            $scannedAt = $request->session()->get('qr_scanned_at.'.$room->id);
            if (! $scannedAt || (now()->timestamp - $scannedAt) > self::SCAN_VALID_SECONDS) {
                return redirect()->route('rooms.qr.show', $room)
                    ->withErrors(['pin' => 'Tenés que escanear el QR de este Playroom con la cámara antes de inspeccionarlo.']);
            }
        }

        $room->load(['category', 'furniture.category']);
        $lastInspection = $room->inspections()->latest()->first();

        return view('rooms.inspection', ['room' => $room, 'lastInspection' => $lastInspection]);
    }

    public function store(Request $request, Room $room, RoomInspectionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'inspected_by' => ['required', 'string', 'max:100'],
            'shift' => ['required', 'in:Mañana,Tarde,Noche,Madrugada'],
            ...$service->validationRules(),
            'from_ronda' => ['nullable', 'boolean'],
            'from_qr' => ['nullable', 'boolean'],
        ]);

        $inspection = $service->submit($request, $room, $validated, $validated['inspected_by']);

        AuditLog::record(auth()->id(), 'habitacion.inspeccionar', 'Room', $room->id, null, $inspection->toArray());

        $statusMessage = $inspection->needs_maintenance
            ? 'Inspección guardada — '.$room->name.' necesita mantención.'
            : 'Inspección guardada — '.$room->name.' está en buen estado.';

        if ($request->boolean('from_ronda')) {
            return redirect()->route('shift_round.index')->with('status', $statusMessage);
        }

        // Si vino del QR de la puerta (anfitrión escaneando ese Playroom en
        // particular, no navegando desde el tablero), vuelve ahí mismo en vez
        // de mandarlo al tablero -- sigue en el mismo lugar físico.
        if ($request->boolean('from_qr')) {
            return redirect()->route('rooms.qr.show', $room)->with('status', $statusMessage);
        }

        return redirect()->route('rooms.board')->with('status', $statusMessage);
    }

    /**
     * Panel de mantención: última inspección de cada Playroom, para ver de
     * un vistazo cuáles necesitan revisión y cuáles nunca se inspeccionaron.
     */
    public function panel(): View
    {
        $rooms = Room::with(['category', 'latestInspection'])
            ->get()
            ->sortBy(fn (Room $room) => sprintf('%03d-%s', $room->category->display_order, $room->name))
            ->values();

        $entries = $rooms->map(fn (Room $room) => [
            'room' => $room,
            'inspection' => $room->latestInspection,
        ]);

        return view('rooms.inspection-panel', [
            'needsMaintenance' => $entries->filter(fn (array $e) => $e['inspection']?->needs_maintenance)->values(),
        ]);
    }
}
