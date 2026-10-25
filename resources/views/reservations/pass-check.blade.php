<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Pase {{ $booking->code }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin:0; }
        .wrap { max-width:460px; margin:0 auto; padding:28px 20px 50px; text-align:center; }
        .badge { width:84px; height:84px; border-radius:50%; margin:6px auto 14px; display:flex; align-items:center; justify-content:center; font-size:46px; font-weight:800; }
        .ok .badge { background:#12331f; color:#6fd39a; border:2px solid #2e6e45; }
        .bad .badge { background:#3a1c1c; color:#f3b8b8; border:2px solid #7a2d2d; }
        h1 { font-size:24px; margin:0 0 4px; }
        .sub { color:#9a9a9a; font-size:14px; margin:0 0 22px; }
        .card { text-align:left; background:#1c1c1c; border:1px solid #333; border-radius:14px; padding:6px 18px; margin-bottom:16px; }
        .row { display:flex; justify-content:space-between; gap:14px; padding:11px 0; border-top:1px solid #2a2a2a; font-size:15px; }
        .row:first-child { border-top:none; }
        .row span:first-child { color:#9a9a9a; }
        .row b { text-align:right; }
        .row.warn b { color:#f0b27a; }
        .pc { background:#0f2b22; border:1px solid #1c9169; color:#6ee7b7; border-radius:12px; padding:14px 16px; font-size:15px; font-weight:600; line-height:1.4; }
        .late { background:#2a2410; border:1px solid #6b5a1e; color:#e8c76f; border-radius:12px; padding:12px 14px; font-size:14px; margin-bottom:16px; text-align:left; }
        .here { display:inline-block; margin-top:22px; color:#777; font-size:13px; text-decoration:underline; }
    </style>
</head>
<body>
    @php
        $tz = 'America/Santiago';
        $balance = $booking->balanceDue();
        $reason = match (true) {
            $booking->checked_in_at !== null && $booking->booking_status !== 'FINALIZADA' => 'Este huésped ya hizo check-in.',
            $booking->booking_status === 'FINALIZADA' => 'Esta reserva ya está finalizada.',
            in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW'], true) => 'Esta reserva está '.strtolower(str_replace('_', ' ', $booking->booking_status)).'.',
            default => null,
        };
        $notToday = ! $booking->starts_at->timezone($tz)->isToday();
    @endphp
    <div class="wrap {{ $valid ? 'ok' : 'bad' }}">
        <div class="badge">{{ $valid ? '✓' : '!' }}</div>
        <h1>{{ $valid ? 'Pase válido' : 'Pase no válido' }}</h1>
        <p class="sub">{{ $valid ? $booking->code : $reason }}</p>

        <div class="card">
            <div class="row"><span>Cliente</span><b>{{ $booking->customer->name }}</b></div>
            <div class="row"><span>Habitación</span><b>{{ $booking->room->name }}</b></div>
            <div class="row"><span>Horario</span><b>{{ $booking->starts_at->timezone($tz)->format('d/m H:i') }} — {{ $booking->ends_at->timezone($tz)->format('H:i') }}</b></div>
            <div class="row"><span>Personas</span><b>{{ $booking->guests_count }}</b></div>
            @if ($valid)
                <div class="row {{ $balance > 0 ? 'warn' : '' }}"><span>Saldo</span><b>{{ $balance > 0 ? '$'.number_format($balance, 0, ',', '.').' por cobrar' : 'Pagado' }}</b></div>
            @endif
        </div>

        @if ($valid)
            @if ($notToday)
                <div class="late">⚠ Esta reserva es para el {{ $booking->starts_at->timezone($tz)->format('d/m') }}, no para hoy.</div>
            @endif
            <div class="pc">Listo. Recepción ya tiene el aviso en el computador: ahí se {{ $balance > 0 ? 'cobra y se ' : '' }}hace el check-in. No necesitas hacer nada en el celular.</div>
            <a class="here" href="{{ route('bookings.validate', ['code' => $booking->code, 'ir' => 1]) }}">No tengo computador cerca: hacerlo desde este celular</a>
        @else
            <a class="here" href="{{ route('reservations.show', $booking->code) }}">Ver la ficha de la reserva</a>
        @endif
    </div>
</body>
</html>
