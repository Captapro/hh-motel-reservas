{{-- Aleta de novedades: no ocupa lugar en la pantalla. Se abre sola al entrar, y si la cierran sin
     confirmar queda latiendo en el borde derecho hasta apretar "Leí y entendí". --}}
@php
    $hhNotes = ($hostView ?? false) ? collect() : \App\Models\ShiftNote::alertFor(auth()->user());
    $hhUpcoming = ($hostView ?? false) || session(\App\Http\Controllers\ShiftNoteController::UPCOMING_SEEN) ? collect() : \App\Models\ShiftNote::upcomingFor(auth()->user());
    $hhAlerts = $hhNotes->count() + $hhUpcoming->count();
@endphp
@if ($hhAlerts > 0)
    <style>
        #shift-notes-flap { position:fixed; right:0; top:38%; z-index:9995; display:flex; flex-direction:column; align-items:center; gap:4px; padding:12px 7px; border:none; border-radius:10px 0 0 10px; background:#ff7918; color:#21170e; font:800 13px -apple-system,'Segoe UI',sans-serif; cursor:pointer; animation:hhNotesPulse 1.6s ease-in-out infinite; }
        #shift-notes-flap span { writing-mode:vertical-rl; transform:rotate(180deg); }
        #shift-notes-panel { position:fixed; right:0; top:0; bottom:0; z-index:9996; width:min(380px, 100vw); display:flex; flex-direction:column; background:#1c1c1c; border-left:2px solid #ff7918; color:#eee; font-family:-apple-system,'Segoe UI',sans-serif; }
        #shift-notes-panel[hidden] { display:none; }
        @keyframes hhNotesPulse { 0%,100% { box-shadow:0 0 0 0 rgba(255,121,24,.6); } 50% { box-shadow:0 0 0 9px rgba(255,121,24,0); } }
        @media (prefers-reduced-motion: reduce) { #shift-notes-flap { animation:none; } }
    </style>
    <button type="button" id="shift-notes-flap" aria-controls="shift-notes-panel" aria-label="Avisos sin leer: {{ $hhAlerts }}">🔔 <span>Novedades ({{ $hhAlerts }})</span></button>
    <aside id="shift-notes-panel" aria-labelledby="shift-notes-title">
        <div style="display:flex; align-items:flex-start; gap:10px; padding:16px 16px 10px;">
            <div style="flex:1;">
                <div id="shift-notes-title" style="font-size:17px; font-weight:800;">🔔 Novedades del turno ({{ $hhAlerts }})</div>
                <div style="font-size:13px; color:#aaa; margin-top:2px;">Léelas y confirma.</div>
            </div>
            <button type="button" id="shift-notes-close" aria-label="Minimizar" style="background:none; border:1px solid #444; color:#ccc; border-radius:7px; padding:4px 10px; cursor:pointer;">✕</button>
        </div>
        <div style="flex:1; min-height:0; overflow-y:auto; padding:0 16px;">
            @if ($hhUpcoming->isNotEmpty())
                <div style="font-size:12px; font-weight:800; color:#ff9a4d; margin:2px 0 6px;">Reservas próximas 24 horas</div>
                @foreach ($hhUpcoming as $booking)
                    @php $hhAt = $booking->starts_at->copy()->timezone('America/Santiago'); @endphp
                    <a href="{{ route('reservations.show', $booking->code) }}" style="display:flex; gap:10px; padding:8px 12px; margin-bottom:6px; border-radius:9px; background:#141414; color:#eee; text-decoration:none; font-size:14px;">
                        <b style="flex:none;">{{ $hhAt->isToday() ? '' : 'mañana ' }}{{ $hhAt->format('H:i') }}</b>
                        <span>{{ $booking->room?->name }} · {{ $booking->customer?->name }}</span>
                    </a>
                @endforeach
            @endif
            @if ($hhNotes->isNotEmpty() && $hhUpcoming->isNotEmpty())
                <div style="font-size:12px; font-weight:800; color:#ff9a4d; margin:12px 0 6px;">Novedades</div>
            @endif
            @foreach ($hhNotes as $note)
                <div style="padding:10px 12px; margin-bottom:8px; border-radius:9px; background:#141414; border-left:4px solid {{ $note->urgent ? '#ff7918' : '#555' }};">
                    <div style="font-size:12px; color:#999; margin-bottom:3px;">
                        @if ($note->urgent)<b style="color:#ff9a4d;">Urgente · </b>@endif
                        {{ $note->category }}@if ($note->room) · {{ $note->room->name }}@endif · {{ $note->user->name }} · {{ $note->created_at->timezone('America/Santiago')->format('d/m H:i') }}
                    </div>
                    <div style="font-size:14.5px; line-height:1.4; white-space:pre-wrap;">{{ $note->body }}</div>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('shift_notes.read') }}" style="padding:12px 16px 16px;">
            @csrf
            <button type="submit" style="width:100%; background:#ff7918; color:#21170e; border:none; border-radius:9px; padding:12px; font-size:15px; font-weight:800; cursor:pointer;">Leí y entendí</button>
        </form>
    </aside>
    <script>
        (function () {
            const panel = document.getElementById('shift-notes-panel');
            const KEY = 'hh-notes-minimized:{{ $hhNotes->max('id') }}:{{ $hhUpcoming->count() }}';
            const remember = (on) => { try { on ? sessionStorage.setItem(KEY, '1') : sessionStorage.removeItem(KEY); } catch (e) {} };
            try { panel.hidden = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
            document.getElementById('shift-notes-flap').addEventListener('click', () => { panel.hidden = false; remember(false); });
            document.getElementById('shift-notes-close').addEventListener('click', () => { panel.hidden = true; remember(true); });
        })();
    </script>
@endif
