<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Registrar pago — {{ $booking->code }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 480px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 14px; margin-bottom: 18px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 16px 18px; margin-bottom: 18px; }
        .row { display:flex; justify-content:space-between; padding: 5px 0; font-size: 14px; }
        .row.balance { font-weight:700; color:#ff7918; font-size:16px; border-top:1px solid #333; margin-top:6px; padding-top:10px; }
        label { display:block; font-size: 12.5px; color:#bbb; margin: 14px 0 6px; }
        input, select { width:100%; box-sizing:border-box; background:#1c1c1c; border:1px solid #333; color:#fff; padding:10px 12px; border-radius:8px; font-size:15px; }
                button { width:100%; margin-top:24px; background:#ff7918; color:#fff; border:none; padding:14px; border-radius:8px; font-size:15px; font-weight:600; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        a.back { display:block; text-align:center; margin-top: 16px; color:#999; font-size: 13px; text-decoration:none; }
        a.skip-btn { display:block; text-align:center; margin-top: 16px; background:#2a2a2a; border:1px solid #555; color:#eee; padding:13px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; }
        a.skip-btn:hover { border-color:#ff7918; color:#ff7918; }
        /* Pantalla de pago compacta: medios como botones, campos en parejas */
        .page-inner { max-width: 1250px; padding-top:16px; padding-bottom:20px; }
        .hh-payment-page .hh-payment-layout { grid-template-columns: minmax(280px,.7fr) minmax(0,1.3fr); gap:18px; }
        .hh-payment-page .hh-payment-layout > .card { padding:18px 22px 20px; }
        .hh-payment-layout h2 { margin:0 0 12px; }
        .hh-payment-summary .row { padding:9px 0; gap:14px; border-top:1px solid #292929; }
        .hh-payment-summary .row:first-of-type { border-top:none; }
        .hh-payment-summary .row span:last-child { text-align:right; }
        .hh-payment-summary .row.item { padding:3px 0 3px 14px; border-top:none; font-size:13px; color:#bbb; }
        .hh-payment-summary .row.item em { font-style:normal; color:#888; margin-left:4px; }
        .muted { color:#777; font-weight:400; }
        .sr-only { position:absolute; width:1px; height:1px; margin:-1px; padding:0; overflow:hidden; clip:rect(0 0 0 0); border:0; opacity:0; }
        .method-chips { display:flex; flex-wrap:wrap; gap:8px; }
        .chip { width:auto; margin:0; padding:10px 18px; background:#1c1c1c; border:1.5px solid #3a3a3a; color:#ccc; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .chip:hover { border-color:#ff7918; color:#fff; }
        .chip.active { background:#ff7918; border-color:#ff7918; color:#111; }
        .chip-error { margin-top:8px; color:#f3b8b8; font-size:13px; }
        .pay-grid { display:grid; grid-template-columns: 1fr 1fr; gap:0 16px; align-items:start; }
        @media (max-width: 700px) { .pay-grid { grid-template-columns: 1fr; } }
        .pay-actions { display:flex; align-items:center; gap:18px; margin-top:20px; flex-wrap:wrap; }
        .pay-actions button.primary { width:auto; min-width:280px; margin:0; padding:13px 28px; }
        a.skip-inline { color:#bbb; font-size:14px; text-decoration:none; font-weight:600; }
        a.skip-inline:hover { color:#ff7918; }
        .step-title { display:flex; align-items:center; gap:10px; font-size:15px; font-weight:700; color:#fff; margin:20px 0 10px; }
        .step-title:first-of-type { margin-top:4px; }
        .step-title b { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; background:#ff7918; color:#111; font-size:13px; }
        .pay-wait { color:#888; font-size:13.5px; margin:14px 0 0; }
        .amount-box { display:flex; align-items:center; gap:8px; background:#111; border:1.5px solid #3a3a3a; border-radius:10px; padding:4px 16px; }
        .amount-box:focus-within { border-color:#ff7918; }
        .amount-box .cur { font-size:26px; color:#888; font-weight:700; }
        .amount-box input { border:none !important; background:transparent !important; box-shadow:none !important; font-size:30px; font-weight:800; padding:10px 0; outline:none !important; }
        .doc-single { grid-template-columns:1fr !important; }
        .doc-single label { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
        #voucher-number, #receipt-number { font-size:20px; font-weight:700; padding:13px 14px; letter-spacing:.5px; }
        button.full-balance { width:auto; margin:10px 0 0; padding:9px 16px; background:#262626; border:1.5px solid #3a3a3a; color:#ddd; border-radius:999px; font-size:13.5px; font-weight:600; cursor:pointer; }
        button.full-balance:hover { border-color:#ff7918; color:#fff; }
        .doc-hint { color:#888; font-size:13px; margin:8px 0 0; min-height:0; }
        .doc-hint:empty { display:none; }
        button.note-toggle { width:auto; margin:16px 0 0; padding:4px 0; background:none; border:none; color:#bbb; font-size:13.5px; font-weight:600; cursor:pointer; text-align:left; }
        button.note-toggle:hover { color:#ff7918; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner hh-payment-page">
    <h1>Registrar pago</h1>
    <div class="code">{{ $booking->code }} — {{ $booking->room->name }}</div>

    @if ($errors->any())
        <div class="errors">
            @foreach (array_unique($errors->all()) as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @php
        $balance = $booking->balanceDue();
        $consumos = $booking->addonsTotal();
        $tz = 'America/Santiago';
    @endphp
    <div class="hh-payment-layout">
    <div class="card hh-payment-summary">
        <h2>Resumen de la reserva</h2>
        <div class="row"><span>Cliente</span><span>{{ $booking->customer->name }}</span></div>
        <div class="row"><span>Habitación</span><span>{{ $booking->room->name }} · {{ $booking->room->category->name }}</span></div>
        <div class="row"><span>Horario</span><span>{{ $booking->starts_at->timezone($tz)->format('d/m H:i') }} — {{ $booking->ends_at->timezone($tz)->format('H:i') }}</span></div>
        <div class="row"><span>Precio final</span><span>${{ number_format($booking->price_final, 0, ',', '.') }}</span></div>
        @if ($consumos > 0)
            <div class="row"><span>Consumos</span><span>${{ number_format($consumos, 0, ',', '.') }}</span></div>
            @foreach ($booking->addons as $addon)
                <div class="row item"><span>{{ $addon->description }}@if ($addon->quantity > 1) <em>× {{ $addon->quantity }}</em>@endif</span><span>${{ number_format($addon->amount, 0, ',', '.') }}</span></div>
            @endforeach
        @endif
        <div class="row"><span>Pagado</span><span>${{ number_format($booking->paidAmount(), 0, ',', '.') }}</span></div>
        <div class="row balance"><span>Saldo</span><span>${{ number_format($balance, 0, ',', '.') }}</span></div>
    </div>

    <form class="card hh-form-card" method="POST" action="{{ route('payments.store', $booking->code) }}" id="payment-form">
        <h2>Datos del pago</h2>
        @csrf
        <input type="hidden" name="request_token" value="{{ old('request_token', $requestToken) }}">
        @if ($after)
            <input type="hidden" name="after" value="{{ $after }}">
        @endif

        <div class="step-title"><b>1</b> ¿Cómo paga?</div>
        <div class="method-chips" id="method-chips" role="group" aria-label="Medio de pago">
            @php $order = ['efectivo', 'transferencia', 'debito', 'credito', 'otro']; @endphp
            @foreach ($methods->sortBy(fn ($m) => ($i = array_search($m->code, $order, true)) === false ? 99 : $i) as $method)
                <button type="button" class="chip" data-id="{{ $method->id }}" data-code="{{ $method->code }}">{{ $method->name }}</button>
            @endforeach
        </div>
        <select id="payment-method" name="payment_method_id" class="sr-only" tabindex="-1" aria-hidden="true">
            <option value="" disabled selected>— Elige un medio —</option>
            @foreach ($methods as $method)
                <option value="{{ $method->id }}" data-code="{{ $method->code }}" @selected(old('payment_method_id') == $method->id)>{{ $method->name }}</option>
            @endforeach
        </select>
        <div class="chip-error" id="chip-error" hidden>Elige un medio de pago para continuar.</div>
        <p class="pay-wait" id="pay-wait">Elige el medio de pago para seguir.</p>

        <div id="pay-rest" hidden>
            <div class="step-title"><b>2</b> ¿Cuánto paga?</div>
            <div class="amount-box">
                <span class="cur">$</span>
                <input type="text" inputmode="numeric" id="amount-display" placeholder="0" aria-label="Monto en pesos">
                <input type="hidden" name="amount" id="amount-input" value="{{ old('amount', $suggestedAmount) }}">
            </div>
            @if ($balance > 0)
                <button type="button" class="full-balance" id="full-balance" data-amount="{{ $balance }}">Saldo completo · ${{ number_format($balance, 0, ',', '.') }}</button>
            @endif

            <div class="step-title"><b>3</b> <span id="doc-title">Comprobante</span></div>
            <div class="pay-grid" id="doc-grid">
                <div id="voucher-field">
                    <label for="voucher-number" id="voucher-label">N° de voucher</label>
                    <input id="voucher-number" type="text" name="voucher_number" value="{{ old('voucher_number') }}" placeholder="Ej. 146" inputmode="numeric" autocomplete="off">
                </div>
                <div id="receipt-field">
                    <label for="receipt-number" id="receipt-label">N° de boleta</label>
                    <input id="receipt-number" type="text" name="receipt_number" value="{{ old('receipt_number') }}" placeholder="Ej. 96302" inputmode="numeric" autocomplete="off">
                </div>
            </div>
            <p class="doc-hint" id="doc-hint"></p>

            <button type="button" class="note-toggle" id="note-toggle" @if (old('notes')) hidden @endif>+ Agregar una nota</button>
            <div id="note-box" @if (! old('notes')) hidden @endif>
                <label for="payment-notes">Nota</label>
                <input id="payment-notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="255">
            </div>
        </div>

        <div class="pay-actions">
            <button type="submit" class="primary">Registrar pago</button>
            @if ($after === 'board')
                <a class="skip-inline" href="{{ route('reservations.show', $booking->code) }}">Saltar por ahora →</a>
            @elseif ($after === 'pase')
                <a class="skip-inline" href="{{ route('bookings.checkin.show', $booking->code) }}">Cobrar después, ir al check-in →</a>
            @elseif ($after === 'checkin')
                <a class="skip-inline" href="{{ route('bookings.checkin.show', ['code' => $booking->code, 'flujo' => 'ingreso']) }}">Cobrar después, ir al check-in →</a>
            @endif
        </div>
        @include('payments._pay-link', ['booking' => $booking, 'divider' => true])
    </form>
    </div>

    @if ($after === 'board')
        <a class="back" href="{{ route('rooms.board') }}">← Volver al tablero</a>
    @else
        <a class="back" href="{{ route('reservations.show', $booking->code) }}">← Volver a la reserva</a>
    @endif
    </div>
    <script>
        // Un <input type="number"> no puede mostrar puntos de miles — y
        // mostrar el monto crudo Y el formateado aparte se veía como si
        // saliera duplicado. Se usa un campo de texto con formato CLP en
        // vivo (lo único que ve recepción) y uno oculto con el número
        // limpio, que es el que de verdad se manda al servidor.
        (function () {
            const display = document.getElementById('amount-display');
            const hidden = document.getElementById('amount-input');
            if (!display || !hidden) return;

            function fmt(raw) {
                return raw ? Number(raw).toLocaleString('es-CL') : '';
            }

            display.value = fmt(hidden.value);

            display.addEventListener('input', function () {
                const raw = display.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
                hidden.value = raw;
                display.value = fmt(raw);
            });
        })();

        // Según el medio se pide UN solo comprobante: efectivo/transferencia
        // → boleta; débito/crédito → voucher del POS. Con Mercado Pago u
        // Otro se muestran los dos y basta con uno. Todo el bloque de
        // monto y comprobante aparece recién al elegir el medio.
        (function () {
            const select = document.getElementById('payment-method');
            const rest = document.getElementById('pay-rest');
            const wait = document.getElementById('pay-wait');
            const voucherField = document.getElementById('voucher-field');
            const receiptField = document.getElementById('receipt-field');
            const voucherInput = document.getElementById('voucher-number');
            const receiptInput = document.getElementById('receipt-number');
            const title = document.getElementById('doc-title');
            const hint = document.getElementById('doc-hint');
            if (!select) return;

            function apply() {
                const code = select.selectedOptions[0]?.dataset.code;
                rest.hidden = !code;
                wait.hidden = !!code;
                if (!code) return;
                const cashLike = code === 'efectivo' || code === 'transferencia';
                const cardLike = code === 'debito' || code === 'credito';

                voucherField.style.display = cashLike ? 'none' : '';
                receiptField.style.display = cardLike ? 'none' : '';
                if (cashLike) voucherInput.value = '';
                if (cardLike) receiptInput.value = '';

                document.getElementById('doc-grid').classList.toggle('doc-single', cashLike || cardLike);
                title.textContent = cashLike ? 'N° de boleta' : cardLike ? 'N° de voucher' : 'Comprobante';
                hint.textContent = cashLike ? 'Es el número de la boleta que entregas al cliente.'
                    : cardLike ? 'Es el número que sale en el voucher del POS.'
                    : 'Escribe el N° de voucher o el de boleta: basta con uno.';
            }

            select.addEventListener('change', apply);
            apply();

            document.getElementById('full-balance')?.addEventListener('click', function () {
                const display = document.getElementById('amount-display');
                display.value = Number(this.dataset.amount).toLocaleString('es-CL');
                document.getElementById('amount-input').value = this.dataset.amount;
            });
            document.getElementById('note-toggle')?.addEventListener('click', function () {
                this.hidden = true;
                document.getElementById('note-box').hidden = false;
                document.getElementById('payment-notes').focus();
            });
        })();

        // Medio de pago como botones: llenan el <select> real (que es el que se envía).
        (function () {
            const select = document.getElementById('payment-method');
            const chips = Array.from(document.querySelectorAll('#method-chips .chip'));
            const error = document.getElementById('chip-error');
            if (!select || !chips.length) return;

            function paint() { chips.forEach((c) => c.classList.toggle('active', c.dataset.id === select.value)); }
            chips.forEach((chip) => chip.addEventListener('click', function () {
                select.value = chip.dataset.id;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                error.hidden = true;
                paint();
                document.getElementById('amount-display')?.focus();
            }));
            paint();

            document.getElementById('payment-form').addEventListener('submit', function (e) {
                if (!select.value) {
                    e.preventDefault();
                    error.hidden = false;
                    chips[0].focus();
                }
            });
        })();
    </script>
</body>
</html>
