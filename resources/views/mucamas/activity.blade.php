<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <meta http-equiv="refresh" content="60">
    <title>Dónde están las mucamas — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 760px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; letter-spacing: .04em; margin-bottom: 2px; }
        .sub { color:#999; font-size: 13px; margin:0 0 22px; }
        /* hh-theme.css (inyectado por partials.navbar) fuerza "body { color:
           var(--hh-ink) !important }" (oscuro, pensado para el fondo claro
           de esta misma página) -- el !important gana la herencia para
           cualquier texto que no fije su propio color, así que esta tarjeta
           oscura necesita el suyo explícito en vez de heredar del body. */
        .mucama-card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:16px 18px; margin-bottom:10px; color:#eee; }
        .mucama-row { display:flex; align-items:center; justify-content:space-between; gap:14px; }
        .mucama-row .who strong { display:block; font-size:15px; }
        .mucama-row .who small { color:#999; font-size:12px; }
        .mucama-row .where { text-align:right; }
        .mucama-row .where .room { font-weight:700; font-size:14px; }
        .mucama-row .where .ago { font-size:11.5px; color:#999; margin-top:2px; }
        .status-tag { display:inline-block; margin-top:5px; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; padding:3px 9px; border-radius:20px; }
        .status-tag.working { background:#2a2010; color:#e8a23f; }
        .status-tag.reported { background:#0f2b22; color:#6ee7b7; }
        .status-tag.done { background:#1a2a1a; color:#7fbf8f; }
        .status-tag.none { background:#262626; color:#888; }
        .shift-tag { display:inline-block; margin-top:4px; font-size:11px; }
        .shift-tag.on { color:#6ee7b7; }
        .shift-tag.off { color:#777; }
        .empty { color:#777; padding:30px; text-align:center; }
        h2.section { font-size:14px; margin:30px 0 12px; color:#999; text-transform:uppercase; letter-spacing:.04em; }
        table.log-table { width:100%; border-collapse:collapse; font-size:13px; }
        table.log-table th { text-align:left; color:#888; font-weight:500; font-size:11px; text-transform:uppercase; padding:0 10px 8px 0; }
        table.log-table td { padding:8px 10px 8px 0; border-top:1px solid #292929; }
        table.log-table td.open { color:#1a7a4c; font-weight:700; }
        .route-trail { margin-top:8px; font-size:11.5px; color:#8fbfff; }
        .toast-wrap { position:fixed; top:16px; right:16px; z-index:50; display:flex; flex-direction:column; gap:8px; }
        .toast { background:#1c2f1c; border:1px solid #2e5a2e; color:#8fe0ad; padding:12px 16px; border-radius:9px; font-size:13.5px; box-shadow:0 6px 18px #0006; animation:hh-toast-in .2s ease; max-width:280px; }
        @keyframes hh-toast-in { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    </style>
</head>
<body>
@include('partials.navbar')
<div class="toast-wrap" id="toast-wrap"></div>
<div class="page-inner">
    <h1>HH MOTEL — Dónde están las mucamas</h1>
    <p class="sub">Solo las mucamas en turno ahora · última habitación que cada una escaneó por QR y cuándo · se avisa solo, sin recargar · <a href="{{ route('mucamas.activity') }}" style="color:#ff7918;">actualizar ahora</a></p>

    @forelse ($mucamas as $mucama)
        <div class="mucama-card">
            <div class="mucama-row">
                <div class="who">
                    <strong>{{ $mucama->name }}</strong>
                    @if ($mucama->shiftLogs->isNotEmpty())
                        <span class="shift-tag on">🟢 En turno desde {{ $mucama->shiftLogs->first()->started_at->timezone('America/Santiago')->format('H:i') }}</span>
                    @else
                        <span class="shift-tag off">⚪ Fuera de turno</span>
                    @endif
                    @if (!$mucama->pin)
                        <small style="display:block; color:#e88a9a;">Sin PIN asignado -- no puede entrar al panel</small>
                    @endif
                </div>
                <div class="where">
                    @if ($mucama->lastQrRoom)
                        <div class="room">{{ $mucama->lastQrRoom->name }} <span style="color:#999; font-weight:500;">· {{ $mucama->lastQrRoom->category->name }}</span></div>
                        <div class="ago">hace {{ $mucama->last_qr_seen_at->diffForHumans(null, true) }}</div>
                        @if ($mucama->lastQrRoom->operational_status === 'aseo' && $mucama->lastQrRoom->aseo_reported_at)
                            <span class="status-tag reported">Reportó, falta confirmar</span>
                        @elseif ($mucama->lastQrRoom->operational_status === 'aseo')
                            <span class="status-tag working">Trabajando en esto</span>
                        @else
                            <span class="status-tag done">Ya liberada</span>
                        @endif
                    @else
                        <div class="ago">Todavía no escaneó ninguna habitación</div>
                        <span class="status-tag none">Sin actividad</span>
                    @endif
                </div>
            </div>
            @php $route = $routesByStaff->get($mucama->id); @endphp
            @if ($route && $route->isNotEmpty())
                <div class="route-trail">Historial de hoy: {{ $route->map(fn ($stop) => $stop['room'].' '.$stop['icon'].' '.$stop['label'].' ('.$stop['at'].')')->implode(' → ') }}</div>
            @endif
        </div>
    @empty
        <p class="empty">Ninguna mucama en turno ahora. El turno empieza cuando entra al panel de mucamas con su PIN y termina cuando sale.</p>
    @endforelse

    <h2 class="section">Registro de turnos de hoy</h2>
    @if ($todayLogs->isEmpty())
        <p class="empty">Todavía no hay entradas registradas hoy.</p>
    @else
        <table class="log-table">
            <thead><tr><th>Quién</th><th>Entrada</th><th>Salida</th><th>Duración</th></tr></thead>
            <tbody>
                @foreach ($todayLogs as $log)
                    <tr>
                        <td>{{ $log->staff->name ?? $log->user->name }}{{ $log->user ? ' (anfitrión)' : '' }}</td>
                        <td>{{ $log->started_at->timezone('America/Santiago')->format('H:i') }}</td>
                        <td class="{{ $log->ended_at ? '' : 'open' }}">{{ $log->ended_at ? $log->ended_at->timezone('America/Santiago')->format('H:i') : 'En turno' }}</td>
                        <td>{{ intdiv($log->durationMinutes(), 60) }}h {{ str_pad($log->durationMinutes() % 60, 2, '0', STR_PAD_LEFT) }}min</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<script>
    // Polling liviano (sin websockets/broadcasting) -- cada pocos segundos
    // pregunta si algo cambió; si cambió, avisa con un toast y recién ahí
    // recarga la página entera (así todo el estado derivado -- ruta, tags,
    // etc -- queda consistente en vez de ir parchando el DOM a mano).
    let hhLastSeen = null;
    async function hhPollMucamaStatus() {
        try {
            const res = await fetch('{{ route('mucamas.activity.status') }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (hhLastSeen === null) {
                hhLastSeen = {};
                data.forEach((m) => { hhLastSeen[m.id] = m.seen_at; });
                return;
            }

            let changed = false;
            data.forEach((m) => {
                if (m.seen_at && m.seen_at !== hhLastSeen[m.id]) {
                    changed = true;
                    hhShowToast(m.room ? `${m.name} escaneó ${m.room}` : `${m.name} tuvo actividad`);
                    hhLastSeen[m.id] = m.seen_at;
                }
            });

            if (changed) {
                setTimeout(() => window.location.reload(), 2200);
            }
        } catch (e) { /* red caída o similar -- se reintenta solo en el próximo tick */ }
    }

    function hhShowToast(text) {
        const wrap = document.getElementById('toast-wrap');
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = '🔔 ' + text;
        wrap.appendChild(toast);
        setTimeout(() => toast.remove(), 6000);
    }

    hhPollMucamaStatus();
    setInterval(hhPollMucamaStatus, 8000);
</script>
</body>
</html>
