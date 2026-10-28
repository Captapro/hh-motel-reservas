<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->name }} — HH Motel</title>
    <style>
        :root {
            color-scheme: light;
            --hh-accent: #ff7918;
            --hh-accent-hover: #ee6909;
            --hh-canvas: #f5f5f4;
            --hh-surface: #353638;
            --hh-ink: #202124;
        }
        * { box-sizing: border-box; }
        body { background:var(--hh-canvas); color:var(--hh-ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; line-height: 1.45; }
        .wrap { max-width: 460px; margin: 0 auto; padding: 32px 18px 50px; }
        .brand { font-size: 13px; font-weight: 800; letter-spacing: .1em; color: var(--hh-accent); text-align:center; margin-bottom: 16px; }
        .card { background:var(--hh-surface); color:#f5f5f5; border-radius:14px; padding:22px; box-shadow: 0 3px 10px #1011120b; }
        h1 { font-size: 22px; margin: 0 0 4px; text-align:center; }
        p.cat { text-align:center; color:#9edaff; font-size:13px; margin:0 0 20px; }
        .status-badge { display:block; text-align:center; padding:10px; border-radius:9px; font-weight:800; font-size:13.5px; letter-spacing:.02em; margin-bottom:18px; }
        .status-badge.activa { background:#1c3a2a; color:#6fd39a; }
        .status-badge.aseo { background:#2a2010; color:#e8a23f; }
        .status-badge.mantencion, .status-badge.inactiva { background:#3a1c1c; color:#f3b8b8; }
        .banner { border-radius:9px; padding:12px 14px; margin-bottom:18px; font-size:13.5px; }
        .banner.ok { background:#1c3a2a; color:#6fd39a; }
        .banner.error { background:#3a1c1c; color:#f3b8b8; }
        label { display:block; font-size:12.5px; color:#c1c3c7; margin:0 0 8px; }
        select { width:100%; background:#202123; border:1px solid #62656b; color:#f4f5f7; padding:13px 12px; border-radius:8px; font-size:16px; min-height:48px; }
        button.submit { width:100%; margin-top:16px; background:var(--hh-accent); color:#21170e; border:none; padding:15px; border-radius:9px; font-size:15.5px; font-weight:800; cursor:pointer; }
        button.submit:hover { background:var(--hh-accent-hover); }
        .info { color:#c1c3c7; font-size:13px; text-align:center; margin-top:6px; }
        .staff-links { margin-top:20px; display:flex; flex-direction:column; gap:10px; }
        .staff-links a, .staff-links button.link-btn {
            display:block; width:100%; box-sizing:border-box; text-align:center; text-decoration:none;
            padding:13px; border-radius:9px; font-size:14.5px; font-weight:700; cursor:pointer;
            border:1px solid #62656b; color:#f4f5f7; background:#202123; font-family:inherit;
        }
        .staff-links a:hover, .staff-links button.link-btn:hover { border-color:var(--hh-accent); color:var(--hh-accent); }
        .staff-links a.primary, .staff-links button.link-btn.primary { background:var(--hh-accent); color:#21170e; border-color:var(--hh-accent); }
        .staff-links a.primary:hover, .staff-links button.link-btn.primary:hover { background:var(--hh-accent-hover); color:#21170e; }
        .reported-box { background:#1c2f1c; border:1px solid #2e5a2e; color:#8fe0ad; border-radius:9px; padding:14px; text-align:center; font-size:14px; }
        .occupied-box { background:#1c2733; border:1px solid #2e4a5a; color:#8fc7e0; border-radius:9px; padding:14px; text-align:center; font-size:14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">HH MOTEL</div>
        <div class="card">
            <h1>{{ $room->name }}</h1>
            <p class="cat">Categoría {{ $room->category->name }}</p>

            <span class="status-badge {{ $room->operational_status }}">
                {{ match($room->operational_status) {
                    'activa' => '● Activa',
                    'aseo' => '◐ En aseo',
                    'mantencion' => '✕ En mantención',
                    'inactiva' => '✕ Inactiva',
                    default => strtoupper($room->operational_status),
                } }}
            </span>

            @if (session('status'))
                <div class="banner ok">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    <div class="banner error">{{ $error }}</div>
                @endforeach
            @endif

            @if ($room->operational_status === 'aseo')
                @if ($room->aseo_reported_at)
                    <div class="reported-box">
                        ✓ Reportada por <strong>{{ $room->aseo_reported_by }}</strong> a las {{ $room->aseo_reported_at->timezone('America/Santiago')->format('H:i') }}.<br>
                        Recepción todavía tiene que confirmarla para volver a quedar disponible.
                    </div>
                @elseif ($mucama)
                    <form method="POST" action="{{ route('rooms.qr.report_aseo', $room) }}">
                        @csrf
                        <p class="info" style="margin-top:0;">Conectada como <strong>{{ $mucama->name }}</strong></p>
                        <button class="submit" type="submit">Reportar aseo listo →</button>
                    </form>
                    <p class="info">Recepción confirma después de este aviso -- el Playroom no vuelve a disponible solo.</p>
                @else
                    <p class="info">Inicia sesión con tu PIN para poder reportar el aseo de este Playroom.</p>
                    <a class="submit" style="display:block; text-align:center; text-decoration:none;" href="{{ route('mucamas.login', ['next' => route('rooms.qr.show', $room)]) }}">Iniciar sesión →</a>
                @endif
            @elseif ($room->operational_status === 'activa' && $nextBooking)
                @if ($nextBooking->room_checked_at)
                    <div class="reported-box">
                        ✓ Confirmada por <strong>{{ $nextBooking->room_checked_by }}</strong> a las {{ $nextBooking->room_checked_at->timezone('America/Santiago')->format('H:i') }}, para la reserva de las {{ $nextBooking->starts_at->timezone('America/Santiago')->format('H:i') }}.
                    </div>
                @elseif ($mucama)
                    <p class="info" style="margin-top:0;">Hay una reserva a las <strong>{{ $nextBooking->starts_at->timezone('America/Santiago')->format('H:i') }}</strong> -- ¿revisaste que el Playroom esté en condiciones?</p>
                    <form method="POST" action="{{ route('rooms.qr.confirm_ready', $room) }}">
                        @csrf
                        <p class="info">Conectada como <strong>{{ $mucama->name }}</strong></p>
                        <button class="submit" type="submit">Confirmar que está en condiciones →</button>
                    </form>
                @else
                    <p class="info">Hay una reserva a las <strong>{{ $nextBooking->starts_at->timezone('America/Santiago')->format('H:i') }}</strong> -- inicia sesión con tu PIN para confirmar que el Playroom está en condiciones.</p>
                    <a class="submit" style="display:block; text-align:center; text-decoration:none;" href="{{ route('mucamas.login', ['next' => route('rooms.qr.show', $room)]) }}">Iniciar sesión →</a>
                @endif
            @elseif ($room->operational_status === 'activa' && $occupancy === 'ocupada')
                <div class="occupied-box">🔵 Ocupada ahora mismo -- no se puede inspeccionar con el huésped adentro.</div>
            @else
                <p class="info">Este Playroom no está esperando aseo ni tiene una próxima reserva por confirmar ahora mismo.</p>
            @endif

            @if ($mucama)
                <form method="POST" action="{{ route('mucamas.logout') }}" style="text-align:center; margin-top:14px;">
                    @csrf
                    <button type="submit" style="background:none; border:none; color:#62656c; font-size:12.5px; cursor:pointer; text-decoration:underline;">Cerrar sesión ({{ $mucama->name }})</button>
                </form>
            @endif

            @auth
                <div class="staff-links">
                    @if ($room->operational_status === 'activa' && $occupancy === 'ocupada')
                        <form method="POST" action="{{ route('rooms.qr.acknowledge_occupied', $room) }}">
                            @csrf
                            @if (request('ronda'))
                                <input type="hidden" name="from_ronda" value="1">
                            @endif
                            <button class="link-btn primary" type="submit">
                                {{ request('ronda') ? 'Marcar ocupada y seguir la ronda →' : 'Marcar como ocupada →' }}
                            </button>
                        </form>
                    @elseif ($hasValidScan)
                        <a class="primary" href="{{ route('rooms.inspections.create', $room) }}?qr=1{{ request('ronda') ? '&ronda=1' : '' }}">Hacer inspección completa →</a>
                    @else
                        <a class="primary" href="{{ route('shift_round.scan') }}{{ request('ronda') ? '?ronda=1' : '' }}">📷 Escanear para inspeccionar →</a>
                    @endif
                    <a href="{{ route('rooms.board') }}">← Ver tablero interno</a>
                </div>
            @endauth
        </div>
    </div>
</body>
</html>
