<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\ShiftNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftNoteController extends Controller
{
    public const UPCOMING_SEEN = 'shift_upcoming_seen';

    public function index(Request $request): View
    {
        $filter = (string) $request->query('ver', 'todas');

        $notes = ShiftNote::with(['user', 'room', 'resolver', 'readers'])
            ->when($filter === 'pendientes', fn ($q) => $q->where('pending', true)->whereNull('resolved_at'))
            ->when($filter === 'urgentes', fn ($q) => $q->where('urgent', true))
            ->orderByRaw('case when pending and resolved_at is null then 0 else 1 end')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('shift-notes.index', [
            'notes' => $notes,
            'filter' => $filter,
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
        ], ['body.required' => 'Escribe la novedad.']);

        ShiftNote::create($validated + [
            'user_id' => $request->user()->id,
            'urgent' => $request->boolean('urgent'),
            'pending' => $request->boolean('pending'),
        ]);

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
}
