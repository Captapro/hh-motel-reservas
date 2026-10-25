<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Reservas — {{ $room->name }} — HH Motel</title>
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
        a.back-btn:hover, button.back-btn:hover { border-color:#ff7918; color:#ff7918; }
        button.back-btn { cursor:pointer; font-family:inherit; }
        .top-actions { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
        .top-actions .back-btn { margin-bottom:0; }
        .sec-label { font-size: 11px; color:#888; text-transform:uppercase; letter-spacing:.05em; margin: 22px 0 10px; }
        .sec-label:first-of-type { margin-top:0; }
        a.row-card { display:block; background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; text-decoration:none; color:#eee; }
        a.row-card.now { border-color:#ff7918; background:#241419; }
        a.row-card:hover { border-color:#666; }
        .row1 { display:flex; justify-content:space-between; align-items:baseline; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 12.5px; }
        .when { font-weight:600; font-size:14.5px; }
        .pill { display:inline-block; font-size:10.5px; font-family: ui-monospace, monospace; padding:2px 8px; border-radius:20px; background:#2a2a2a; color:#ccc; }
        .meta { color:#999; font-size:13px; margin-top:4px; }
        .empty { color:#777; font-size:14px; padding: 4px 0 20px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner hh-room-history">
    <div class="top-actions">
        <a class="back-btn" href="{{ route('rooms.board') }}">← Volver al tablero</a>
        <button type="button" class="back-btn" id="copy-room-link" data-link="{{ route('catalog.room', $room) }}">📋 Copiar link de la ficha</button>
        <a class="back-btn" href="{{ route('catalog.room', $room) }}" target="_blank" rel="noopener">Ver ficha ↗</a>
    </div>
    <h1>{{ $room->name }} — reservas</h1>
    <p class="sub">Categoría {{ $room->category->name }} · <a href="{{ route('reservations.create', ['room_id' => $room->id]) }}">nueva reserva aquí</a></p>

    <div class="sec-label">Hoy y próximas</div>
    @forelse ($upcoming as $booking)
        @php $isNow = $booking->starts_at <= $now && $booking->ends_at > $now; @endphp
        <a class="row-card {{ $isNow ? 'now' : '' }}" href="{{ route('reservations.show', $booking->code) }}">
            <div class="row1">
                <span class="when">{{ ucfirst($booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('dddd D/MM, H:mm')) }}</span>
                <span class="pill">{{ $isNow ? 'OCUPANDO AHORA' : $booking->booking_status }}</span>
            </div>
            <div class="meta">{{ $booking->customer->name }} · {{ $booking->duration_minutes / 60 }} h · <span class="code">{{ $booking->code }}</span></div>
        </a>
    @empty
        <div class="hh-history-empty">
            <h2>Esta habitación no tiene reservas próximas</h2>
            <p>Puedes ingresar una reserva y consultar aquí su horario y estado.</p>
            <a class="btn" href="{{ route('reservations.create', ['room_id' => $room->id]) }}">+ Reservar {{ $room->name }}</a>
        </div>
    @endforelse

    @if ($past->isNotEmpty())
        <div class="sec-label">Historial reciente</div>
        @foreach ($past as $booking)
            <a class="row-card" href="{{ route('reservations.show', $booking->code) }}">
                <div class="row1">
                    <span class="when">{{ ucfirst($booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('dddd D/MM, H:mm')) }}</span>
                    <span class="pill">{{ $booking->booking_status }}</span>
                </div>
                <div class="meta">{{ $booking->customer->name }} · {{ $booking->duration_minutes / 60 }} h · <span class="code">{{ $booking->code }}</span></div>
            </a>
        @endforeach
    @endif
    </div>
    <script>
        // Copia el link público de la ficha de esta habitación, para pegarlo en WhatsApp.
        (function () {
            const btn = document.getElementById('copy-room-link');
            if (!btn) return;
            const original = btn.textContent;
            const show = (ok) => { btn.textContent = ok ? '¡Copiado!' : 'No se pudo copiar'; setTimeout(() => { btn.textContent = original; }, 1800); };
            btn.addEventListener('click', function () {
                const url = btn.dataset.link;
                const fallback = () => {
                    const tmp = document.createElement('input');
                    tmp.value = url; document.body.appendChild(tmp); tmp.select();
                    try { show(document.execCommand('copy')); } catch (e) { show(false); }
                    document.body.removeChild(tmp);
                };
                if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(url).then(() => show(true)).catch(fallback);
                else fallback();
            });
        })();
    </script>
</body>
</html>
