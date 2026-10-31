<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Mi reserva — HH Motel</title>
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
        .check { width:56px; height:56px; border-radius:50%; background:#1c3a2a; color:#6fd39a; display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800; margin: 0 auto 18px; }
        h1 { font-size: 23px; margin: 0 0 8px; letter-spacing:-.02em; }
        p.sub { color:#4b4e54; font-size: 16px; margin: 0 0 26px; }
        .card { background:var(--hh-surface); color:#f5f5f5; border-radius: 12px; box-shadow: 0 3px 10px #1011120b; padding: 20px 22px; text-align:left; margin-bottom: 22px; }
        .row { display:flex; justify-content:space-between; padding: 7px 0; font-size: 16px; border-bottom: 1px solid #505154; }
        .row:last-child { border-bottom:none; }
        .row span.muted { color:#c1c3c7; }
        .code { font-family: ui-monospace, monospace; color:var(--hh-accent); font-size: 18px; font-weight:700; }
        .status-badge { display:inline-flex; background:#3a2a12; color:#ffd699; border:1px solid #7a5a1f; border-radius:20px; padding:3px 10px; font-size:14px; font-weight:700; }
        .discount-row span:last-child { color:#6ee7b7; }
        .next-steps { background:#fff; border:1px solid #e2e2df; border-radius:10px; padding:14px 16px; margin-bottom:20px; text-align:left; font-size:15.5px; color:#42464e; }
        .next-steps strong { display:block; margin-bottom:6px; font-size:16px; }
        .next-steps ul { margin:0; padding-left:18px; }
        .next-steps li { margin-bottom:4px; }
        .qr-box { background:#fff; border:1px solid #e2e2df; border-radius:12px; padding:18px; margin-bottom:20px; text-align:center; }
        .qr-box img { width:170px; height:170px; image-rendering:pixelated; }
        .qr-box strong { display:block; margin:10px 0 2px; font-size:16px; color:#242424; }
        .qr-box small { color:#4b4e54; font-size:15px; }
        .qr-box a.pass { display:inline-block; margin-top:12px; color:#b4510d; font-weight:700; font-size:16px; text-decoration:none; }
        .pay-box { background:#fff; border:1px solid #e2e2df; border-radius:10px; padding:14px 16px; margin-bottom:20px; text-align:left; font-size:16px; color:#42464e; }
        .pay-box strong { display:block; margin-bottom:8px; font-size:16px; }
        .pay-box .line { display:flex; justify-content:space-between; gap:12px; padding:5px 0; border-bottom:1px solid #eeeeea; }
        .pay-box .line:last-of-type { border-bottom:none; }
        .pay-box .line span:first-child { color:#4b4e54; }
        .pay-box a.mp { display:block; text-align:center; margin-top:10px; background:#e8f1ff; color:#1a4fa0; padding:11px; border-radius:8px; font-weight:700; text-decoration:none; }
        .pay-box small { display:block; margin-top:10px; color:#4b4e54; }
        a.btn { display:block; background:var(--hh-accent); color:#21170e; text-decoration:none; padding: 13px; border-radius: 9px; font-weight:750; font-size:16.5px; }
        a.btn:hover { background:var(--hh-accent-hover); }
        a.back { display:block; margin-top:14px; color:#4b4e54; text-decoration:none; font-size:15.5px; }
        .pay-hero { background:#fff; border:2px solid var(--hh-accent); border-radius:14px; padding:18px 18px 14px; margin-bottom:20px; box-shadow:0 6px 18px rgba(255,121,24,.15); }
        .pay-timer { display:flex; flex-direction:column; align-items:center; gap:2px; margin-bottom:14px; color:#4b4e54; font-size:15px; }
        .pay-timer b { font-size:34px; line-height:1.1; color:var(--hh-ink); font-variant-numeric:tabular-nums; letter-spacing:.02em; }
        .pay-timer b.urgent { color:#c0392b; }
        a.btn.pay-btn { font-size:18px; padding:15px; border-radius:11px; }
        .pay-hero small { display:block; margin-top:10px; color:#4b4e54; font-size:15px; }
        .qr-pending { background:#fff; border:1px dashed #d7d7d2; border-radius:12px; padding:14px 16px; margin-bottom:20px; color:#4b4e54; font-size:15.5px; }
    </style>
</head>
<body>
    @include('catalog._app-bar', ['appBack' => route('catalog.index'), 'appTitle' => 'Mi reserva'])
    <div class="wrap">
        @php
            $expired = $booking->booking_status === 'EXPIRADA';
            $confirmed = in_array($booking->booking_status, ['CONFIRMADA', 'CHECK_IN', 'FINALIZADA'], true);
            $holdOpen = $booking->booking_status === 'PENDIENTE_PAGO' && $booking->expires_at && $booking->expires_at->isFuture();
            $hasBank = ! empty($pay['bank_titular']) && ! empty($pay['bank_account_number']);
            $canPayOnline = $mpEnabled && in_array($booking->booking_status, ['PENDIENTE_PAGO', 'CONFIRMADA'], true) && $booking->balanceDue() > 0;
            // Volvió de Mercado Pago pero el aviso todavía no llegó: se refresca solo unos segundos.
            $waitingMp = request('mp') === 'ok' && $booking->booking_status === 'PENDIENTE_PAGO';
            // Con la retención de 10 minutos corriendo, lo primero es pagar: el
            // QR recién sirve cuando la reserva está confirmada (o la mantiene
            // recepción para pagar al llegar).
            $payFirst = ! $expired && ! $confirmed && $booking->balanceDue() > 0;
            $showQr = ! $expired && ! $holdOpen && ! $waitingMp;
            $amount = '$'.number_format($booking->balanceDue(), 0, ',', '.');
        @endphp

        @if ($expired)
            <div class="check" style="background:#3a1c1c; color:#f3b8b8;">✕</div>
            <h1>Reserva vencida</h1>
            <p class="sub">No recibimos el pago a tiempo y el Playroom se liberó.</p>
            <div class="next-steps" style="background:#3a1c1c; border-color:#7a2d2d; color:#f3b8b8;">Puedes hacer una nueva reserva cuando quieras: <a href="{{ route('catalog.reserve') }}" style="color:#ffd699;">reservar de nuevo →</a></div>
        @elseif ($confirmed)
            <div class="check">✓</div>
            <h1>¡Reserva confirmada!</h1>
            <p class="sub">Muestra tu código QR en recepción al llegar.</p>
        @else
            <div class="check" style="background:#3a2a12; color:#ffd699;">⏳</div>
            <h1>Falta un paso: paga tu reserva</h1>
            <p class="sub">Tu Playroom está apartado. Se confirma apenas se registre el pago.</p>
        @endif

        @if (session('warning'))
            <div class="next-steps" style="background:#3a2a12; border-color:#7a5a1f; color:#ffd699;">{{ session('warning') }}</div>
        @endif

        @if ($waitingMp)
            <div class="next-steps" style="background:#12293a; border-color:#243a4c; color:#9edaff;">⏳ Estamos confirmando tu pago con Mercado Pago. Esta página se actualiza sola.</div>
            <meta http-equiv="refresh" content="4">
        @elseif (request('mp') === 'fallo')
            <div class="next-steps" style="background:#3a1c1c; border-color:#7a2d2d; color:#f3b8b8;">El pago no se completó. Puedes intentarlo de nuevo{{ $hasBank ? ' o pagar por transferencia' : '' }}.</div>
        @endif

        @if ($payFirst && ! $waitingMp)
            {{-- Lo primero: pagar antes de que se libere el Playroom. --}}
            <div class="pay-hero">
                @if ($holdOpen)
                    <div class="pay-timer">
                        <span>Se libera en</span>
                        <b id="hold-countdown" data-expires="{{ $booking->expires_at->toIso8601String() }}">{{ \App\Models\Booking::HOLD_MINUTES }}:00</b>
                        <span>hasta las {{ $booking->expires_at->timezone('America/Santiago')->format('H:i') }}</span>
                    </div>
                @endif
                @if ($canPayOnline)
                    <a class="btn pay-btn" href="{{ route('catalog.pay', $booking->pass_token) }}">Pagar {{ $amount }} ahora →</a>
                    <small>Pago seguro en Mercado Pago. Apenas se aprueba, tu reserva queda confirmada y aparece tu código QR.</small>
                @elseif (! empty($pay['mercadopago_link']))
                    <a class="btn pay-btn" href="{{ $pay['mercadopago_link'] }}" target="_blank" rel="noopener">Pagar {{ $amount }} ahora →</a>
                    <small>Pago seguro en Mercado Pago.</small>
                @elseif ($hasBank)
                    <small style="margin-top:0;">Paga por transferencia con los datos de abajo y envíanos el comprobante por WhatsApp.</small>
                @else
                    {{-- Sin pago en línea configurado: que nunca quede el reloj corriendo sin cómo pagar. --}}
                    <a class="btn pay-btn" href="https://wa.me/{{ \App\Http\Controllers\CatalogController::WHATSAPP_NUMBER }}?text={{ rawurlencode('Hola! Quiero pagar mi reserva '.$booking->code.' ('.$amount.').') }}" target="_blank" rel="noopener">Pagar por WhatsApp →</a>
                    <small>Te respondemos al tiro con los datos para pagar. Indica tu código {{ $booking->code }}.</small>
                @endif
            </div>
        @endif

        @if ($confirmed && $showQr)
            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="Código QR de la reserva {{ $booking->code }}">
                <strong>Tu código QR</strong>
                <small>Muéstralo en recepción al llegar. Código {{ $booking->code }}.</small><br>
                <a class="pass" href="{{ $passUrl }}">Descargar mi pase (PDF) ↓</a>
            </div>
        @endif

        <div class="card">
            <div class="row"><span class="muted">Código</span><span class="code">{{ $booking->code }}</span></div>
            <div class="row"><span class="muted">Estado</span><span class="status-badge" @if ($confirmed) style="background:#1c3a2a; color:#6fd39a; border-color:#2f6b45;" @endif>{{ $expired ? 'Vencida' : ($confirmed ? 'Confirmada' : 'Pendiente de pago') }}</span></div>
            <div class="row"><span class="muted">Playroom</span><span>{{ $booking->room->name }} · {{ $booking->room->category->name }}</span></div>
            <div class="row"><span class="muted">Fecha</span><span>{{ $booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('D [de] MMMM, HH:mm') }}</span></div>
            @if ($booking->short_stay)
                <div class="row"><span class="muted">Salida</span><span>{{ $booking->ends_at->timezone('America/Santiago')->format('H:i') }} (hora de cierre · estadía corta)</span></div>
            @else
                <div class="row"><span class="muted">Duración</span><span>{{ $booking->duration_minutes >= 60 ? intdiv($booking->duration_minutes, 60).' h' : $booking->duration_minutes.' min' }}</span></div>
            @endif
            @if ($booking->discount_amount > 0)
                <div class="row discount-row"><span class="muted">Descuento aplicado{{ $booking->coupon_code_snapshot ? ' ('.$booking->coupon_code_snapshot.')' : '' }}</span><span>-${{ number_format($booking->discount_amount, 0, ',', '.') }}</span></div>
            @endif
            @foreach ($booking->addons as $addon)
                <div class="row"><span class="muted">{{ $addon->description }}</span><span>${{ number_format($addon->amount, 0, ',', '.') }}</span></div>
            @endforeach
            @if ($confirmed && $booking->balanceDue() <= 0)
                <div class="row"><span class="muted">Total pagado</span><span>${{ number_format($booking->paidAmount(), 0, ',', '.') }}</span></div>
            @else
                <div class="row"><span class="muted">Total a pagar</span><span>{{ $amount }}</span></div>
            @endif
        </div>

        @if (! $expired && ! $confirmed && $showQr)
            {{-- Mantenida por recepción para pagar al llegar: el QR sirve para identificarla. --}}
            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="Código QR de la reserva {{ $booking->code }}">
                <strong>Tu código QR</strong>
                <small>Muéstralo en recepción al llegar. Código {{ $booking->code }}.</small><br>
                <a class="pass" href="{{ $passUrl }}">Descargar mi pase (PDF) ↓</a>
            </div>
        @elseif (! $expired && ! $showQr)
            <div class="qr-pending">🔒 Tu código QR y tu pase aparecerán aquí apenas se confirme el pago.</div>
        @endif

        @if ($confirmed && $canPayOnline)
            <div class="pay-box">
                <strong>Saldo pendiente — {{ $amount }}</strong>
                <a class="mp" href="{{ route('catalog.pay', $booking->pass_token) }}">Pagar con Mercado Pago →</a>
                <small>También puedes pagarlo al llegar al motel.</small>
            </div>
        @endif
        @if (! $expired && ! $canPayOnline && $hasBank && $booking->balanceDue() > 0)
            <div class="pay-box">
                <strong>O paga por transferencia — {{ $amount }}</strong>
                <div class="line"><span>Titular</span><span>{{ $pay['bank_titular'] }}</span></div>
                @if (! empty($pay['bank_rut']))<div class="line"><span>RUT</span><span>{{ $pay['bank_rut'] }}</span></div>@endif
                @if (! empty($pay['bank_name']))<div class="line"><span>Banco</span><span>{{ $pay['bank_name'] }}</span></div>@endif
                <div class="line"><span>{{ $pay['bank_account_type'] ?: 'Cuenta' }}</span><span>{{ $pay['bank_account_number'] }}</span></div>
                @if (! empty($pay['bank_email']))<div class="line"><span>Correo</span><span>{{ $pay['bank_email'] }}</span></div>@endif
                <small>Envíanos el comprobante por WhatsApp indicando tu código {{ $booking->code }}.</small>
            </div>
        @endif

        @unless ($expired)
        <div class="next-steps">
            <strong>¿Qué sigue ahora?</strong>
            <ul>
                @if ($confirmed)
                    <li>{{ $booking->balanceDue() > 0 ? 'Tu reserva está confirmada. Queda un saldo de '.$amount.' por pagar al llegar al motel.' : 'Tu pago está registrado y tu reserva está confirmada.' }}</li>
                    <li>Muestra el código QR o el código {{ $booking->code }} en recepción al llegar.</li>
                @elseif ($holdOpen)
                    <li>Paga antes de las {{ $booking->expires_at->timezone('America/Santiago')->format('H:i') }}; si no, el Playroom se libera.</li>
                    <li>Apenas se registre tu pago, la reserva queda confirmada y aparece tu código QR.</li>
                @else
                    <li>Esta reserva queda pendiente hasta que se registre tu pago.</li>
                @endif
                <li>Presenta tu documento de identidad al llegar.</li>
            </ul>
        </div>
        @endunless

        <a class="back" href="{{ route('catalog.index') }}">← Volver al catálogo</a>
    </div>
    @if ($holdOpen)
        <script>
        (function () {
            const el = document.getElementById('hold-countdown');
            if (!el) return;
            const end = new Date(el.dataset.expires).getTime();
            function tick() {
                const left = Math.max(0, Math.round((end - Date.now()) / 1000));
                el.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
                el.classList.toggle('urgent', left <= 120);
                if (left <= 0) { setTimeout(() => location.reload(), 1500); return; }
                setTimeout(tick, 1000);
            }
            tick();
        })();
        </script>
    @endif
</body>
</html>
