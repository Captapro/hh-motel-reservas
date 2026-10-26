<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Ronda de turno — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; letter-spacing: .04em; margin-bottom: 2px; }
        .sub { color:#999; font-size: 13px; margin:0 0 20px; }
        .progress-card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:16px 18px; margin-bottom:20px; color:#eee; }
        .progress-card .big { font-size:22px; font-weight:800; }
        .progress-card .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
        .progress-bar { height:8px; background:#292929; border-radius:20px; margin-top:12px; overflow:hidden; }
        .progress-bar .fill { height:100%; background:#ff7918; border-radius:20px; }
        .all-done { background:#1c2f1c; border-color:#2e5a2e; color:#8fe0ad; }
        h2.section { font-size:13px; margin:26px 0 10px; color:#999; text-transform:uppercase; letter-spacing:.04em; }
        a.room-card { display:flex; justify-content:space-between; align-items:center; background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; margin-bottom:9px; text-decoration:none; color:#eee; }
        a.room-card:hover { border-color:#ff7918; }
        a.room-card .name { font-weight:700; font-size:14.5px; }
        a.room-card .cat { color:#999; font-size:12px; margin-top:2px; }
        a.room-card .go { color:#ff9a4a; font-size:13px; font-weight:600; }
        .done-card { display:flex; justify-content:space-between; align-items:center; background:#161c16; border:1px solid #2a3a2a; border-radius:10px; padding:12px 16px; margin-bottom:8px; color:#9fcf9f; opacity:.85; }
        .done-card .name { font-weight:700; font-size:14px; color:#cde; }
        .done-card .when { font-size:11.5px; color:#7fa87f; }
        .warn-tag { color:#e88a9a; font-size:11px; font-weight:700; margin-left:8px; }
        .empty { color:#777; padding:20px 0; text-align:center; font-size:14px; }
        a.scan-btn { display:block; text-align:center; background:#ff7918; color:#21170e; text-decoration:none; font-weight:800; font-size:15px; padding:14px; border-radius:10px; margin-bottom:20px; }
        a.scan-btn:hover { background:#ee6909; }
        .rg { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:10px; }
        .rg > a.room-card, .rg > .done-card { margin-bottom:0; }
        .rg > .empty { grid-column:1 / -1; }
    </style>
</head>
<body>
@include('partials.navbar')
<div class="page-inner">
    @include('partials.aseo-tabs')
    <h1>HH MOTEL — Ronda de turno</h1>
    <p class="sub">Recorré cada Playroom disponible ahora y dejá la pauta de inspección al día.</p>

    @if (session('status'))
        <div class="progress-card all-done" style="margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="progress-card {{ $pending->isEmpty() && $total > 0 ? 'all-done' : '' }}">
        <div class="label">Progreso de hoy</div>
        <div class="big">{{ $done->count() }} de {{ $total }} revisadas</div>
        <div class="progress-bar"><div class="fill" style="width:{{ $total > 0 ? round($done->count() / $total * 100) : 0 }}%;"></div></div>
    </div>

    <a class="scan-btn" href="{{ route('shift_round.scan') }}?ronda=1">📷 Escanear QR de la habitación →</a>

    <h2 class="section">Pendientes ({{ $pending->count() }})</h2>
    <div class="rg">
    @forelse ($pending as $room)
        <a class="room-card" href="{{ route('rooms.qr.show', $room) }}?ronda=1">
            <div>
                <div class="name">{{ $room->name }}</div>
                <div class="cat">{{ $room->category->name }}</div>
            </div>
            <div class="go">Ir a la habitación →</div>
        </a>
    @empty
        <p class="empty">@if($total > 0) ¡Ronda completa! No queda ninguna disponible sin revisar hoy. @else No hay Playrooms disponibles en este momento (todas ocupadas, en aseo o inactivas). @endif</p>
    @endforelse
    </div>

    @if ($done->isNotEmpty())
        <h2 class="section">Ya revisadas hoy ({{ $done->count() }})</h2>
        <div class="rg">
        @foreach ($done as $room)
            <div class="done-card">
                <div>
                    <div class="name">{{ $room->name }} <span class="cat" style="margin-left:6px;">{{ $room->category->name }}</span></div>
                    <div class="when">{{ $room->latestInspection->created_at->timezone('America/Santiago')->format('H:i') }} · {{ $room->latestInspection->inspected_by }}</div>
                </div>
                @if ($room->latestInspection->needs_maintenance)
                    <span class="warn-tag">⚠ Necesita mantención</span>
                @else
                    <span>✓</span>
                @endif
            </div>
        @endforeach
        </div>
    @endif
</div>
</body>
</html>
