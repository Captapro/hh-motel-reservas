<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Reserva confirmada — HH Motel</title>
    @include('catalog._app-head')
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
        .wrap { max-width: 440px; margin: 0 auto; padding: 24px 16px calc(40px + var(--safe-b)); text-align:center; }
        .check { width:56px; height:56px; border-radius:50%; background:#1c3a2a; color:#6fd39a; display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:800; margin: 0 auto 18px; }
        h1 { font-size: 21px; margin: 0 0 8px; letter-spacing:-.02em; }
        p.sub { color:#62656c; font-size: 14px; margin: 0 0 26px; }
        .card { background:var(--hh-surface); color:#f5f5f5; border-radius: 12px; box-shadow: 0 3px 10px #1011120b; padding: 20px 22px; text-align:left; margin-bottom: 22px; }
        .row { display:flex; justify-content:space-between; padding: 7px 0; font-size: 14px; border-bottom: 1px solid #505154; }
        .row:last-child { border-bottom:none; }
        .row span.muted { color:#c1c3c7; }
        .code { font-family: ui-monospace, monospace; color:var(--hh-accent); font-size: 16px; font-weight:700; }
        .status-badge { display:inline-flex; background:#3a2a12; color:#ffd699; border:1px solid #7a5a1f; border-radius:20px; padding:3px 10px; font-size:11.5px; font-weight:700; }
        .discount-row span:last-child { color:#6ee7b7; }
        .next-steps { background:#fff; border:1px solid #e2e2df; border-radius:10px; padding:14px 16px; margin-bottom:20px; text-align:left; font-size:13px; color:#42464e; }
        .next-steps strong { display:block; margin-bottom:6px; font-size:13.5px; }
        .next-steps ul { margin:0; padding-left:18px; }
        .next-steps li { margin-bottom:4px; }
        .qr-box { background:#fff; border:1px solid #e2e2df; border-radius:12px; padding:18px; margin-bottom:20px; text-align:center; }
        .qr-box img { width:170px; height:170px; image-rendering:pixelated; }
        .qr-box strong { display:block; margin:10px 0 2px; font-size:14px; color:#242424; }
        .qr-box small { color:#62656c; font-size:12.5px; }
        .qr-box a.pass { display:inline-block; margin-top:12px; color:#b4510d; font-weight:700; font-size:13.5px; text-decoration:none; }
        .pay-box { background:#fff; border:1px solid #e2e2df; border-radius:10px; padding:14px 16px; margin-bottom:20px; text-align:left; font-size:13.5px; color:#42464e; }
        .pay-box strong { display:block; margin-bottom:8px; font-size:14px; }
        .pay-box .line { display:flex; justify-content:space-between; gap:12px; padding:5px 0; border-bottom:1px solid #eeeeea; }
        .pay-box .line:last-of-type { border-bottom:none; }
        .pay-box .line span:first-child { color:#62656c; }
        .pay-box a.mp { display:block; text-align:center; margin-top:10px; background:#e8f1ff; color:#1a4fa0; padding:11px; border-radius:8px; font-weight:700; text-decoration:none; }
        .pay-box small { display:block; margin-top:10px; color:#62656c; }
        a.btn { display:block; background:var(--hh-accent); color:#21170e; text-decoration:none; padding: 13px; border-radius: 9px; font-weight:750; font-size:14.5px; }
        a.btn:hover { background:var(--hh-accent-hover); }
        a.back { display:block; margin-top:14px; color:#62656c; text-decoration:none; font-size:13px; }
    </style>
</head>
<body>
    @include('catalog._app-bar', ['appBack' => route('catalog.index'), 'appTitle' => 'Mi reserva'])
    <div class="wrap">
        <div class="check">✓</div>
        @php
            $expired = $booking->booking_status === 'EXPIRADA';
            $confirmed = in_array($booking->booking_status, ['CONFIRMADA', 'CHECK_IN', 'FINALIZADA'], true);
            $holdOpen = $booking->booking_status === 'PENDIENTE_PAGO' && $booking->expires_at && $booking->expires_at->isFuture();
        @endphp
        <h1>{{ $expired ? 'Reserva vencida' : ($confirmed ? '¡Reserva confirmada!' : '¡Reserva creada!') }}</h1>
        <p class="sub">{{ $expired ? 'No recibimos el pago a tiempo y la habitación se liberó.' : 'Guarda el código — te lo vamos a pedir al llegar.' }}</p>
        @if ($holdOpen)
            <div class="next-steps" style="background:#3a2a12; border-color:#7a5a1f; color:#ffd699;">⏳ Tu habitación está retenida hasta las <b>{{ $booking->expires_at->timezone('America/Santiago')->format('H:i') }}</b> ({{ \App\Models\Booking::HOLD_MINUTES }} minutos). Realiza el pago antes de ese horario para confirmarla; si no, se libera.</div>
        @elseif ($expired)
            <style>.qr-box, .pay-box { display:none; }</style>
            <div class="next-steps" style="background:#3a1c1c; border-color:#7a2d2d; color:#f3b8b8;">Puedes hacer una nueva reserva cuando quieras: <a href="{{ route('catalog.reserve') }}" style="color:#ffd699;">reservar de nuevo →</a></div>
        @endif

        @if (session('warning'))
            <div class="next-steps" style="background:#3a2a12; border-color:#7a5a1f; color:#ffd699;">{{ session('warning') }}</div>
        @endif

        <div class="card">
            <div class="row"><span class="muted">Código</span><span class="code">{{ $booking->code }}</span></div>
            <div class="row"><span class="muted">Estado</span><span class="status-badge">{{ $expired ? 'Vencida' : ($confirmed ? 'Confirmada' : 'Pendiente de pago') }}</span></div>
            <div class="row"><span class="muted">Habitación</span><span>{{ $booking->room->name }} · {{ $booking->room->category->name }}</span></div>
            <div class="row"><span class="muted">Fecha</span><span>{{ $booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('D [de] MMMM, HH:mm') }}</span></div>
            <div class="row"><span class="muted">Duración</span><span>{{ $booking->duration_minutes >= 60 ? intdiv($booking->duration_minutes, 60).' h' : $booking->duration_minutes.' min' }}</span></div>
            @if ($booking->discount_amount > 0)
                <div class="row discount-row"><span class="muted">Descuento aplicado{{ $booking->coupon_code_snapshot ? ' ('.$booking->coupon_code_snapshot.')' : '' }}</span><span>-${{ number_format($booking->discount_amount, 0, ',', '.') }}</span></div>
            @endif
            @foreach ($booking->addons as $addon)
                <div class="row"><span class="muted">{{ $addon->description }}</span><span>${{ number_format($addon->amount, 0, ',', '.') }}</span></div>
            @endforeach
            @if ($confirmed && $booking->balanceDue() <= 0)
                <div class="row"><span class="muted">Total pagado</span><span>${{ number_format($booking->paidAmount(), 0, ',', '.') }}</span></div>
            @else
                <div class="row"><span class="muted">Total a pagar</span><span>${{ number_format($booking->balanceDue(), 0, ',', '.') }}</span></div>
            @endif
        </div>

        <div class="qr-box">
            <img src="{{ $qrDataUri }}" alt="Código QR de la reserva {{ $booking->code }}">
            <strong>Tu código QR</strong>
            <small>Muéstralo en recepción al llegar. Código {{ $booking->code }}.</small><br>
            <a class="pass" href="{{ $passUrl }}">Descargar mi pase (PDF) ↓</a>
        </div>

        @php
            $hasBank = ! empty($pay['bank_titular']) && ! empty($pay['bank_account_number']);
            $canPayOnline = $mpEnabled && in_array($booking->booking_status, ['PENDIENTE_PAGO', 'CONFIRMADA'], true) && $booking->balanceDue() > 0;
            // Volvió de Mercado Pago pero el aviso todavía no llegó: se refresca solo unos segundos.
            $waitingMp = request('mp') === 'ok' && $booking->booking_status === 'PENDIENTE_PAGO';
        @endphp
        @if ($waitingMp)
            <div class="next-steps" style="background:#12293a; border-color:#243a4c; color:#9edaff;">⏳ Estamos confirmando tu pago con Mercado Pago. Esta página se actualiza sola.</div>
            <meta http-equiv="refresh" content="4">
        @elseif (request('mp') === 'fallo')
            <div class="next-steps" style="background:#3a1c1c; border-color:#7a2d2d; color:#f3b8b8;">El pago no se completó. Puedes intentarlo de nuevo o pagar por transferencia.</div>
        @endif
        @if ($canPayOnline)
            <div class="pay-box">
                <strong>Paga ahora y confirma tu reserva — ${{ number_format($booking->balanceDue(), 0, ',', '.') }}</strong>
                <a class="mp" href="{{ route('catalog.pay', $booking->pass_token) }}">Pagar con Mercado Pago →</a>
                <small>Pago seguro en Mercado Pago. Apenas se aprueba, tu reserva queda confirmada.</small>
            </div>
        @endif
        @if (! $canPayOnline && ($hasBank || ! empty($pay['mercadopago_link'])))
            <div class="pay-box">
                <strong>Para asegurar tu reserva — ${{ number_format($booking->balanceDue(), 0, ',', '.') }}</strong>
                @if ($hasBank)
                    <div class="line"><span>Titular</span><span>{{ $pay['bank_titular'] }}</span></div>
                    @if (! empty($pay['bank_rut']))<div class="line"><span>RUT</span><span>{{ $pay['bank_rut'] }}</span></div>@endif
                    @if (! empty($pay['bank_name']))<div class="line"><span>Banco</span><span>{{ $pay['bank_name'] }}</span></div>@endif
                    <div class="line"><span>{{ $pay['bank_account_type'] ?: 'Cuenta' }}</span><span>{{ $pay['bank_account_number'] }}</span></div>
                    @if (! empty($pay['bank_email']))<div class="line"><span>Correo</span><span>{{ $pay['bank_email'] }}</span></div>@endif
                @endif
                @if (! empty($pay['mercadopago_link']))
                    <a class="mp" href="{{ $pay['mercadopago_link'] }}" target="_blank" rel="noopener">Pagar con Mercado Pago →</a>
                @endif
                <small>Envíanos el comprobante por WhatsApp indicando tu código {{ $booking->code }}.</small>
            </div>
        @endif

        @unless ($expired)
        <div class="next-steps">
            <strong>¿Qué sigue ahora?</strong>
            <ul>
                @if ($confirmed)
                    <li>{{ $booking->balanceDue() > 0 ? 'Tu reserva está confirmada. Queda un saldo de $'.number_format($booking->balanceDue(), 0, ',', '.').' por pagar al llegar al motel.' : 'Tu pago está registrado y tu reserva está confirmada.' }}</li>
                    <li>Muestra el código QR o el código {{ $booking->code }} en recepción al llegar.</li>
                @elseif ($holdOpen)
                    <li>Paga antes de las {{ $booking->expires_at->timezone('America/Santiago')->format('H:i') }} para confirmar tu reserva; si no, la habitación se libera.</li>
                    <li>Apenas se registre tu pago, la reserva queda confirmada.</li>
                @else
                    <li>Esta reserva queda pendiente hasta que se registre tu pago.</li>
                @endif
                <li>Presenta tu documento de identidad al llegar.</li>
            </ul>
        </div>
        @endunless

        <a class="back" href="{{ route('catalog.index') }}">← Volver al catálogo</a>
    </div>
</body>
</html>
