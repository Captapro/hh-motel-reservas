<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="light">
    <title>Ronda de turno — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme:light; }
        * { box-sizing:border-box; }
        body { background:#f4f4f2; color:#1f2937; font-family:-apple-system,'Segoe UI',sans-serif; margin:0; }
        .page-inner { max-width:1100px; margin:0 auto; padding:18px 16px 50px; }
        .rd-title { font-size:24px; margin:4px 0 2px; letter-spacing:-.02em; color:#1f2937; }
        .rd-lead { margin:0 0 14px; color:#6b7280; font-size:14px; }
        .rd-banner { padding:11px 14px; border-radius:10px; margin-bottom:12px; font-size:14px; font-weight:600; background:#fff3e6; border:1px solid #ff7918; color:#9a3412; }
        .rd-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; }
        @media (max-width:480px) { .rd-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        .rd-stat { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; }
        .rd-stat b { display:block; font-size:21px; color:#1f2937; }
        .rd-stat span { font-size:11.5px; color:#6b7280; }
        .rd-bar { height:8px; background:#e5e7eb; border-radius:20px; margin:10px 0 12px; overflow:hidden; }
        .rd-bar i { display:block; height:100%; background:#ff7918; border-radius:20px; }
        a.rd-scan { display:flex; align-items:center; justify-content:center; min-height:52px; border-radius:11px; background:#ff7918; color:#21170e !important; font-size:16px; font-weight:800; text-decoration:none; }
        .mod { border-radius:16px; overflow:hidden; margin-top:14px; background:#fff; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .mod-h { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 14px; font-size:14px; font-weight:800; color:#fff; }
        .mod-h b { min-width:26px; padding:1px 8px; border-radius:999px; background:rgba(255,255,255,.22); text-align:center; font-size:13px; }
        .mod-b { padding:10px; }
        .mod-orange .mod-h { background:#ff7918; color:#21170e; }
        .mod-orange .mod-h b { background:rgba(0,0,0,.14); }
        .mod-blue .mod-h { background:#0a5cff; }
        .mod-dark .mod-h { background:#111; color:#ffb379; }
        .mod-amber .mod-h { background:#a8530f; }
        .mod-gray .mod-h { background:#4b5563; }
        .mod-soft .mod-h { background:#e5e7eb; color:#374151; }
        .mod-soft .mod-h b { background:rgba(0,0,0,.08); }
        .rd-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:9px; }
        .rd-card { display:flex; justify-content:space-between; align-items:center; gap:10px; background:#f7f7f5; border:1px solid #e5e7eb; border-radius:14px; padding:12px 14px; color:inherit; text-decoration:none; }
        a.rd-card:hover { border-color:#ff7918; }
        .rd-card.aseo-urgent { border:2px solid #ff7918; }
        .rd-card.aseo-high { border:2px solid #ffb379; }
        .rd-name { font-size:16px; font-weight:800; color:#1f2937; }
        .rd-meta { font-size:12.5px; color:#6b7280; }
        .rd-go { flex:none; color:#c2410c; font-size:13px; font-weight:700; }
        .rd-tag { display:inline-block; margin-top:3px; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:800; }
        .rd-tag.aseo-urgent, .rd-tag.aseo-high { background:#ff7918; color:#21170e; }
        .rd-tag.aseo-mid { background:#a8530f; color:#fff; }
        .rd-tag.aseo-info { background:#0a5cff; color:#fff; }
        .rd-warn { flex:none; color:#c2410c; font-size:12px; font-weight:700; }
        .rd-empty { grid-column:1 / -1; color:#9ca3af; font-size:14px; padding:4px; margin:0; }
    </style>
</head>
@include('partials.navbar')
<div class="page-inner">
    <h1 class="rd-title">Ronda de turno</h1>
    <p class="rd-lead">Recorre cada Playroom lista y deja la pauta de inspección al día.</p>

    @if (session('status'))
        <div class="rd-banner">{{ session('status') }}</div>
    @endif

    <div class="rd-stats">
        <div class="rd-stat"><b>{{ $occupied->count() }}</b><span>ocupadas</span></div>
        <div class="rd-stat"><b>{{ $done->count() }} de {{ $total }}</b><span>revisadas hoy</span></div>
        <div class="rd-stat"><b>{{ $pendingTotal }}</b><span>por revisar</span></div>
        <div class="rd-stat"><b>{{ $aseo->count() }}</b><span>en aseo</span></div>
    </div>
    <div class="rd-bar"><i style="width:{{ $total > 0 ? round($done->count() / $total * 100) : 0 }}%;"></i></div>

    <a class="rd-scan" href="{{ route('shift_round.scan') }}?ronda=1">Escanear QR del Playroom</a>

    <section class="mod mod-blue">
        <div class="mod-h"><span>Ocupadas</span><b>{{ $occupied->count() }}</b></div>
        <div class="mod-b rd-grid">
    @forelse ($occupied as $entry)
        <div class="rd-card">
            <div>
                <div class="rd-name">{{ $entry['room']->name }}</div>
                <div class="rd-meta">{{ $entry['room']->category->name }}@if ($entry['status']['occupancy'] === 'ocupada' && $entry['status']['eta_minutes'] !== null && $entry['status']['eta_minutes'] >= 0) · libera en {{ \App\Support\TimeFormat::minutes($entry['status']['eta_minutes']) }}@endif</div>
            </div>
        </div>
    @empty
        <p class="rd-empty">Ninguna ocupada ahora.</p>
    @endforelse
    </div>
    </section>

    @if ($upcoming->isNotEmpty())
        <section class="mod mod-orange">
            <div class="mod-h"><span>Próximas a llegar</span><b>{{ $upcoming->count() }}</b></div>
            <div class="mod-b rd-grid">
        @foreach ($upcoming as $entry)
            @php
                $room = $entry['room'];
                $priority = \App\Support\AseoPriority::for($entry['status']['next_booking']->starts_at);
                $isDone = $done->contains('id', $room->id);
            @endphp
            <a class="rd-card {{ $priority['class'] }}" href="{{ route('rooms.qr.show', $room) }}?ronda=1">
                <div>
                    <div class="rd-name">{{ $room->name }}</div>
                    <span class="rd-tag {{ $priority['class'] }}">{{ $priority['text'] }}</span>
                </div>
                <span class="{{ $isDone ? 'rd-meta' : 'rd-go' }}">{{ $isDone ? 'Revisada ✓' : 'Ir al Playroom →' }}</span>
            </a>
        @endforeach
        </div>
        </section>
    @endif

    <section class="mod mod-amber">
        <div class="mod-h"><span>En aseo</span><b>{{ $aseo->count() }}</b></div>
        <div class="mod-b rd-grid">
    @forelse ($aseo as $entry)
        @php
            $next = $entry['status']['next_booking'];
            $priority = $next ? \App\Support\AseoPriority::for($next->starts_at) : null;
        @endphp
        <div class="rd-card {{ $priority['class'] ?? '' }}">
            <div>
                <div class="rd-name">{{ $entry['room']->name }}</div>
                @if ($priority)<span class="rd-tag {{ $priority['class'] }}">{{ $priority['text'] }}</span>@endif
                <div class="rd-meta">{{ $entry['room']->aseo_reported_at ? 'Aseo terminado, falta confirmar' : 'Sin levantamiento hasta que quede lista' }}</div>
            </div>
        </div>
    @empty
        <p class="rd-empty">Ninguna en aseo.</p>
    @endforelse
    </div>
    </section>

    <section class="mod mod-dark">
        <div class="mod-h"><span>Por revisar</span><b>{{ $pending->count() }}</b></div>
        <div class="mod-b rd-grid">
    @forelse ($pending as $room)
        <a class="rd-card" href="{{ route('rooms.qr.show', $room) }}?ronda=1">
            <div>
                <div class="rd-name">{{ $room->name }}</div>
                <div class="rd-meta">{{ $room->category->name }}</div>
            </div>
            <span class="rd-go">Ir al Playroom →</span>
        </a>
    @empty
        <p class="rd-empty">{{ $total > 0 ? 'No queda ninguna otra por revisar hoy.' : 'No hay Playrooms listas en este momento.' }}</p>
    @endforelse
    </div>
    </section>

    <section class="mod mod-gray">
        <div class="mod-h"><span>Fuera de servicio</span><b>{{ $out->count() }}</b></div>
        <div class="mod-b rd-grid">
    @forelse ($out as $entry)
        <div class="rd-card">
            <div>
                <div class="rd-name">{{ $entry['room']->name }}</div>
                <div class="rd-meta">{{ \App\Services\Rooms\RoomClosingLog::STATUS_LABELS[$entry['room']->operational_status] }}@if ($entry['room']->operational_note) · {{ $entry['room']->operational_note }}@endif</div>
            </div>
        </div>
    @empty
        <p class="rd-empty">Ninguna fuera de servicio.</p>
    @endforelse
    </div>
    </section>

    @if ($done->isNotEmpty())
        <section class="mod mod-soft">
            <div class="mod-h"><span>Ya revisadas hoy</span><b>{{ $done->count() }}</b></div>
            <div class="mod-b rd-grid">
        @foreach ($done as $room)
            <div class="rd-card">
                <div>
                    <div class="rd-name">{{ $room->name }}</div>
                    <div class="rd-meta">{{ $room->latestInspection->created_at->timezone('America/Santiago')->format('H:i') }} · {{ $room->latestInspection->inspected_by }}</div>
                </div>
                @if ($room->latestInspection->needs_maintenance)
                    <span class="rd-warn">Necesita mantención</span>
                @else
                    <span class="rd-meta">✓</span>
                @endif
            </div>
        @endforeach
        </div>
        </section>
    @endif
</div>
</body>
</html>
