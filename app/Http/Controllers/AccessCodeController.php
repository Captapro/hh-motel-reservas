<?php

namespace App\Http\Controllers;

use App\Models\AccessCode;
use App\Models\AuditLog;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Claves de las chapas digitales, a mano para una emergencia. Las claves se
 * muestran ocultas y se revelan a pedido: cada vez que alguien las mira queda
 * registrado en el AuditLog. Solo el administrador las agrega o cambia.
 */
class AccessCodeController extends Controller
{
    private const FIELDS = ['name' => 'nombre', 'kind' => 'tipo', 'location' => 'ubicación', 'code' => 'clave', 'notes' => 'nota'];

    public function index(): View
    {
        $codes = AccessCode::with('editor')->get();
        $byRoom = $codes->whereNotNull('room_id')->keyBy('room_id');
        $rooms = Room::with('category')->orderBy('name')->get()
            ->map(fn (Room $room) => ['room' => $room, 'code' => $byRoom->get($room->id)]);
        $places = $codes->whereNull('room_id')->sortBy([['kind', 'asc'], ['name', 'asc']])->values();

        return view('access-codes.index', [
            'rooms' => $rooms,
            'places' => $places,
            'kinds' => AccessCode::KINDS,
            'canEdit' => auth()->user()->isAdministrador(),
        ]);
    }

    /** Devuelve las claves pedidas (o todas) y deja registro de quién las miró. */
    public function reveal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);
        $query = AccessCode::query();
        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }
        $codes = $query->get();

        AuditLog::record(auth()->id(), 'claves.ver', 'AccessCode', empty($validated['ids']) ? 'todas' : implode(',', $codes->pluck('id')->all()), null, [
            'recintos' => $codes->load('room')->map->label()->values()->all(),
        ]);

        return response()->json($codes->mapWithKeys(fn (AccessCode $c) => [$c->id => ['code' => $c->code, 'notes' => $c->notes]]));
    }

    public function saveRoom(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::FIELDS);
        $existing = AccessCode::where('room_id', $room->id)->first();

        if (blank($validated['code'] ?? null)) {
            if ($existing) {
                $existing->delete();
                AuditLog::record(auth()->id(), 'claves.borrar', 'AccessCode', $existing->id, ['recinto' => $room->name], null);
            }

            return back()->with('status', "Clave de {$room->name} borrada.");
        }

        $code = AccessCode::updateOrCreate(['room_id' => $room->id], [
            'kind' => 'habitacion',
            'code' => $validated['code'],
            'notes' => $validated['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);
        AuditLog::record(auth()->id(), $existing ? 'claves.cambiar' : 'claves.crear', 'AccessCode', $code->id, null, ['recinto' => $room->name]);

        return back()->with('status', "Clave de {$room->name} guardada.");
    }

    /**
     * Carga varias claves de una vez, pegadas desde una planilla: una por línea,
     * nombre y clave (ej. "#101 1234" o "Puerta electricidad: 3333 (nota)"). Si el nombre termina en
     * un número de habitación, va a esa habitación; si no, se crea o actualiza
     * un recinto con ese nombre.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate(['lines' => ['required', 'string', 'max:20000']], [], ['lines' => 'listado']);
        $rooms = Room::all()->keyBy(fn (Room $r) => preg_match('/(\d{3})$/', $r->name, $m) ? $m[1] : $r->name);
        $saved = [];
        $skipped = [];

        foreach (preg_split('/\R/', $validated['lines']) as $raw) {
            $line = trim($raw);
            // Encabezado de la planilla ("HAB  LLAVE 1"): se ignora.
            if ($line === '' || preg_match('/^hab\b.*llave/iu', $line)) {
                continue;
            }
            // Nombre y valor separados por tabulación (copiado de Excel) o por ":";
            // si no, el nombre es todo menos la última palabra.
            $parts = preg_split('/\t+|\s*:\s+/u', $line, 2);
            if (count($parts) < 2 && preg_match('/^(.+?)\s+(\S+)$/u', $line, $m)) {
                $parts = [$m[1], $m[2]];
            }
            if (count($parts) < 2 || trim($parts[1]) === '') {
                $skipped[] = $line;
                continue;
            }
            $label = trim($parts[0], " :#\t");
            // El valor es la clave y, si trae algo más, eso va como nota: "3333 (Presiona el candado...)".
            [$code, $notes] = array_pad(preg_split('/\s+/u', trim($parts[1]), 2), 2, null);
            if (preg_match('/^(?:hab(?:itaci[oó]n)?\.?\s*)?#?\s*(\d{3})$/iu', $label, $n)) {
                $room = $rooms->get($n[1]);
                if (! $room) {
                    $skipped[] = $line.' (no existe la habitación '.$n[1].')';
                    continue;
                }
                AccessCode::updateOrCreate(['room_id' => $room->id], ['kind' => 'habitacion', 'code' => $code, 'notes' => $notes, 'updated_by' => auth()->id()]);
                $saved[] = $room->name;
            } else {
                $row = AccessCode::whereNull('room_id')->where('name', $label)->first()
                    ?? new AccessCode(['name' => $label, 'kind' => 'acceso']);
                $row->fill(['code' => $code, 'notes' => $notes, 'updated_by' => auth()->id()])->save();
                $saved[] = $label;
            }
        }

        AuditLog::record(auth()->id(), 'claves.importar', 'AccessCode', 'lote', null, ['recintos' => $saved, 'omitidas' => $skipped]);

        $message = count($saved).' claves guardadas.';
        if ($skipped) {
            $message .= ' No se cargaron: '.implode(' · ', $skipped).'.';
        }

        return redirect()->route('access_codes.index')->with('status', $message);
    }

    public function store(Request $request): RedirectResponse
    {
        $code = AccessCode::create($this->validatePlace($request) + ['updated_by' => auth()->id()]);
        AuditLog::record(auth()->id(), 'claves.crear', 'AccessCode', $code->id, null, ['recinto' => $code->name]);

        return back()->with('status', "{$code->name} agregado.");
    }

    public function update(Request $request, AccessCode $accessCode): RedirectResponse
    {
        abort_if($accessCode->room_id !== null, 404);
        $old = $accessCode->name;
        $accessCode->update($this->validatePlace($request) + ['updated_by' => auth()->id()]);
        AuditLog::record(auth()->id(), 'claves.cambiar', 'AccessCode', $accessCode->id, ['recinto' => $old], ['recinto' => $accessCode->name]);

        return back()->with('status', "{$accessCode->name} actualizado.");
    }

    public function destroy(AccessCode $accessCode): RedirectResponse
    {
        abort_if($accessCode->room_id !== null, 404);
        $accessCode->delete();
        AuditLog::record(auth()->id(), 'claves.borrar', 'AccessCode', $accessCode->id, ['recinto' => $accessCode->name], null);

        return back()->with('status', "{$accessCode->name} eliminado.");
    }

    private function validatePlace(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(array_keys(AccessCode::KINDS))],
            'location' => ['nullable', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::FIELDS);
    }
}
