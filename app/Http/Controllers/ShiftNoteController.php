<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\ShiftNote;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftNoteController extends Controller
{
    public const UPCOMING_SEEN = 'shift_upcoming_seen';

    /** El libro completo, para todo el equipo: se filtra por estado, fecha o texto. */
    public function index(Request $request): View
    {
        $filter = (string) $request->query('ver', 'todas');
        $search = trim((string) $request->query('q', ''));
        $from = (string) $request->query('desde', '');
        $to = (string) $request->query('hasta', '');
        $day = fn (string $date) => Carbon::hasFormat($date, 'Y-m-d') ? Carbon::parse($date, 'America/Santiago') : null;

        $notes = ShiftNote::with(['user', 'staff', 'room', 'resolver', 'readers'])
            ->when($filter === 'pendientes', fn ($q) => $q->where('pending', true)->whereNull('resolved_at'))
            ->when($filter === 'urgentes', fn ($q) => $q->where('urgent', true))
            ->when($search !== '', fn ($q) => $q->whereRaw('lower(body) like ?', ['%'.mb_strtolower($search).'%']))
            ->when($day($from), fn ($q, $date) => $q->where('created_at', '>=', $date->startOfDay()))
            ->when($day($to), fn ($q, $date) => $q->where('created_at', '<=', $date->endOfDay()))
            ->orderByDesc('pinned')
            ->orderByRaw('case when pending and resolved_at is null then 0 else 1 end')
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString();

        return view('shift-notes.index', [
            'notes' => $notes,
            'filter' => $filter,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'rooms' => Room::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(ShiftNote::CATEGORIES)],
            'body' => ['required', 'string', 'max:1000'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'urgent' => ['nullable', 'boolean'],
            'pending' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
        ], ['body.required' => 'Escribe la novedad.']);

        ShiftNote::create([
            'user_id' => $request->user()->id,
            'urgent' => $request->boolean('urgent'),
            'pending' => $request->boolean('pending'),
            // Fija: queda en la aleta de todos los turnos, por eso la marca solo administración.
            'pinned' => $request->user()->isAdministrador() && $request->boolean('pinned'),
        ] + $validated);

        return back()->with('status', 'Novedad registrada.');
    }

    /** "Leí y entendí": queda registrado quién leyó y a qué hora. */
    public function markRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ids = ShiftNote::unreadBy($user)->pluck('id');
        $user->readShiftNotes()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['read_at' => now()]])->all());
        // Las reservas próximas se avisan una vez por ingreso al sistema.
        $request->session()->put(self::UPCOMING_SEEN, true);

        return back();
    }

    public function resolve(Request $request, ShiftNote $note): RedirectResponse
    {
        abort_unless($note->isOpen(), 404);

        $validated = $request->validate(['resolution' => ['required', 'string', 'max:500']], ['resolution.required' => 'Escribe qué se hizo.']);

        $note->update($validated + ['resolved_at' => now(), 'resolved_by' => $request->user()->id]);

        return back()->with('status', 'Novedad resuelta.');
    }

    /** Deja de estar fija: sale de la aleta, pero sigue en el libro. */
    public function unpin(Request $request, ShiftNote $note): RedirectResponse
    {
        abort_unless($request->user()->isAdministrador(), 403);

        $note->update(['pinned' => false]);

        return back()->with('status', 'La novedad ya no está fija.');
    }

    /**
     * Solo administración: el texto anterior y quién lo había leído quedan en el registro de cambios.
     * Se borran las lecturas para que el equipo confirme el texto nuevo.
     */
    public function update(Request $request, ShiftNote $note): RedirectResponse
    {
        abort_unless($request->user()->isAdministrador(), 403);

        $validated = $request->validate(['body' => ['required', 'string', 'max:1000']], ['body.required' => 'Escribe la novedad.']);

        if ($validated['body'] === $note->body) {
            return back();
        }

        AuditLog::record($request->user()->id, 'novedad.editada', 'ShiftNote', $note->id, [
            'texto' => $note->body,
            'leida_por' => $note->readers()->pluck('name')->all(),
        ], ['texto' => $validated['body']]);
        $note->update($validated);
        $note->readers()->detach();

        return back()->with('status', 'Novedad actualizada.');
    }

    /** Solo administración: queda la copia del texto en el registro de cambios. */
    public function destroy(Request $request, ShiftNote $note): RedirectResponse
    {
        abort_unless($request->user()->isAdministrador(), 403);

        AuditLog::record($request->user()->id, 'novedad.eliminada', 'ShiftNote', $note->id, [
            'autor' => $note->authorName(),
            'categoria' => $note->category,
            'fija' => $note->pinned,
            'texto' => $note->body,
            'escrita' => $note->created_at?->toIso8601String(),
        ], null);
        $note->delete();

        return back()->with('status', 'Novedad eliminada.');
    }
}
