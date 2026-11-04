<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomCleaning;
use App\Models\ShiftNote;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lo que la mucama hace desde su panel: empezar y terminar aseos, y dejar novedades. */
class MucamaWorkController extends Controller
{
    public function start(Request $request, Room $room): RedirectResponse
    {
        $mucama = $this->mucama($request);
        $panel = redirect()->route('rooms.qr.mucama_panel');

        if ($room->operational_status !== 'aseo' || $room->aseo_reported_at) {
            return $panel->withErrors(['aseo' => $room->name.' ya no está esperando aseo.']);
        }
        if ($open = RoomCleaning::with('room')->where('staff_id', $mucama->id)->whereNull('finished_at')->first()) {
            return $panel->withErrors(['aseo' => 'Primero termina el aseo de '.$open->room->name.'.']);
        }
        if ($other = RoomCleaning::with('staff')->where('room_id', $room->id)->whereNull('finished_at')->first()) {
            return $panel->withErrors(['aseo' => $other->staff->name.' ya está limpiando '.$room->name.'.']);
        }

        RoomCleaning::create(['room_id' => $room->id, 'staff_id' => $mucama->id, 'started_at' => now()]);

        return $panel->with('status', 'Aseo de '.$room->name.' iniciado.');
    }

    public function finish(Request $request, Room $room): RedirectResponse
    {
        $mucama = $this->mucama($request);
        $cleaning = RoomCleaning::where('room_id', $room->id)->where('staff_id', $mucama->id)->whereNull('finished_at')->first();
        $panel = redirect()->route('rooms.qr.mucama_panel');

        if (! $cleaning) {
            return $panel->withErrors(['aseo' => 'No tienes un aseo abierto en '.$room->name.'.']);
        }

        $cleaning->update(['finished_at' => now()]);

        if ($room->operational_status === 'aseo' && ! $room->aseo_reported_at) {
            $old = $room->only(['aseo_reported_by', 'aseo_reported_at']);
            $room->update(['aseo_reported_by' => $mucama->name, 'aseo_reported_at' => now()]);
            AuditLog::record(null, 'habitacion.aseo_reportado', 'Room', $room->id, $old, [
                ...$room->only(['aseo_reported_by', 'aseo_reported_at']),
                'staff_id' => $mucama->id,
            ]);
        }

        return $panel->with('status', '¡Listo! Aseo de '.$room->name.' terminado en '.$cleaning->minutes().' min. Recepción lo confirma.');
    }

    public function note(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(ShiftNote::CATEGORIES)],
            'body' => ['required', 'string', 'max:1000'],
            'room_id' => ['nullable', 'exists:rooms,id'],
        ], ['body.required' => 'Escribe la novedad.']);

        ShiftNote::create($validated + [
            'staff_id' => $this->mucama($request)->id,
            'urgent' => $request->boolean('urgent'),
            'pending' => $request->boolean('pending'),
        ]);

        return redirect()->route('rooms.qr.mucama_panel')->with('status', 'Novedad registrada. Recepción la verá.');
    }

    private function mucama(Request $request): Staff
    {
        return $request->attributes->get('mucama');
    }
}
