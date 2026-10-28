<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Próximas reservas — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 640px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom:2px; }
        .sub { color:#999; font-size: 13px; margin-bottom: 22px; }
        .sub a { color:#ff7918; text-decoration:none; }
        a.back-btn { display:inline-block; background:#1c1c1c; border:1px solid #333; color:#ccc; text-decoration:none; padding:8px 13px; border-radius:7px; font-size:12.5px; font-weight:600; margin-bottom:16px; }
        a.back-btn:hover { border-color:#ff7918; color:#ff7918; }
        .sec-label { font-size: 11px; color:#888; text-transform:uppercase; letter-spacing:.05em; margin: 22px 0 10px; }
        .sec-label:first-of-type { margin-top:0; }
        a.row-card { display:block; background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; text-decoration:none; color:#eee; }
        a.row-card.now { border-color:#ff7918; background:#241419; }
        a.row-card:hover { border-color:#666; }
        .row1 { display:flex; justify-content:space-between; align-items:baseline; gap:10px; }
        .room { font-weight:700; font-size:14.5px; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 12.5px; }
        .when { font-weight:600; font-size:14px; }
        .pill { display:inline-block; font-size:10.5px; font-family: ui-monospace, monospace; padding:2px 8px; border-radius:20px; background:#2a2a2a; color:#ccc; white-space:nowrap; }
        .pill.now { background:#3a1c22; color:#e88a9a; }
        .meta { color:#999; font-size:13px; margin-top:4px; }
        .empty { color:#777; font-size:14px; padding: 4px 0 20px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <a class="back-btn" href="{{ route('rooms.board') }}">← Volver al tablero</a>
    <h1>Próximas reservas</h1>
    <p class="sub">Todos los Playrooms</p>

    @php $today = $bookings->filter(fn ($b) => $b->starts_at->isSameDay($now)); @endphp
    @php $later = $bookings->filter(fn ($b) => ! $b->starts_at->isSameDay($now)); @endphp

    <div class="sec-label">Hoy</div>
    @forelse ($today as $booking)
        @php $isNow = $booking->starts_at <= $now && $booking->ends_at > $now; @endphp
        <a class="row-card {{ $isNow ? 'now' : '' }}" href="{{ route('reservations.show', $booking->code) }}">
            <div class="row1">
                <span class="room">{{ $booking->room->name }} <span style="font-weight:400; color:#999;">({{ $booking->room->category->name }})</span></span>
                <span class="pill {{ $isNow ? 'now' : '' }}">{{ $isNow ? 'OCUPANDO AHORA' : $booking->starts_at->timezone('America/Santiago')->format('H:i') }}</span>
            </div>
            <div class="meta">{{ $booking->customer->name }} · {{ $booking->duration_minutes / 60 }} h · <span class="code">{{ $booking->code }}</span></div>
        </a>
    @empty
        <p class="empty">No hay reservas activas ni próximas para hoy.</p>
    @endforelse

    @if ($later->isNotEmpty())
        <div class="sec-label">Más adelante</div>
        @foreach ($later as $booking)
            <a class="row-card" href="{{ route('reservations.show', $booking->code) }}">
                <div class="row1">
                    <span class="room">{{ $booking->room->name }} <span style="font-weight:400; color:#999;">({{ $booking->room->category->name }})</span></span>
                    <span class="when">{{ ucfirst($booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('dddd D/MM, H:mm')) }}</span>
                </div>
                <div class="meta">{{ $booking->customer->name }} · {{ $booking->duration_minutes / 60 }} h · <span class="code">{{ $booking->code }}</span></div>
            </a>
        @endforeach
    @endif
    </div>
</body>
</html>
