{{-- Aleta de novedades: no ocupa lugar en la pantalla. El panel nunca se abre solo: solo con la aleta,
     que late en el borde derecho mientras haya algo sin confirmar con "Leí y entendí". --}}
@php
    $hhPreview = $hostView ?? false;
    // Administración y soporte las ven sin aviso insistente: no les toca confirmar la lectura.
    $hhAdmin = ! $hhPreview && auth()->user()?->isAdministrador();
    $hhNotes = $hhPreview ? \App\Models\ShiftNote::previewAlert() : \App\Models\ShiftNote::alertFor(auth()->user());
    $hhUpcoming = ! $hhPreview && session(\App\Http\Controllers\ShiftNoteController::UPCOMING_SEEN) ? collect() : \App\Models\ShiftNote::upcomingFor(auth()->user());
    $hhAlerts = $hhNotes->count() + $hhUpcoming->count();
    // Las leídas siguen a la mano durante el día; salen solas al cambiar el día operativo.
    $hhToday = $hhAdmin ? \App\Models\ShiftNote::previewAlert() : (auth()->user()?->isRecepcion() ? \App\Models\ShiftNote::today() : collect());
    $hhAll = $hhNotes->merge($hhToday)->unique('id');
    $hhCount = $hhAlerts ?: $hhAll->count();
@endphp
@if ($hhCount > 0)
    <style>
        #shift-notes-flap { position:fixed; right:0; top:38%; width:auto; margin:0; z-index:9995; display:flex; flex-direction:column; align-items:center; gap:4px; padding:12px 7px; border:none; border-radius:10px 0 0 10px; background:#ff7918; color:#21170e; font:800 13px -apple-system,'Segoe UI',sans-serif; cursor:pointer; }
        #shift-notes-flap.unread { animation:hhNotesPulse 1.6s ease-in-out infinite; }
        #shift-notes-flap.quiet { background:#2c2c2c; color:#ffb379; }
        #shift-notes-flap span { writing-mode:vertical-rl; transform:rotate(180deg); }
        #shift-notes-panel { position:fixed; right:0; top:0; bottom:0; z-index:9996; width:min(380px, 100vw); display:flex; flex-direction:column; background:#1c1c1c; border-left:2px solid #ff7918; color:#eee; font-family:-apple-system,'Segoe UI',sans-serif; }
        #shift-notes-panel[hidden] { display:none; }
        /* Con clases y no en línea: el tema claro reescribe los colores en línea y aquí el panel sigue oscuro. */
        #shift-notes-panel .hh-note-sub { font-size:13px; color:#c7c7c7; margin-top:2px; }
        #shift-notes-panel .hh-note-close { width:auto; margin:0; flex:none; background:none; border:1px solid #666; color:#eee; border-radius:7px; padding:4px 10px; cursor:pointer; }
        #shift-notes-panel .hh-note-card { padding:10px 12px; margin-bottom:8px; border-radius:9px; background:#141414; color:#eee; border-left:4px solid #666; }
        #shift-notes-panel .hh-note-card.is-urgent { border-left-color:#ff7918; }
        #shift-notes-panel .hh-note-card.is-read { opacity:.8; }
        #shift-notes-panel .hh-note-meta { font-size:12px; color:#b5b5b5; margin-bottom:3px; }
        #shift-notes-panel .hh-note-body { font-size:14.5px; line-height:1.4; white-space:pre-wrap; color:#eee; }
        #shift-notes-panel .hh-note-booking { display:flex; gap:10px; padding:8px 12px; margin-bottom:6px; border-radius:9px; background:#141414; color:#eee; text-decoration:none; font-size:14px; }
        @keyframes hhNotesPulse { 0%,100% { box-shadow:0 0 0 0 rgba(255,121,24,.6); } 50% { box-shadow:0 0 0 9px rgba(255,121,24,0); } }
        @media (prefers-reduced-motion: reduce) { #shift-notes-flap.unread { animation:none; } }
    </style>
    <button type="button" id="shift-notes-flap" class="{{ $hhAlerts ? 'unread' : 'quiet' }}" aria-controls="shift-notes-panel" aria-label="Novedades: {{ $hhCount }}">🔔 <span>Novedades ({{ $hhCount }})</span></button>
    <aside id="shift-notes-panel" aria-labelledby="shift-notes-title" hidden>
        <div style="display:flex; align-items:flex-start; gap:10px; padding:16px 16px 10px;">
            <div style="flex:1;">
                <div id="shift-notes-title" style="font-size:17px; font-weight:800;">🔔 Novedades del turno ({{ $hhCount }})</div>
                <div class="hh-note-sub">{{ $hhAdmin ? 'Lo que hay para el turno.' : ($hhAlerts ? 'Léelas y confirma.' : 'Ya las leíste. Salen solas al terminar el día.') }} <a href="{{ route('shift_notes.index') }}" style="color:#ff9a4d;">Ver el libro completo</a></div>
            </div>
            <button type="button" id="shift-notes-close" aria-label="Minimizar" class="hh-note-close">✕</button>
        </div>
        <div style="flex:1; min-height:0; overflow-y:auto; padding:0 16px;">
            @if ($hhUpcoming->isNotEmpty())
                <div style="font-size:12px; font-weight:800; color:#ff9a4d; margin:2px 0 6px;">Reservas próximas 24 horas</div>
                @foreach ($hhUpcoming as $booking)
                    @php $hhAt = $booking->starts_at->copy()->timezone('America/Santiago'); @endphp
                    <a href="{{ route('reservations.show', $booking->code) }}" class="hh-note-booking">
                        <b style="flex:none;">{{ $hhAt->isToday() ? '' : 'mañana ' }}{{ $hhAt->format('H:i') }}</b>
                        <span>{{ $booking->room?->name }} · {{ $booking->customer?->name }}</span>
                    </a>
                @endforeach
            @endif
            @if ($hhAll->isNotEmpty() && $hhUpcoming->isNotEmpty())
                <div style="font-size:12px; font-weight:800; color:#ff9a4d; margin:12px 0 6px;">Novedades</div>
            @endif
            @foreach ($hhAll as $note)
                <div class="hh-note-card{{ $note->urgent ? ' is-urgent' : '' }}{{ $hhNotes->contains('id', $note->id) ? '' : ' is-read' }}">
                    <div class="hh-note-meta">
                        @if ($note->pinned)<b style="color:#ffb379;">📌 Fija · </b>@endif
                        @if ($note->urgent)<b style="color:#ff9a4d;">Urgente · </b>@endif
                        {{ $note->category }}@if ($note->room) · {{ $note->room->name }}@endif · {{ $note->authorName() }} · {{ $note->created_at->timezone('America/Santiago')->format('d/m H:i') }}
                    </div>
                    <div class="hh-note-body">{{ $note->body }}</div>
                </div>
            @endforeach
        </div>
        @if (! $hhAlerts)
            <div style="height:14px;"></div>
        @elseif ($hhPreview)
            <p style="margin:0; padding:12px 16px 16px; font-size:13px; color:#ffb379;">Vista previa de soporte: aquí la anfitriona confirma con "Leí y entendí".</p>
        @else
            <form method="POST" action="{{ route('shift_notes.read') }}" style="padding:12px 16px 16px;">
            @csrf
            <button type="submit" style="width:100%; background:#ff7918; color:#21170e; border:none; border-radius:9px; padding:12px; font-size:15px; font-weight:800; cursor:pointer;">Leí y entendí</button>
        </form>
        @endif
    </aside>
    <script>
        (function () {
            const panel = document.getElementById('shift-notes-panel');
            document.getElementById('shift-notes-flap').addEventListener('click', () => { panel.hidden = false; });
            document.getElementById('shift-notes-close').addEventListener('click', () => { panel.hidden = true; });
        })();
    </script>
@endif
