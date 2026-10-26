@php
    $mpClient = app(\App\Services\Payments\MercadoPagoClient::class);
    $linkBalance = $booking->balanceDue();
    $showPayLink = $mpClient->enabled() && $linkBalance > 0 && in_array($booking->booking_status, ['PENDIENTE_PAGO', 'CONFIRMADA'], true);
@endphp
@if ($showPayLink)
    @php
        $payUrl = route('catalog.pay', $booking->pass_token);
        $firstName = trim(explode(' ', trim($booking->customer->name))[0] ?? '');
        $payMessage = 'Hola'.($firstName !== '' ? ' '.$firstName : '').'! Este es el link para pagar tu reserva '.$booking->code.' en HH Motel ($'.number_format($linkBalance, 0, ',', '.').'): '.$payUrl;
        $payPhone = preg_replace('/\D/', '', (string) $booking->customer->phone_e164);
        $payWaUrl = 'https://wa.me/'.$payPhone.'?text='.rawurlencode($payMessage);
    @endphp
    <div class="pay-link-box" style="margin-top:14px;padding:12px 14px;background:#161616;border:1px solid #333;border-radius:10px;">
        <div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Cobrar por link (Mercado Pago)</div>
        <input type="text" readonly value="{{ $payUrl }}" onclick="this.select()" style="width:100%;font-size:12px;box-sizing:border-box;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
            <button type="button" class="pay-link-copy" data-message="{{ $payMessage }}" style="width:auto;margin:0;padding:8px 14px;background:#262626;border:1.5px solid #3a3a3a;color:#eee;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Copiar mensaje</button>
            @if ($payPhone !== '')
                <a href="{{ $payWaUrl }}" target="_blank" rel="noopener" style="padding:8px 14px;background:#1f7a4a;border:1.5px solid #1f7a4a;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Enviar por WhatsApp</a>
            @endif
        </div>
        <div style="font-size:11.5px;color:#888;margin-top:6px;">El cliente paga el saldo (${{ number_format($linkBalance, 0, ',', '.') }}) y la reserva se confirma sola. Solo sirve mientras la reserva siga activa.</div>
    </div>
    <script>
        document.querySelectorAll('.pay-link-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.dataset.message, done = function () { var old = btn.textContent; btn.textContent = '¡Copiado!'; setTimeout(function () { btn.textContent = old; }, 1800); };
                if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done, fallback); } else { fallback(); }
                function fallback() { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(t); }
            });
        });
    </script>
@endif
