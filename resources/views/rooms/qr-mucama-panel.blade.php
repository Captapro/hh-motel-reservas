<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="HH Aseo">
    <meta name="theme-color" content="#111111">
    <title>Aseo — HH Motel</title>
    <style>
        :root { color-scheme:light; }
        * { box-sizing:border-box; }
        body { margin:0; background:#f4f4f2; color:#1f2937; font-family:-apple-system,'Segoe UI',sans-serif; min-height:100vh; padding-bottom:30px; }
        .top { position:sticky; top:0; z-index:5; display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; background:#111; color:#fff; font-size:14px; font-weight:700; }
        .top form { margin:0; }
        .top button { background:none; border:none; color:#ff9a4d; font:inherit; font-size:13px; cursor:pointer; padding:0; }
        .wrap { max-width:560px; margin:0 auto; padding:14px 16px; }
        h1 { font-size:24px; margin:4px 0 2px; letter-spacing:-.02em; }
        .lead { margin:0 0 14px; color:#6b7280; font-size:14px; }
        h2 { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; margin:20px 0 8px; }
        .banner, .errors { padding:11px 14px; border-radius:10px; margin-bottom:12px; font-size:14px; font-weight:600; }
        .banner { background:#fff3e6; border:1px solid #ff7918; color:#9a3412; }
        .errors { background:#fde8e8; border:1px solid #e88a8a; color:#991b1b; }
        .stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-bottom:6px; }
        .stat { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; }
        .stat b { display:block; font-size:21px; }
        .stat span { font-size:11.5px; color:#6b7280; }
        .current { background:#111; color:#fff; border-radius:16px; padding:16px; margin-bottom:6px; }
        .current small { color:#ffb379; font-weight:700; font-size:12px; }
        .current h3 { margin:2px 0 4px; font-size:24px; }
        .timer { font:700 34px ui-monospace,monospace; margin:2px 0 12px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:12px 14px; margin-bottom:9px; }
        .card.aseo-urgent { border:2px solid #ff7918; }
        .card.aseo-high { border:2px solid #ffb379; }
        .row { display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .name { font-size:16px; font-weight:800; }
        .meta { font-size:12.5px; color:#6b7280; }
        .tag { display:inline-block; margin-top:3px; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:800; }
        .tag.aseo-urgent, .tag.aseo-high { background:#ff7918; color:#21170e; }
        .tag.aseo-mid { background:#a8530f; color:#fff; }
        .tag.aseo-info { background:#0a5cff; color:#fff; }
        .go { flex:none; min-height:46px; border:none; border-radius:11px; background:#ff7918; color:#21170e; font:800 15px -apple-system,'Segoe UI',sans-serif; padding:0 16px; cursor:pointer; }
        .go.full { width:100%; min-height:52px; font-size:17px; }
        .go:disabled { background:#e5e7eb; color:#9ca3af; cursor:not-allowed; }
        .empty { color:#9ca3af; font-size:14px; padding:6px 2px; }
        a.card { display:block; color:inherit; text-decoration:none; }
        label { display:block; font-size:13px; font-weight:600; color:#374151; margin:9px 0 4px; }
        select, textarea { width:100%; border:1px solid #d1d5db; border-radius:9px; background:#fff; color:#1f2937; font:15px -apple-system,'Segoe UI',sans-serif; padding:10px; }
        textarea { min-height:78px; resize:vertical; }
        .checks { display:flex; flex-wrap:wrap; gap:16px; margin:10px 0 12px; }
        .checks label { display:flex; align-items:center; gap:7px; margin:0; font-weight:500; }
        .checks input { width:20px; height:20px; accent-color:#ff7918; }
        details { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:12px 14px; margin-bottom:9px; }
        summary { cursor:pointer; font-weight:800; }
        .mod { border-radius:16px; overflow:hidden; margin-top:14px; background:#fff; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .mod-h { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 14px; font-size:14px; font-weight:800; color:#fff; }
        .mod-h b { min-width:26px; padding:1px 8px; border-radius:999px; background:rgba(255,255,255,.22); text-align:center; font-size:13px; }
        .mod-b { padding:10px; }
        .mod-b > .card:last-child, .mod-b > .note:last-child { margin-bottom:0; }
        .mod .card { background:#f7f7f5; }
        .mod .empty { margin:0; padding:4px 4px; }
        .mod-orange .mod-h { background:#ff7918; color:#21170e; }
        .mod-orange .mod-h b { background:rgba(0,0,0,.14); }
        .mod-blue .mod-h { background:#0a5cff; }
        .mod-dark .mod-h { background:#111; color:#ffb379; }
        .mod-amber .mod-h { background:#a8530f; }
        .mod-gray .mod-h { background:#4b5563; }
        .note { background:#fff7ef; border:1px solid #ffd2ad; border-left:5px solid #ff7918; border-radius:10px; padding:10px 12px; margin-bottom:9px; color:#1f2937; }
        .note .meta { color:#7c4a1e; font-weight:600; }
        #novedad { margin:10px 0 0; background:#f3f4f6; border-color:#d1d5db; }
        #novedad[open] { background:#e5e7eb; border-color:#9ca3af; }
    </style>
</head>
<body>
    <div class="top">
        <span>HH MOTEL · Aseo</span>
        <form method="POST" action="{{ route('mucamas.logout') }}">@csrf<button type="submit">Salir</button></form>
    </div>
    <div class="wrap">
        <h1>Hola, {{ $mucama->name }}</h1>
        <p class="lead">Empieza un Playroom y termínalo aquí: el tiempo queda registrado.</p>

        @if (session('status'))<div class="banner">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif

        <div class="stats">
            <div class="stat"><b>{{ $occupied->count() }}</b><span>ocupadas</span></div>
            <div class="stat"><b>{{ $pendingAseo->count() }}</b><span>por limpiar</span></div>
            <div class="stat"><b>{{ $doneToday->count() }}</b><span>tus aseos de hoy</span></div>
            <div class="stat"><b>{{ $doneToday->isNotEmpty() ? round($doneToday->avg(fn ($c) => $c->minutes())).' min' : '—' }}</b><span>tu promedio</span></div>
        </div>

        @if ($current)
            <h2>En curso</h2>
            <div class="current">
                <small>Limpiando ahora</small>
                <h3>{{ $current->room->name }}</h3>
                <div class="timer" id="timer" data-start="{{ $current->started_at->timestamp }}">00:00</div>
                <form method="POST" action="{{ route('mucamas.work.finish', $current->room) }}">
                    @csrf
                    <button class="go full" type="submit">Terminar aseo</button>
                </form>
            </div>
        @endif

        <section class="mod mod-blue">
            <div class="mod-h"><span>Ocupadas</span><b>{{ $occupied->count() }}</b></div>
            <div class="mod-b">
        @forelse ($occupied as $entry)
            <div class="card row">
                <span class="name">{{ $entry['room']->name }}</span>
                <span class="meta">
                    @if ($entry['status']['occupancy'] === 'ocupada' && $entry['status']['eta_minutes'] !== null && $entry['status']['eta_minutes'] >= 0)
                        Libera en {{ \App\Support\TimeFormat::minutes($entry['status']['eta_minutes']) }}
                    @else
                        Ocupada
                    @endif
                </span>
            </div>
        @empty
            <p class="empty">Ninguna ocupada ahora.</p>
        @endforelse
            </div>
        </section>

        @if ($upcoming->isNotEmpty())
            <section class="mod mod-orange">
                <div class="mod-h"><span>Próximas a llegar</span><b>{{ $upcoming->count() }}</b></div>
                <div class="mod-b">
            @foreach ($upcoming as $entry)
                @php
                    $next = $entry['status']['next_booking'];
                    $priority = \App\Support\AseoPriority::for($next->starts_at);
                @endphp
                <a class="card {{ $priority['class'] }}" href="{{ route('rooms.qr.show', $entry['room']) }}">
                    <div class="row">
                        <div>
                            <div class="name">{{ $entry['room']->name }}</div>
                            <span class="tag {{ $priority['class'] }}">{{ $priority['text'] }}</span>
                        </div>
                        <span class="meta">{{ $next->room_checked_at ? 'Confirmada' : 'Falta confirmar' }}</span>
                    </div>
                </a>
            @endforeach
                </div>
            </section>
        @endif

        <section class="mod mod-orange">
            <div class="mod-h"><span>Por limpiar</span><b>{{ $pendingAseo->count() }}</b></div>
            <div class="mod-b">
        @forelse ($pendingAseo as $entry)
            @php
                $room = $entry['room'];
                $next = $entry['status']['next_booking'];
                $priority = $next ? \App\Support\AseoPriority::for($next->starts_at) : null;
                $busy = $openByRoom->get($room->id);
            @endphp
            @continue($current && $current->room_id === $room->id)
            <div class="card {{ $priority['class'] ?? '' }}">
                <div class="row">
                    <div>
                        <div class="name">{{ $room->name }}</div>
                        @if ($priority)<span class="tag {{ $priority['class'] }}">{{ $priority['text'] }}</span>
                        @else<div class="meta">Sin reserva próxima</div>@endif
                        @if ($busy)<div class="meta">{{ $busy->staff->name }} lo está limpiando</div>@endif
                    </div>
                    <form method="POST" action="{{ route('mucamas.work.start', $room) }}" style="margin:0;">
                        @csrf
                        <button class="go" type="submit" @disabled($current || $busy)>Empezar</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="empty">Nada pendiente ahora mismo.</p>
        @endforelse
        @if ($current)<p class="meta">Termina el aseo en curso para empezar otro.</p>@endif
            </div>
        </section>

        <section class="mod mod-dark">
            <div class="mod-h"><span>Novedades de hoy</span><b>{{ $todayNotes->count() }}</b></div>
            <div class="mod-b">
            @forelse ($todayNotes as $note)
                <div class="note">
                    <div class="meta">@if ($note->urgent)<b style="color:#c2410c;">Urgente · </b>@endif{{ $note->category }}@if ($note->room) · {{ $note->room->name }}@endif · {{ $note->authorName() }} · {{ $note->created_at->timezone('America/Santiago')->format('H:i') }}</div>
                    <div style="font-size:14.5px; line-height:1.4; margin-top:3px; white-space:pre-wrap;">{{ $note->body }}</div>
                </div>
            @empty
                <p class="empty">Sin novedades por ahora.</p>
            @endforelse
        <details id="novedad" @if ($errors->has('body')) open @endif>
            <summary>Avisar algo a recepción</summary>
            <form method="POST" action="{{ route('mucamas.work.note') }}">
                @csrf
                <label for="note-category">Categoría</label>
                <select name="category" id="note-category" required>
                    @foreach (\App\Models\ShiftNote::CATEGORIES as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                <label for="note-room">Playroom (opcional)</label>
                <select name="room_id" id="note-room">
                    <option value="">—</option>
                    @foreach ($rooms as $option)
                        <option value="{{ $option->id }}" @selected((int) old('room_id', $current?->room_id) === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
                <label for="note-body">Novedad</label>
                <textarea name="body" id="note-body" maxlength="1000" required placeholder="Ej: falta jabón, ampolleta quemada">{{ old('body') }}</textarea>
                <div class="checks">
                    <label><input type="checkbox" name="urgent" value="1"> Urgente</label>
                    <label><input type="checkbox" name="pending" value="1"> Queda pendiente</label>
                </div>
                <button class="go full" type="submit">Enviar novedad</button>
            </form>
        </details>
            </div>
        </section>

        <section class="mod mod-amber">
            <div class="mod-h"><span>Esperan confirmación de recepción</span><b>{{ $reportedAseo->count() }}</b></div>
            <div class="mod-b">
        @forelse ($reportedAseo as $entry)
            <div class="card row"><span class="name">{{ $entry['room']->name }}</span><span class="meta">{{ $entry['room']->aseo_reported_by }} · {{ $entry['room']->aseo_reported_at->timezone('America/Santiago')->format('H:i') }}</span></div>
        @empty
            <p class="empty">Nada esperando confirmación.</p>
        @endforelse
            </div>
        </section>

        <section class="mod mod-gray">
            <div class="mod-h"><span>Fuera de servicio</span><b>{{ $outOfService->count() }}</b></div>
            <div class="mod-b">
        @forelse ($outOfService as $entry)
            <div class="card row">
                <span class="name">{{ $entry['room']->name }}</span>
                <span class="meta">{{ \App\Services\Rooms\RoomClosingLog::STATUS_LABELS[$entry['room']->operational_status] }}@if ($entry['room']->operational_note) · {{ $entry['room']->operational_note }}@endif</span>
            </div>
        @empty
            <p class="empty">Ninguna fuera de servicio.</p>
        @endforelse
            </div>
        </section>
    </div>
    <script>
        (function () {
            const timer = document.getElementById('timer');
            if (timer) {
                const start = Number(timer.dataset.start);
                const pad = (n) => String(n).padStart(2, '0');
                const tick = () => {
                    const s = Math.max(0, Math.floor(Date.now() / 1000) - start);
                    timer.textContent = (s >= 3600 ? Math.floor(s / 3600) + ':' : '') + pad(Math.floor(s / 60) % 60) + ':' + pad(s % 60);
                };
                tick(); setInterval(tick, 1000);
            }
            // Se actualiza solo, salvo mientras escriben una novedad.
            setInterval(function () {
                if (document.getElementById('novedad').open || document.getElementById('note-body').value) return;
                window.location.reload();
            }, 60000);
        })();
    </script>
</body>
</html>
