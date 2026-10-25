<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\Staff;
use App\Services\Booking\RoomBoardService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Páginas detrás del QR pegado en la puerta de cada habitación y del QR
 * general de mucamas. show()/image() son públicas (solo muestran estado, no
 * hay nada sensible ni que romper); reportAseo() y mucamaPanel() exigen
 * sesión de mucama (middleware 'mucama', ver EnsureMucamaSession) -- antes
 * cualquiera con el link podía reportar "a nombre de" cualquier mucama
 * eligiéndola de una lista, ahora reporta la que efectivamente inició sesión.
 */
class RoomQrController extends Controller
{
    public function show(Request $request, Room $room, RoomBoardService $board): View
    {
        $mucamaId = $request->session()->get('mucama_staff_id');
        $mucama = $mucamaId ? Staff::activeMucamas()->find($mucamaId) : null;

        // "Dónde anda cada mucama ahora" para recepción -- se actualiza con
        // cada visita a un QR de habitación, no solo al reportar aseo listo,
        // así se ve también cuando recién llegó a mirar una pieza. La ruta
        // del día (AuditLog) solo agrega una entrada cuando CAMBIA de
        // habitación -- si refresca la misma página varias veces no infla
        // la ruta con la misma parada repetida.
        if ($mucama) {
            if ($mucama->last_qr_room_id !== $room->id) {
                AuditLog::record(null, 'habitacion.qr_visita', 'Room', $room->id, null, ['staff_id' => $mucama->id, 'staff_name' => $mucama->name]);
            }
            $mucama->update(['last_qr_room_id' => $room->id, 'last_qr_seen_at' => now()]);
        }

        $status = $board->statusFor($room);
        $scannedAt = $request->session()->get('qr_scanned_at.'.$room->id);
        $hasValidScan = $scannedAt && (now()->timestamp - $scannedAt) <= RoomInspectionController::SCAN_VALID_SECONDS;

        return view('rooms.qr-show', [
            'room' => $room,
            'mucama' => $mucama,
            'nextBooking' => $status['next_booking'],
            'occupancy' => $status['occupancy'],
            'hasValidScan' => $hasValidScan,
        ]);
    }

    /**
     * Cuando el anfitrión llega a una habitación de la ronda y la encuentra
     * ocupada -- no se puede inspeccionar con el huésped adentro. Deja
     * registro de que se pasó a verla (quién y cuándo) sin crear una
     * inspección real, y sigue la ronda -- la pieza queda pendiente para
     * cuando se desocupe, no se pierde de la lista.
     */
    public function acknowledgeOccupied(Request $request, Room $room): RedirectResponse
    {
        AuditLog::record(auth()->id(), 'habitacion.ronda_ocupada', 'Room', $room->id, null, ['room_name' => $room->name]);

        $statusMessage = $room->name.' está ocupada -- la salteamos, queda pendiente para cuando se desocupe.';

        if ($request->boolean('from_ronda')) {
            return redirect()->route('shift_round.index')->with('status', $statusMessage);
        }

        return redirect()->route('rooms.qr.show', $room)->with('status', $statusMessage);
    }

    /**
     * Llamado por JS desde la cámara de escaneo (rooms/qr-scan.blade.php)
     * justo antes de navegar a la habitación decodificada -- deja una marca
     * en la sesión de que ESTE dispositivo escaneó ESTA habitación hace
     * poco. RoomInspectionController::create() la exige cuando llega con
     * ?qr=1, así "Hacer inspección completa" no se puede alcanzar solo
     * tipeando o guardando el link de la habitación -- hay que haber
     * pasado por la cámara de verdad.
     */
    public function confirmScan(Request $request, Room $room): JsonResponse
    {
        $request->session()->put('qr_scanned_at.'.$room->id, now()->timestamp);

        return response()->json(['ok' => true]);
    }

    /**
     * Confirma que la habitación quedó revisada/en condiciones ANTES de que
     * llegue el próximo huésped -- distinto de "aseo listo" (que es sobre la
     * limpieza en sí): esto es un último vistazo justo antes de la llegada.
     */
    public function confirmRoomReady(Request $request, Room $room, RoomBoardService $board): RedirectResponse
    {
        if ($room->operational_status !== 'activa') {
            return back()->withErrors(['pin' => 'Esta habitación pasó a '.$room->operational_status.' -- no se puede confirmar como lista todavía.']);
        }

        $next = $board->statusFor($room)['next_booking'];

        if (! $next) {
            return back()->withErrors(['pin' => 'Esta habitación ya no tiene una próxima reserva para confirmar.']);
        }

        /** @var Staff $mucama */
        $mucama = $request->attributes->get('mucama');

        $next->update(['room_checked_at' => now(), 'room_checked_by' => $mucama->name]);
        AuditLog::record(null, 'habitacion.revision_previa', 'Room', $room->id, null, [
            ...$next->only(['room_checked_at', 'room_checked_by']),
            'staff_id' => $mucama->id,
        ]);

        return redirect()->route('rooms.qr.show', $room)->with('status', '¡Gracias, '.$mucama->name.'! Quedó confirmada para la próxima reserva.');
    }

    /**
     * PNG del QR para imprimir y pegar en la puerta -- codifica la misma
     * URL pública de show(), nada sensible, así que no hace falta login
     * para verla (igual que las fotos del catálogo).
     */
    public function image(Room $room): Response
    {
        return $this->qrPng(route('rooms.qr.show', $room), $room->name);
    }

    public function mucamaPanelImage(): Response
    {
        return $this->qrPng(route('rooms.qr.mucama_panel'), 'Panel de mucamas');
    }

    /**
     * QR para que el anfitrión de turno entre directo a "Ronda de turno"
     * (ShiftRoundController) -- apunta a una ruta auth, así que si nadie ha
     * iniciado sesión en ese dispositivo, Laravel lo manda al login y
     * después de entrar lo redirige solo de vuelta acá.
     */
    public function shiftRoundImage(): Response
    {
        return $this->qrPng(route('shift_round.index'), 'Ronda de turno');
    }

    private function qrPng(string $url, string $label): Response
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            size: 400,
            margin: 12,
            labelText: $label,
        ))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }

    public function reportAseo(Request $request, Room $room): RedirectResponse
    {
        if ($room->operational_status !== 'aseo') {
            return back()->withErrors(['pin' => 'Esta habitación ya no está esperando aseo -- puede que alguien ya la haya confirmado.']);
        }
        if ($room->aseo_reported_at) {
            return back()->with('status', 'Ya estaba reportada -- recepción todavía tiene que confirmarla.');
        }

        /** @var Staff $mucama */
        $mucama = $request->attributes->get('mucama');

        $old = $room->only(['aseo_reported_by', 'aseo_reported_at']);
        $room->update(['aseo_reported_by' => $mucama->name, 'aseo_reported_at' => now()]);
        AuditLog::record(null, 'habitacion.aseo_reportado', 'Room', $room->id, $old, [
            ...$room->only(['aseo_reported_by', 'aseo_reported_at']),
            'staff_id' => $mucama->id,
        ]);

        return redirect()->route('rooms.qr.show', $room)->with('status', '¡Gracias, '.$mucama->name.'! Recepción ya puede confirmarlo.');
    }

    /**
     * Panel general de mucamas -- QR único, no por habitación. Solo lo
     * operativo del día: qué falta hacer y qué viene, nada de cliente ni
     * de dinero.
     */
    public function mucamaPanel(Request $request, RoomBoardService $board): View
    {
        $entries = collect($board->board());

        // Prioridad: la que tiene una reserva agendada más próxima primero
        // (por más urgente que sea limpiarla), las que no tienen nada
        // encima todavía agendado van al final -- next_booking se calcula
        // igual aunque la pieza esté en 'aseo', no hace falta nada nuevo acá.
        $pendingAseo = $entries->filter(fn (array $e) => $e['room']->operational_status === 'aseo' && ! $e['room']->aseo_reported_at)
            ->sortBy(fn (array $e) => $e['status']['next_booking']?->starts_at?->timestamp ?? PHP_INT_MAX)
            ->values();
        $reportedAseo = $entries->filter(fn (array $e) => $e['room']->operational_status === 'aseo' && $e['room']->aseo_reported_at)->values();
        $occupied = $entries->filter(fn (array $e) => $e['room']->operational_status === 'activa' && $e['status']['occupancy'] === 'ocupada')->values();
        $upcoming = $entries->filter(fn (array $e) => $e['room']->operational_status === 'activa' && $e['status']['occupancy'] === 'libre' && $e['status']['next_booking'])
            ->sortBy(fn (array $e) => $e['status']['next_booking']->starts_at)
            ->values();

        return view('rooms.qr-mucama-panel', [
            'pendingAseo' => $pendingAseo,
            'reportedAseo' => $reportedAseo,
            'occupied' => $occupied,
            'upcoming' => $upcoming,
            'mucama' => $request->attributes->get('mucama'),
        ]);
    }
}
