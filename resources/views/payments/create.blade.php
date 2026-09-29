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
        .amount-fmt { font-size:13px; color:#8fe0ad; margin-top:6px; }
        button { width:100%; margin-top:24px; background:#ff7918; color:#fff; border:none; padding:14px; border-radius:8px; font-size:15px; font-weight:600; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        a.back { display:block; text-align:center; margin-top: 16px; color:#999; font-size: 13px; text-decoration:none; }
        a.skip-btn { display:block; text-align:center; margin-top: 16px; background:#2a2a2a; border:1px solid #555; color:#eee; padding:13px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; }
        a.skip-btn:hover { border-color:#ff7918; color:#ff7918; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner hh-payment-page">
    <h1>Registrar pago</h1>
    <div class="code">{{ $booking->code }} — {{ $booking->room->name }}</div>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="hh-payment-layout">
    <div class="card hh-payment-summary">
        <h2>Resumen de la reserva</h2>
        <div class="row"><span>Precio final</span><span>${{ number_format($booking->price_final, 0, ',', '.') }}</span></div>
        <div class="row"><span>Pagado</span><span>${{ number_format($booking->paidAmount(), 0, ',', '.') }}</span></div>
        <div class="row balance"><span>Saldo</span><span>${{ number_format($booking->balanceDue(), 0, ',', '.') }}</span></div>
    </div>

    <form class="card hh-form-card" method="POST" action="{{ route('payments.store', $booking->code) }}">
        <h2>Datos del pago</h2>
        @csrf
        <input type="hidden" name="request_token" value="{{ old('request_token', $requestToken) }}">
        @if ($after)
            <input type="hidden" name="after" value="{{ $after }}">
        @endif

        <label for="payment-method">Medio de pago</label>
        <select id="payment-method" name="payment_method_id" required>
            <option value="" disabled selected>— Elige un medio —</option>
            @foreach ($methods as $method)
                <option value="{{ $method->id }}" data-code="{{ $method->code }}" @selected(old('payment_method_id') == $method->id)>{{ $method->name }}</option>
            @endforeach
        </select>

        <label for="amount-display">Monto (CLP)</label>
        <input type="text" inputmode="numeric" id="amount-display" placeholder="0" required>
        <input type="hidden" name="amount" id="amount-input" value="{{ old('amount', $suggestedAmount) }}">

        <label for="payment-reference">Referencia / N° de operación (opcional — si la dejas vacía, generamos una)</label>
        <input id="payment-reference" type="text" name="external_id" value="{{ old('external_id') }}">

        <div id="voucher-field">
            <label for="voucher-number">N° voucher <span id="voucher-hint" style="color:#777; font-weight:400;"></span></label>
            <input id="voucher-number" type="text" name="voucher_number" value="{{ old('voucher_number') }}" placeholder="Ej. 146">
        </div>

        <div id="receipt-field">
            <label for="receipt-number">N° boleta <span id="receipt-hint" style="color:#777; font-weight:400;"></span></label>
            <input id="receipt-number" type="text" name="receipt_number" value="{{ old('receipt_number') }}" placeholder="Ej. 96302">
        </div>

        <label for="payment-notes">Observaciones (opcional)</label>
        <input id="payment-notes" type="text" name="notes" value="{{ old('notes') }}">

        <button type="submit">Registrar pago</button>
    </form>
    </div>

    @if ($after === 'board')
        <a class="skip-btn" href="{{ route('reservations.show', $booking->code) }}">Saltar por ahora →</a>
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

        // Efectivo/transferencia solo piden boleta; débito/crédito solo
        // piden voucher -- se oculta el que no corresponde para no
        // confundir a recepción. Con otros medios (Mercado Pago, Otro) se
        // muestran los dos, basta con uno.
        (function () {
            const select = document.getElementById('payment-method');
            const voucherField = document.getElementById('voucher-field');
            const receiptField = document.getElementById('receipt-field');
            const voucherInput = document.getElementById('voucher-number');
            const receiptInput = document.getElementById('receipt-number');
            const voucherHint = document.getElementById('voucher-hint');
            const receiptHint = document.getElementById('receipt-hint');
            if (!select) return;

            function apply() {
                const code = select.selectedOptions[0]?.dataset.code;
                const cashLike = code === 'efectivo' || code === 'transferencia';
                const cardLike = code === 'debito' || code === 'credito';

                voucherField.style.display = cashLike ? 'none' : '';
                receiptField.style.display = cardLike ? 'none' : '';
                if (cashLike) voucherInput.value = '';
                if (cardLike) receiptInput.value = '';

                voucherHint.textContent = cardLike ? '' : (!code ? '(uno de los dos)' : '');
                receiptHint.textContent = cashLike ? '' : (!code ? '(uno de los dos)' : '');
            }

            select.addEventListener('change', apply);
            apply();
        })();
    </script>
</body>
</html>
