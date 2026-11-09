<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reserva {{ $booking->code }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 960px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; letter-spacing: .04em; margin-bottom: 2px; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 15px; margin-bottom: 20px; }
        .banner { border-radius:10px; padding: 12px 16px; margin-bottom:16px; font-size:14px; }
        .banner.ok { background:#1c2f1c; border:1px solid #2e5a2e; color:#6fd39a; }
        .banner.error { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; }

        .layout { display:grid; grid-template-columns: 1fr 1fr; gap:20px; align-items:start; }
        @media (max-width: 760px) { .layout { grid-template-columns: minmax(0, 1fr); } }
        .col { display:flex; flex-direction:column; gap:16px; }

        .card { background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 18px 20px; }
        .row { display:flex; justify-content:space-between; padding: 7px 0; font-size: 14.5px; border-bottom: 1px solid #292929; gap:12px; }
        .row:last-child { border-bottom: none; }
        .room-media { display:flex; flex-wrap:wrap; gap:6px; padding:8px 0; border-bottom: 1px solid #292929; }
        .room-media img { width:64px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #333; }
        .room-media-video { display:flex; align-items:center; justify-content:center; width:64px; height:48px; border-radius:6px; border:1px dashed #444; color:#ff7918; text-decoration:none; font-size:11px; }
        .row.total { font-weight: 700; font-size: 16px; color:#fff; }
        .row .muted { color:#999; flex:none; }
        .pill { display:inline-block; font-size:11px; font-family: ui-monospace, monospace; padding:3px 9px; border-radius:20px; background:#2a2a2a; color:#ccc; }
        .balance { color:#ff7918; }
        a.back { display:block; text-align:center; margin-top: 14px; color:#999; font-size: 13px; text-decoration:none; }
        /* Secundario a propósito: "Finalizar" (arriba) es naranja porque es
           la acción principal de esta pantalla -- volver al tablero es solo
           navegación, no debería competir visualmente con eso. Ancho acotado
           y centrado: al ser un solo botón suelto (no parte del grid de dos
           columnas de arriba), estirarlo al 100% del ancho de página se veía
           desproporcionado frente al resto del layout. */
        a.board-btn { display:block; max-width:320px; text-align:center; background:#2a2a2a; border:1px solid #444; color:#eee; padding:13px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; margin:24px auto 0; }
        a.board-btn:hover { border-color:#ff7918; color:#ff7918; }
        a.pay-btn { display:block; text-align:center; background:#ff7918; color:#fff; padding:13px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; }
        a.pay-btn-inline { margin-top:14px; }
        a.conversation-btn { display:inline-block; background:#0a5cff; border:1px solid #0a5cff; color:#fff; padding:8px 14px; border-radius:7px; font-size:12.5px; font-weight:700; text-decoration:none; }
        a.whatsapp-btn { display:inline-block; background:#25d366; border:1px solid #25d366; color:#0b2816; padding:8px 14px; border-radius:7px; font-size:12.5px; font-weight:700; text-decoration:none; }
        .payment-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px; margin:-4px 0 16px; }
        button.copy-btn { background:#2a2a2a; border:1px solid #444; color:#eee; padding:8px 14px; border-radius:7px; font-size:12.5px; font-weight:700; cursor:pointer; font-family:inherit; }
        button.copy-btn:hover { border-color:#ff7918; color:#ff7918; }
        .sent-tag { display:flex; align-items:center; background:#0f2b22; border:1px solid #1c9169; color:#6ee7b7; font-size:12px; font-weight:600; padding:6px 12px; border-radius:20px; margin-right:auto; }
        .visually-hidden { position:absolute; left:-9999px; top:-9999px; }
        .pay-title { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px; }
        .pay-block { padding:12px 0; border-top:1px solid #292929; }
        .pay-block:first-of-type { border-top:none; padding-top:0; }
        .pay-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
        .pay-method { font-weight:600; }
        .pay-amount { font-family: ui-monospace, monospace; color:#6fd39a; }
        .pay-status { margin-left:auto; font-size:10.5px; font-family: ui-monospace, monospace; text-transform:uppercase; background:#16301f; color:#6fd39a; padding:2px 9px; border-radius:20px; }
        .pay-doc-done { display:flex; gap:16px; align-items:center; flex-wrap:wrap; font-size:13.5px; color:#bbb; padding-top:4px; }
        .pay-doc-done b { color:#eee; font-family:ui-monospace,monospace; }
        .pay-operation { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:10px; font-size:13.5px; color:#bbb; }
        .pay-operation b { color:#eee; font-family:ui-monospace,monospace; }
        .pay-operation a { color:#ff9a4d; font-weight:600; }
        .pay-operation button { width:auto; margin:0; padding:3px 9px; background:transparent; color:#eee; border:1px solid #555; border-radius:6px; font-size:12.5px; cursor:pointer; }
        .pay-doc-edit { margin-left:auto; background:transparent; border:0; color:#888; font-size:12.5px; cursor:pointer; padding:2px 4px; }
        .pay-doc-edit:hover { color:#ff7918; }
        .pay-docs[hidden] { display:none; }
        .pay-docs { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .pay-docs label { display:flex; flex-direction:column; gap:4px; font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; }
        .pay-docs input { width:130px; padding:8px 10px; background:#111; border:1px solid #3a3a3a; border-radius:7px; color:#eee; font-size:13.5px; }
        .pay-docs button { padding:9px 16px; background:#ff7918; color:#111; border:0; border-radius:7px; font-weight:700; font-size:13px; cursor:pointer; }
        table.payments { width:100%; border-collapse: collapse; font-size: 13px; }
        table.payments th { text-align:left; color:#888; font-weight:500; font-size:11px; text-transform:uppercase; padding-bottom:6px; }
        table.payments td { padding: 6px 0; border-top: 1px solid #292929; }
        .addon-form { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .addon-form input, .addon-form select { background:#111; border:1px solid #333; color:#fff; padding:8px 10px; border-radius:6px; font-size:13px; min-width:0; }
        .addon-form select { flex:1 1 140px; }
        .addon-form input[name="quantity"] { flex:0 1 60px; }
        .addon-form input[name="description"] { flex:1 1 140px; }
        .addon-form input[name="amount"] { flex:0 1 90px; }
        .addon-form button { width:auto; margin-top:0; padding:8px 14px; font-size:13px; background:#ff7918; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; flex:0 0 auto; }
        .addon-sep { font-size:11px; color:#777; margin: 12px 0 6px; text-transform:uppercase; letter-spacing:.04em; }
        #consumo { border-left: 3px solid #ff7918; }
        .addon-label { font-size:12.5px; color:#bbb; margin: 4px 0 6px; }
        .pending-alert { background:#3a1c26; border:1px solid #7a2d4a; color:#f3a8c4; padding:10px 14px; border-radius:8px; font-size:13.5px; margin: 4px 0 12px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
        .pending-alert a { color:#fff; background:#ff7918; text-decoration:none; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12.5px; white-space:nowrap; }
        ul.guests { margin:0; padding-left:18px; font-size:14px; }
        .col-primary { background:#ff7918; border-color:#ff7918; color:#111; }
        .col-primary:hover { background:#ff8f3d; color:#111; }
        .col-disabled { opacity:.6; cursor:not-allowed; color:#f3a8c4; border-color:#7a2d4a; }
        .col-disabled:hover { border-color:#7a2d4a; color:#f3a8c4; }
        /* Venta de consumo durante la estadía */
        .addon-panel { margin-top:10px; }
        .addon-head { display:flex; justify-content:space-between; align-items:baseline; gap:10px; padding-bottom:8px; border-bottom:1px solid #2c2c2c; margin-bottom:4px; }
        .addon-head b { font-size:14px; color:#eee; }
        .addon-total { font-size:12.5px; font-family:ui-monospace,monospace; color:#888; }
        .addon-total.has { color:#8fe0ad; font-weight:700; }
        .addon-cat { font-size:10.5px; text-transform:uppercase; letter-spacing:.06em; color:#888; margin:10px 0 4px; }
        .addon-combos { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:8px; }
        .addon-combo { display:flex; flex-direction:column; align-items:center; text-align:center; gap:3px; padding:10px 8px; background:#242424; border:1px solid #363636; border-radius:11px; }
        .addon-combo.out { opacity:.5; }
        .addon-combo-name { font-size:13.5px; font-weight:700; color:#fff; line-height:1.2; }
        .addon-combo-items { font-size:11px; color:#9a9a9a; line-height:1.3; min-height:26px; }
        .addon-combo-price { font-size:16px; font-weight:800; color:#ff7918; margin:1px 0 3px; }
        .addon-tools { display:flex; flex-wrap:wrap; align-items:center; gap:8px 12px; margin:10px 0 4px; }
        .addon-tools input[type=search] { flex:1 1 220px; min-width:0; background:#111; border:1px solid #3a3a3a; color:#f1f1f1; padding:9px 12px; border-radius:8px; font-size:14px; }
        .addon-tools input[type=search]:focus { outline:none; border-color:#ff7918; }
        .addon-chips { display:flex; flex-wrap:wrap; gap:6px; }
        .addon-chips button { width:auto; margin:0; padding:6px 12px; border-radius:999px; background:#222; border:1px solid #3a3a3a; color:#bbb; font-size:12.5px; font-weight:600; cursor:pointer; }
        .addon-chips button.on { background:#ff7918; border-color:#ff7918; color:#21170e; }
        .addon-show-out { display:flex; align-items:center; gap:6px; font-size:12.5px; color:#999; cursor:pointer; margin:0; }
        .addon-show-out input { width:auto; margin:0; }
        .addon-block { margin-bottom:6px; }
        .addon-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(175px, 1fr)); gap:8px; }
        .addon-tile { display:flex; flex-direction:column; gap:6px; padding:10px 11px; background:#1f1f1f; border:1px solid #333; border-radius:10px; }
        .addon-tile:has(.qty.has) { border-color:#ff7918; background:#2a1d12; }
        .addon-tile.low .addon-stock { color:#f0b84b; }
        .addon-tile.out, .addon-combo.out { display:none; }
        .show-out .addon-tile.out, .show-out .addon-combo.out { display:flex; opacity:.45; }
        .addon-tile.hide, .addon-combo.hide, .addon-block.hide { display:none !important; }
        .addon-name { font-size:13.5px; font-weight:600; color:#f1f1f1; line-height:1.25; min-height:34px; }
        .addon-meta { display:flex; justify-content:space-between; align-items:baseline; gap:6px; }
        .addon-price { font-size:15px; font-weight:800; color:#ff7918; }
        .addon-stock { font-size:11.5px; color:#8d8f94; }
        .addon-tile .qty { justify-content:space-between; }
        .addon-tile .qty button { width:34px !important; height:32px !important; }
        .addon-empty { color:#888; font-size:13px; margin:8px 0; }
        .addon-bar { position:sticky; bottom:0; z-index:5; display:flex; justify-content:flex-end; align-items:center; gap:14px; margin-top:12px; padding:10px 0 4px; background:linear-gradient(to top, #1a1a1a 75%, transparent); }
        .addon-bar .addon-submit { margin:0; }
        .addon-panel .qty { display:flex; align-items:center; gap:2px; flex:none; }
        .addon-panel .qty button { width:28px; height:28px; margin:0; padding:0; border-radius:7px; background:#262626; border:1px solid #444; color:#ddd; font-size:17px; line-height:1; font-weight:600; cursor:pointer; }
        .addon-panel .qty button:hover:not(:disabled) { border-color:#ff7918; color:#ff7918; }
        .addon-panel .qty input[type=number] { width:30px !important; height:28px !important; min-height:0 !important; margin:0 !important; padding:0 !important; text-align:center; background:transparent !important; border:none !important; box-shadow:none !important; color:#9a9a9a !important; font-size:15px; font-weight:700; -moz-appearance:textfield; appearance:textfield; }
        .addon-panel .qty input::-webkit-inner-spin-button, .addon-panel .qty input::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
        .addon-panel .qty.has input[type=number] { color:#ff7918 !important; }
        .addon-submit { display:block; width:auto; margin:12px 0 0 auto; padding:10px 22px; background:#ff7918; color:#111; border:0; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; }
        .addon-submit:disabled { background:#2a2a2a; color:#777; cursor:not-allowed; }
        .addon-more { margin-top:12px; padding-top:12px; border-top:1px solid #2c2c2c; display:flex; flex-direction:column; gap:10px; }
        .addon-hour { width:auto; background:#2a2410; border:1px solid #6b5a1e; color:#f0d58a; padding:8px 14px; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; }
        .addon-hour:hover { border-color:#f0b84b; color:#fff; }
        .addon-hour b { color:#f0b84b; margin-left:4px; }
        .addon-hour-off { padding:9px 12px; border:1px dashed #6b5a1e; border-radius:9px; background:#2a2410; color:#e8c76f; font-size:12.5px; line-height:1.5; }
        .addon-other summary { cursor:pointer; color:#999; font-size:12.5px; list-style:none; }
        .addon-other summary::-webkit-details-marker { display:none; }
        .addon-other summary:hover { color:#ff7918; }
        @media (max-width: 760px) { .addon-grid { grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); } }
        a.finalize-btn { display:block; text-align:center; margin-top: 16px; background:#2a2a2a; border:1px solid #444; color:#eee; padding:13px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; }
        .finish-blocked { margin-top:16px; padding:14px 16px; border:1px solid #b87822; border-radius:8px; background:#fff4df; color:#74480f; display:flex; flex-direction:column; gap:4px; text-align:center; }
        .finish-blocked span { font-size:13px; }
        .checkin-btn { display:block; text-align:center; background:#1c2f3a; border:1px solid #3a5a72; color:#7fbcdc; padding:13px; border-radius:8px; font-size:14px; font-weight:600; width:100%; cursor:pointer; text-decoration:none; }
        .cancel-form { background:#241717; border:1px solid #4a2a2a; border-radius:8px; padding:12px 14px; }
        .cancel-form input { background:#1c1414; border:1px solid #4a2a2a; color:#fff; }
        .cancel-form button { width:100%; margin-top:8px; background:#7a2d2d; color:#fff; border:none; padding:11px; border-radius:7px; font-size:13.5px; font-weight:600; cursor:pointer; }
        .cancel-form button:hover { background:#93393a; }
        .delay-late { color:#e88a9a; }
        .delay-early { color:#6fd39a; }
        .col-actions { display:flex; justify-content:flex-end; align-items:flex-start; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
        .col-btn { display:inline-block; background:#2a2a2a; border:1px solid #444; color:#eee; padding:8px 14px; border-radius:7px; font-size:12.5px; font-weight:600; text-decoration:none; cursor:pointer; white-space:nowrap; list-style:none; }
        .col-btn:hover { border-color:#ff7918; color:#ff7918; }
        .col-btn::-webkit-details-marker { display:none; }
        details.col-cancel { flex:0 0 auto; position:relative; }
        details.col-cancel .cancel-form { position:absolute; right:0; top:calc(100% + 6px); width:340px; max-width:90vw; z-index:30; box-shadow:0 12px 30px #0008; }
        details.col-cancel .col-btn { background:transparent; border-color:#4a2a2a; color:#c47a7a; }
        details.col-cancel .col-btn:hover { border-color:#a83a3a; color:#e88a8a; }
        details.col-cancel[open] .col-btn { border-color:#a83a3a; color:#e88a8a; }
        .hold-box { display:flex; align-items:center; gap:12px 16px; flex-wrap:wrap; background:#2a2410; border:1px solid #6b5a1e; color:#e8c76f; border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:13.5px; }
        .hold-box.free { background:#14241b; border-color:#2e6e45; color:#8fe0ad; }
        .hold-box b { color:#fff; }
        .hold-box form { display:flex; gap:8px; flex-wrap:wrap; margin-left:auto; }
        .hold-box button { background:#1c1c1c; border:1px solid #6b5a1e; color:#f0d58a; padding:7px 12px; border-radius:7px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .hold-box button:hover { border-color:#ff7918; color:#ff7918; }
        /* Tres columnas: estadía | dinero y pagos | consumo */
        .page-inner { max-width: 1760px; padding-top:16px; padding-bottom:8px; }
        .page-inner > .finish-blocked, .page-inner > a.finalize-btn { margin-top:12px; }
        .page-inner > .hint { margin:6px 0 0; }
        .page-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px 20px; margin-bottom:14px; }
        .head-title { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .head-title h1 { margin:0; font-size:21px; letter-spacing:.01em; }
        .code-inline { font-family: ui-monospace, monospace; color:#ff7918; font-size:19px; }
        .pill-status { font-size:11px; letter-spacing:.03em; }
        .page-head .col-actions { margin:0; justify-content:flex-end; }
        .layout { grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(0,1.1fr); gap:16px; }
        @media (min-width: 1700px) { .layout { grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(0,2fr); } }
        .layout .card { padding:14px 18px; }
        .layout .row { padding:6px 0; font-size:14px; }
        @media (max-width: 1280px) { .layout { grid-template-columns: 1fr 1fr; } .layout > .col:nth-child(3) { grid-column: 1 / -1; } }
        @media (max-width: 760px) { .layout { grid-template-columns: minmax(0, 1fr); } }
        /* Monitor vertical: datos y cobro arriba lado a lado; consumos abajo a todo el ancho, con más productos por fila. */
        @media (orientation: portrait) and (min-width: 761px) {
            .layout { grid-template-columns: minmax(0,1fr) minmax(0,1fr); }
            .layout > .col:nth-child(3) { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <div class="page-head">
        <div class="head-title">
            <h1>Reserva <span class="code-inline">{{ $booking->code }}</span></h1>
            <span class="pill pill-status">{{ $booking->booking_status }}</span>
            <span class="pill pill-status">{{ $booking->payment_status }}</span>
        </div>
        <div class="col-actions">
        {{-- El pase es para entrar: una vez hecho el check-in o cerrada la reserva ya no sirve. --}}
        @unless ($booking->checked_in_at || in_array($booking->booking_status, ['FINALIZADA', 'CANCELADA', 'EXPIRADA', 'NO_SHOW']))
            <a class="col-btn" href="{{ route('bookings.pass.preview', $booking->pass_token) }}" target="_blank">Ver pase PDF</a>
            <a class="col-btn" href="{{ route('bookings.pass.pdf', $booking->pass_token) }}">Descargar pase</a>
        @endunless
        <a class="col-btn" href="{{ route('rooms.board') }}">← Tablero</a>
        @unless (in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA']))
            <a class="col-btn" href="{{ route('reservations.edit', $booking->code) }}">Modificar</a>
        @endunless
        @if ($booking->checked_in_at && ! in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA']))
            @if ($booking->balanceDue() <= 0)
                <a class="col-btn col-primary" href="{{ route('bookings.finalize.show', $booking->code) }}" title="Marca la salida ahora mismo, aunque sea antes de la hora reservada">Finalizar reserva →</a>
            @else
                <span class="col-btn col-disabled" title="Registra el saldo pendiente antes de marcar el check-out">Finalizar · falta cobrar ${{ number_format($booking->balanceDue(), 0, ',', '.') }}</span>
            @endif
        @endif
        @unless (in_array($booking->booking_status, ['FINALIZADA', 'CANCELADA', 'EXPIRADA', 'NO_SHOW']) || $booking->checked_in_at)
            <details class="col-cancel">
                <summary class="col-btn">Cancelar</summary>
                <form class="cancel-form" method="POST" action="{{ route('bookings.cancel', $booking->code) }}">
                    @csrf
                    <label style="display:block; font-size:12px; color:#bbb; margin-bottom:6px;">Motivo (opcional)</label>
                    <input type="text" name="reason" placeholder="Cliente no llegó, error de carga, cambio de planes...">
                    <p style="font-size:11.5px; color:#c47a7a; margin:8px 0 0;">El Playroom queda libre de inmediato al confirmar.</p>
                    <button type="submit">Confirmar cancelación</button>
                </form>
            </details>
        @endunless
    </div>
    </div>

    @if ($booking->booking_status === 'PENDIENTE_PAGO')
        @if ($booking->expires_at)
            <div class="hold-box">
                <span>⏳ Retenida hasta las <b>{{ $booking->expires_at->timezone('America/Santiago')->format('H:i') }}</b> ({{ $booking->expires_at->isFuture() ? 'vence '.$booking->expires_at->diffForHumans() : 'vencida' }}). Si no se registra el pago, se libera el Playroom.</span>
                <form method="POST" action="{{ route('bookings.hold', $booking->code) }}">
                    @csrf
                    <button type="submit" name="reason" value="recepcion">Pagará en recepción</button>
                    <button type="submit" name="reason" value="tardio">Pago tardío</button>
                </form>
            </div>
        @else
            <div class="hold-box free">✓ Reserva mantenida sin vencimiento: el Playroom queda tomado hasta que se registre el pago o se cancele.</div>
        @endif
    @endif

    @if ($booking->booking_status === 'EXPIRADA' && ! $booking->checked_in_at)
        @php $tzS = 'America/Santiago'; @endphp
        <div class="card reactivate-box" style="margin-bottom:14px;border:1px solid #6b5a1e;background:#2a2410;">
            <strong style="color:#e8c76f;">Reserva vencida: ¿el cliente quiere pagarla ahora?</strong>
            <p style="margin:6px 0 12px;font-size:13px;color:#cdbb82;">Reactívala con los mismos datos, sin crearla de nuevo. Puedes dejar la misma hora o cambiarla; si cambia, el precio se recalcula. Después aparece el link de pago para enviárselo al cliente.</p>
            @error('reactivar')
                <div style="background:#3a1c1c;border:1px solid #7a2d2d;color:#f3b8b8;padding:8px 12px;border-radius:8px;margin-bottom:10px;font-size:13px;">{{ $message }}</div>
            @enderror
            <form method="POST" action="{{ route('bookings.reactivate', $booking->code) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                @csrf
                <div>
                    <label style="display:block;font-size:12px;color:#bbb;margin-bottom:4px;">Fecha</label>
                    <input type="date" name="date" value="{{ old('date', $booking->starts_at->timezone($tzS)->toDateString()) }}" min="{{ now($tzS)->toDateString() }}" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#bbb;margin-bottom:4px;">Hora de entrada</label>
                    <input type="time" name="time" step="900" value="{{ old('time', $booking->starts_at->timezone($tzS)->format('H:i')) }}" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#bbb;margin-bottom:4px;">Retener por</label>
                    <select name="hold_minutes">
                        @foreach ([15 => '15 minutos', 30 => '30 minutos', 60 => '1 hora'] as $min => $label)
                            <option value="{{ $min }}" @selected((int) old('hold_minutes', 15) === $min)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="background:#ff7918;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-weight:700;cursor:pointer;">Reactivar reserva</button>
            </form>
        </div>
    @endif

    @php
        $hhPayment = config('services.hh_payment');
        // Sin esto configurado (HH_BANK_* / HH_MERCADOPAGO_LINK en .env), el
        // mensaje simplemente no incluye ninguna instrucción de pago -- mejor
        // que mandarle al cliente una cuenta inventada o equivocada.
        $bankLines = [];
        if ($hhPayment['bank_titular'] && $hhPayment['bank_account_number']) {
            $bankLines[] = "Datos para transferencia:";
            $bankLines[] = "Titular: {$hhPayment['bank_titular']}";
            if ($hhPayment['bank_rut']) $bankLines[] = "RUT: {$hhPayment['bank_rut']}";
            if ($hhPayment['bank_name']) $bankLines[] = "Banco: {$hhPayment['bank_name']}";
            $bankLines[] = trim(($hhPayment['bank_account_type'] ?: 'Cuenta').": {$hhPayment['bank_account_number']}");
            if ($hhPayment['bank_email']) $bankLines[] = "Correo: {$hhPayment['bank_email']}";
        }
        $paymentLines = $bankLines;
        if ($hhPayment['mercadopago_link']) {
            $paymentLines[] = ($paymentLines ? "\nTambién puedes pagar con Mercado Pago:\n" : "Paga con Mercado Pago:\n").$hhPayment['mercadopago_link'];
        }

        $whatsappMessage = "Hola {$booking->customer->name}, tu reserva en HH Motel está creada.\n\n"
            ."Código: {$booking->code}\n"
            ."Playroom: {$booking->room->name}\n"
            ."Fecha: ".$booking->starts_at->timezone('America/Santiago')->format('d/m/Y')."\n"
            ."Horario: ".$booking->starts_at->timezone('America/Santiago')->format('H:i')." a ".$booking->ends_at->timezone('America/Santiago')->format('H:i').($booking->short_stay ? ' (salida a la hora de cierre)' : '')."\n"
            ."Monto pendiente: $".number_format($booking->balanceDue(), 0, ',', '.')."\n\n"
            .($paymentLines ? implode("\n", $paymentLines)."\n\nEnvíanos el comprobante por este mismo WhatsApp.\n\n" : '')
            ."Pase de reserva: ".url(route('bookings.pass.pdf', $booking->pass_token));
        // Sin ?text= a propósito -- solo abre el chat con el cliente. El
        // mensaje armado arriba se copia aparte ("Copiar texto") y se pega a
        // mano, para poder revisarlo/editarlo dentro de WhatsApp antes de
        // mandarlo en vez de que salga prellenado.
        $whatsappContactUrl = 'https://wa.me/'.preg_replace('/[^0-9]/', '', (string) $booking->customer->phone_e164);
    @endphp
    @if ($booking->balanceDue() > 0)
        <textarea id="whatsapp-message-text" class="visually-hidden" readonly>{{ $whatsappMessage }}</textarea>
        @if ($bankLines)
            <textarea id="bank-details-text" class="visually-hidden" readonly>{{ implode("\n", $bankLines) }}</textarea>
        @endif
        <div class="payment-actions">
            @if ($booking->payment_instructions_sent_at)
                <span class="sent-tag" id="instructions-sent-tag">✓ Instrucciones enviadas {{ $booking->payment_instructions_sent_at->timezone('America/Santiago')->diffForHumans() }}</span>
            @endif
            @if ($bankLines)
                <button type="button" class="copy-btn" onclick="hhCopyText('bank-details-text', this); hhMarkInstructionsSent();">Copiar datos de cuenta</button>
            @endif
            <button type="button" class="copy-btn" onclick="hhCopyText('whatsapp-message-text', this); hhMarkInstructionsSent();">Copiar texto</button>
            <a class="whatsapp-btn" href="{{ $whatsappContactUrl }}" target="_blank" rel="noopener">Contactar por WhatsApp</a>
        </div>
    @endif

    @if (auth()->user()->canAccess('conversaciones') && ($booking->customer->ghl_contact_id || $booking->customer->phone_e164))
        <div class="payment-actions">
            <a class="conversation-btn" href="{{ route('admin.conversations.index', ['reserva' => $booking->code]) }}">Ir a la conversación</a>
        </div>
    @endif

    @if ($booking->booking_status === 'FINALIZADA')
        @php
            $reviewUrl = url(route('reviews.create', $booking->code));
            $reviewMessage = "Hola {$booking->customer->name}, gracias por alojarte en HH Motel.\n\n"
                ."¿Nos regalas 30 segundos para contarnos cómo fue tu experiencia?\n\n"
                .$reviewUrl;
        @endphp
        {{-- Abre el chat con el mensaje ya escrito: solo queda apretar enviar. --}}
        <div class="payment-actions">
            <a class="whatsapp-btn" href="{{ $whatsappContactUrl.'?text='.rawurlencode($reviewMessage) }}" target="_blank" rel="noopener">Pedir reseña por WhatsApp</a>
        </div>
    @endif

    @if (session('status'))
        <div class="banner ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="banner error">
            @foreach (array_unique($errors->all()) as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="layout">
        {{-- ===== Columna izquierda: la estadía ===== --}}
        <div class="col">
            <div class="card">
                <div class="row"><span class="muted">Playroom</span><span>{{ $booking->room->name }} ({{ $booking->room->category->name }})</span></div>
                @if ($booking->room->photos || $booking->room->videos)
                    <div class="room-media">
                        @foreach (($booking->room->photos ?? []) as $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="Foto de {{ $booking->room->name }}" loading="lazy"></a>
                        @endforeach
                        @foreach (($booking->room->videos ?? []) as $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="room-media-video">Video ↗</a>
                        @endforeach
                    </div>
                @endif
                @php $custSegment = $booking->customer->segment(); @endphp
                <div class="row">
                    <span class="muted">Cliente</span>
                    <span>
                        <a class="code-link" style="color:#ff7918; text-decoration:none;" href="{{ route('admin.customers.show', $booking->customer) }}" target="_blank" rel="noopener">{{ $booking->customer->name }}</a>
                        <span class="pill" style="margin-left:6px;">{{ strtoupper($custSegment['label']) }}</span>
                    </span>
                </div>
                @php $cust = $booking->customer; @endphp
                <div class="row"><span class="muted">{{ $cust->passport_number && ! $cust->rut ? 'Pasaporte' : 'RUT' }}</span><span>{{ $cust->rut ?: ($cust->passport_number ?: '—') }}</span></div>
                <div class="row"><span class="muted">Teléfono</span><span>{{ $cust->phone_e164 ?: '—' }}</span></div>
                <div class="row"><span class="muted">Email</span><span>{{ $cust->email ?: '—' }}</span></div>
                <div class="row"><span class="muted">Nacionalidad / edad</span><span>{{ $cust->nationality ?: '—' }}@if ($cust->age()) · {{ $cust->age() }} años @endif</span></div>
                <div class="row"><span class="muted">Personas</span><span>{{ $booking->guests_count }}</span></div>
                <div class="row"><span class="muted">Fecha / hora de ingreso</span><span>{{ $booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i') }}</span></div>
                <div class="row"><span class="muted">{{ $booking->checked_out_at ? 'Salida contratada' : 'Hora de salida' }}</span><span>{{ $booking->bookedEndsAt()->timezone('America/Santiago')->format('d/m/Y H:i') }}@if ($booking->short_stay) <span class="pill" style="margin-left:6px; background:#2e2410; color:#ffcf6e; border-color:#c98a1b;">SALE AL CIERRE</span>@endif</span></div>
                <div class="row"><span class="muted">Duración</span><span>@if ($booking->short_stay){{ \App\Support\TimeFormat::minutes((int) $booking->starts_at->diffInMinutes($booking->ends_at)) }} · estadía corta por cierre (tarifa de {{ $booking->duration_minutes / 60 }} h)@else{{ $booking->duration_minutes / 60 }} h @endif</span></div>
                <div class="row"><span class="muted">Reservado el</span><span>{{ $booking->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }}</span></div>
                <div class="row"><span class="muted">Tarifa aplicada</span><span class="pill">{{ $booking->rate_rule_name_snapshot }}</span></div>
                @if ($booking->checked_in_at)
                    @php $delay = $booking->checkInDelayMinutes(); @endphp
                    <div class="row">
                        <span class="muted">Check-in real</span>
                        <span>
                            {{ $booking->checked_in_at->timezone('America/Santiago')->format('H:i') }}
                            @if ($delay > 0)
                                <span class="delay-late">({{ $delay }} min tarde)</span>
                            @elseif ($delay < 0)
                                <span class="delay-early">({{ abs($delay) }} min antes)</span>
                            @else
                                (a tiempo)
                            @endif
                        </span>
                    </div>
                @endif
                @if ($booking->checked_out_at)
                    @php $outDiff = $booking->checkOutDiffMinutes(); @endphp
                    <div class="row">
                        <span class="muted">Salida real (check-out)</span>
                        <span>
                            {{ $booking->checked_out_at->timezone('America/Santiago')->format('d/m/Y H:i') }}
                            @if ($outDiff > 0)
                                <span class="delay-late">({{ \App\Support\TimeFormat::minutes($outDiff) }} después de la hora)</span>
                            @elseif ($outDiff < 0)
                                <span class="delay-early">({{ \App\Support\TimeFormat::minutes(abs($outDiff)) }} antes de la hora)</span>
                            @else
                                (a la hora)
                            @endif
                        </span>
                    </div>
                @endif
            </div>

            @if (auth()->user()->isAdministrador() && ! in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW'], true))
                @php $tzReal = 'America/Santiago'; @endphp
                <details style="margin:0 0 12px;" @if ($errors->has('checked_in_at') || $errors->has('checked_out_at')) open @endif>
                    <summary style="display:inline-block; cursor:pointer; padding:8px 14px; border:1px solid #ff7918; border-radius:8px; background:#fff; color:#c2410c; font-size:13px; font-weight:700;">Corregir horas reales (administrador)</summary>
                    <form method="POST" action="{{ route('bookings.real_times', $booking->code) }}" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-top:10px;">
                        @csrf
                        <label style="font-size:12px;">Entró<br><input type="datetime-local" name="checked_in_at" required value="{{ old('checked_in_at', ($booking->checked_in_at ?? $booking->starts_at)->copy()->timezone($tzReal)->format('Y-m-d\TH:i')) }}"></label>
                        <label style="font-size:12px;">Salió<br><input type="datetime-local" name="checked_out_at" required value="{{ old('checked_out_at', ($booking->checked_out_at ?? $booking->ends_at)->copy()->timezone($tzReal)->format('Y-m-d\TH:i')) }}"></label>
                        <button type="submit" class="col-btn">Guardar horas</button>
                    </form>
                    @foreach (['checked_in_at', 'checked_out_at'] as $field)
                        @error($field)<div class="delay-late" style="font-size:12px; margin-top:6px;">{{ $message }}</div>@enderror
                    @endforeach
                    <div class="muted" style="font-size:12px; margin-top:6px;">Deja la reserva finalizada con estas horas. No cambia el estado del Playroom.</div>
                </details>
            @endif

            @if (auth()->user()->isAdministrador() && $booking->booking_status === 'FINALIZADA')
                @php
                    $fixPayments = $booking->payments->where('status', 'aprobado');
                    $fixCoupons = \App\Models\Coupon::where('auto_apply', false)->where('is_active', true)->whereNull('customer_id')->orderBy('internal_name')->get(['id', 'code', 'internal_name', 'auto_apply']);
                    $fixErrors = $errors->has('price_final') || $errors->has('payments') || $errors->has('reason');
                @endphp
                <details style="margin:0 0 12px;" @if ($fixErrors) open @endif>
                    <summary style="display:inline-block; cursor:pointer; padding:8px 14px; border:1px solid #ff7918; border-radius:8px; background:#fff; color:#c2410c; font-size:13px; font-weight:700;">Corregir venta cerrada (administrador)</summary>
                    <form method="POST" action="{{ route('bookings.sale_correction', $booking->code) }}" style="display:grid; gap:10px; margin-top:10px; max-width:460px;">
                        @csrf
                        <label style="font-size:12px;">Lo que de verdad se cobró por la estadía (sin consumos) · tarifa ${{ number_format($booking->price_original, 0, ',', '.') }}<br>
                            <input type="number" name="price_final" min="0" max="{{ $booking->price_original }}" required value="{{ old('price_final', $booking->price_final) }}" style="width:100%;">
                        </label>
                        <label style="font-size:12px;">Cupón que se aplicó (opcional)<br>
                            <select name="coupon_id" style="width:100%;">
                                <option value="">— Sin cupón / como está —</option>
                                @foreach ($fixCoupons as $c)
                                    <option value="{{ $c->id }}" @selected((string) old('coupon_id', $booking->coupon_id) === (string) $c->id)>{{ $c->internal_name }}{{ $c->code ? ' ('.$c->code.')' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        @foreach ($fixPayments as $p)
                            @php $isCash = $p->paymentMethod?->code === 'efectivo'; @endphp
                            <label style="font-size:12px;">Pago {{ $p->paymentMethod?->name }}{{ $p->voucher_number ? ' · voucher '.$p->voucher_number : '' }}{{ $p->receipt_number ? ' · boleta '.$p->receipt_number : '' }}{{ $isCash ? ' (efectivo: no se corrige aquí)' : '' }}<br>
                                <input type="number" name="payments[{{ $p->id }}]" min="0" value="{{ old('payments.'.$p->id, $p->amount) }}" @disabled($isCash) style="width:100%;">
                            </label>
                        @endforeach
                        <label style="font-size:12px;">Motivo<br>
                            <input type="text" name="reason" required maxlength="300" value="{{ old('reason') }}" placeholder="Ej.: descuento estudiante aplicado en recepción, voucher 33 por $16.000" style="width:100%;">
                        </label>
                        <button type="submit" class="col-btn">Guardar corrección</button>
                    </form>
                    @foreach (['price_final', 'payments', 'reason'] as $field)
                        @error($field)<div class="delay-late" style="font-size:12px; margin-top:6px;">{{ $message }}</div>@enderror
                    @endforeach
                    <div class="muted" style="font-size:12px; margin-top:6px;">Solo guarda si lo pagado queda igual a lo vendido (precio + consumos). Queda registrado quién lo corrigió y por qué.</div>
                </details>
            @endif

            @if (! $booking->checked_in_at && ! in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA']))
                <a class="checkin-btn" href="{{ route('bookings.checkin.show', $booking->code) }}">Marcar check-in — ofrecer consumo →</a>
            @endif

            @if ($booking->guests->isNotEmpty() && $booking->guestsVisibleTo(auth()->user()))
                <div class="card">
                    <div class="row" style="border-bottom:none; padding-bottom:4px;"><span class="muted">Acompañantes</span><span></span></div>
                    <ul class="guests">
                        @foreach ($booking->guests as $guest)
                            <li>{{ $guest->name }}@if ($guest->id_document) <span style="color:#999; font-family:ui-monospace,monospace; font-size:12px;">· {{ $guest->id_document }}</span>@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>

        {{-- ===== Columna central: el dinero y los pagos ===== --}}
        <div class="col">
            <div class="card">
                <div class="row"><span class="muted">Playroom · {{ $booking->duration_minutes / 60 }} h</span><span>${{ number_format($booking->basePrice(), 0, ',', '.') }}</span></div>
                @if ($booking->extra_guests_fee > 0)
                    <div class="row"><span class="muted">Adicional · {{ $booking->extraGuests() }} {{ $booking->extraGuests() === 1 ? 'persona' : 'personas' }}</span><span>${{ number_format($booking->extra_guests_fee, 0, ',', '.') }}</span></div>
                @endif
                @if ($booking->coupon_code_snapshot)
                    <div class="row"><span class="muted">Promoción ({{ $booking->coupon_code_snapshot }})</span><span>-${{ number_format($booking->discount_amount, 0, ',', '.') }}</span></div>
                    @if (\Illuminate\Support\Facades\Schema::hasTable('coupon_proofs') && \App\Models\CouponProof::where('booking_id', $booking->id)->exists())
                        <div class="row"><span class="muted">Documento del cupón</span><a href="{{ route('bookings.coupon_proof.show', $booking->code) }}" target="_blank" rel="noopener" style="color:#ff7918;">Ver foto</a></div>
                    @endif
                @endif
                @if (! $booking->coupon_code_snapshot && ! in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true))
                    <details class="apply-coupon" @if ($errors->has('coupon_code')) open @endif style="margin:6px 0 8px;">
                        <summary style="cursor:pointer; color:#ff7918; font-size:13px;">¿Se olvidó un código de descuento? Aplicarlo</summary>
                        <form method="POST" action="{{ route('bookings.coupon.apply', $booking->code) }}" style="display:flex; gap:8px; margin-top:8px; flex-wrap:wrap;">
                            @csrf
                            <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="Código (ej. MORNING ESCAPE)" required style="flex:1; min-width:160px;">
                            <button type="submit" class="pay-btn" style="margin:0;">Aplicar</button>
                        </form>
                        @error('coupon_code')<p style="color:#ff6b6b; font-size:12.5px; margin:6px 0 0;">{{ $message }}</p>@enderror
                        <p class="muted" style="font-size:12px; margin:6px 0 0;">Se valida como al reservar (días, horario y vigencia del cupón, con el horario de esta reserva). Queda registrado quién lo aplicó.</p>
                    </details>
                @endif
                <div class="row total"><span>Precio final (Playroom)</span><span>${{ number_format($booking->price_final, 0, ',', '.') }}</span></div>
                @if ($booking->addonsTotal() > 0)
                    <div class="row"><span class="muted">Consumo</span><span>${{ number_format($booking->addonsTotal(), 0, ',', '.') }}</span></div>
                @endif
                <div class="row"><span class="muted">Abono objetivo</span><span>${{ number_format($booking->deposit_amount, 0, ',', '.') }}</span></div>
                <div class="row"><span class="muted">Pagado</span><span>${{ number_format($booking->paidAmount(), 0, ',', '.') }}</span></div>
                <div class="row total balance"><span>Saldo</span><span>${{ number_format($booking->balanceDue(), 0, ',', '.') }}</span></div>
                @if ($booking->balanceDue() > 0)
                    <a class="pay-btn pay-btn-inline" href="{{ route('payments.create', ['code' => $booking->code, 'after' => 'board']) }}">Registrar pago (abono o total) →</a>
                    @include('payments._pay-link', ['booking' => $booking])
                @endif
            </div>

            @if ($booking->payments->isNotEmpty())
                <div class="card">
                    <div class="pay-title">Pagos registrados</div>
                    @foreach ($booking->payments as $payment)
                        <div class="pay-block">
                            <div class="pay-head">
                                <span class="pay-method">{{ $payment->methodLabel() }}</span>
                                <span class="pay-amount">${{ number_format($payment->amount, 0, ',', '.') }}</span>
                                <span class="pay-status">{{ $payment->status }}</span>
                            </div>
                            @if ($payment->isMercadoPago())
                                <div class="pay-operation">
                                    @if ($payment->mercadoPagoOperation())
                                        <span>N° operación <b>{{ $payment->mercadoPagoOperation() }}</b></span>
                                        <button type="button" onclick="navigator.clipboard.writeText('{{ $payment->mercadoPagoOperation() }}').then(() => { this.textContent = 'Copiado ✓'; })">Copiar</button>
                                    @endif
                                    @if (auth()->user()->isAdministrador())
                                        @php $paidAt = $payment->created_at->copy()->timezone('America/Santiago'); @endphp
                                        <a href="{{ route('admin.mercadopago.index', ['mes' => $paidAt->format('Y-m'), 'dia' => $paidAt->toDateString(), 'voucher' => $payment->voucher_number ?: null]) }}">Ver operación en Mercado Pago →</a>
                                    @endif
                                </div>
                            @endif
                            @php $hasDoc = filled($payment->voucher_number) || filled($payment->receipt_number); @endphp
                            @if ($hasDoc)
                                {{-- Ya registrado: se muestra como dato; corregirlo es una acción aparte. --}}
                                <div class="pay-doc-done">
                                    @if (filled($payment->voucher_number))<span>Voucher <b>{{ $payment->voucher_number }}</b></span>@endif
                                    @if (filled($payment->receipt_number))<span>Boleta <b>{{ $payment->receipt_number }}</b></span>@endif
                                    <button type="button" class="pay-doc-edit" onclick="this.closest('.pay-block').querySelector('.pay-docs').hidden = false; this.hidden = true;">✎ Corregir</button>
                                </div>
                            @endif
                            @if ($payment->status === 'aprobado' && auth()->user()?->isAdministrador())
                                <details class="pay-refund" style="margin-top:8px;">
                                    <summary style="cursor:pointer;color:#c47a7a;font-size:12.5px;">Devolver este pago</summary>
                                    <form method="POST" action="{{ route('payments.refund', ['code' => $booking->code, 'payment' => $payment]) }}" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;" onsubmit="return confirm('¿Devolver $' + Number(this.amount.value || {{ $payment->amount }}).toLocaleString('es-CL') + ' a este cliente? {{ str_starts_with((string) $payment->external_id, 'MP-') ? 'Se le devolverá por Mercado Pago.' : 'Tendrás que devolver el dinero por fuera del sistema.' }}');">
                                        @csrf
                                        {{-- Por defecto el pago completo; un monto menor deja el resto como pagado. --}}
                                        <label style="display:flex;align-items:center;gap:4px;font-size:12.5px;">$ <input name="amount" type="number" min="1" max="{{ $payment->amount }}" step="1" value="{{ $payment->amount }}" required title="Monto a devolver (máximo ${{ number_format($payment->amount, 0, ',', '.') }})" style="width:110px;"></label>
                                        <input name="reason" required maxlength="255" placeholder="Motivo de la devolución" style="flex:1;min-width:160px;">
                                        <button type="submit" style="background:#7a2d2d;color:#fff;border:none;border-radius:7px;padding:8px 14px;font-weight:600;cursor:pointer;">Devolver</button>
                                    </form>
                                </details>
                            @endif
                            <form method="POST" action="{{ route('payments.document', $payment) }}" class="pay-docs" @if ($hasDoc) hidden @endif>
                                @csrf @method('PUT')
                                {{-- Mercado Pago no entrega voucher de máquina: solo se anota la boleta. --}}
                                @unless ($payment->paymentMethod->code === 'mercado_pago')
                                <label>N° voucher <input name="voucher_number" value="{{ $payment->voucher_number }}" placeholder="Voucher" maxlength="30"></label>
                                @endunless
                                <label>N° boleta <input name="receipt_number" value="{{ $payment->receipt_number }}" placeholder="Boleta" maxlength="30"></label>
                                <button type="submit">Guardar</button>
                            </form>
                        </div>
                    @endforeach
                    @php
                        $cust = $booking->customer;
                        $faltan = array_values(array_filter([
                            $cust->documentNumber() ? null : 'RUT o pasaporte',
                            $cust->nationality ? null : 'nacionalidad',
                        ]));
                    @endphp
                    @if ($faltan)
                        <p style="margin:10px 0 0;font-size:12.5px;color:#e8c76f;">Falta en la ficha del cliente: <b>{{ implode(', ', $faltan) }}</b>. <a href="{{ route('admin.customers.show', $cust) }}" style="color:#ff7918;">Completar en su ficha →</a></p>
                    @endif
                </div>
            @endif
        </div>

        {{-- ===== Columna derecha: consumo y extras ===== --}}
        <div class="col">
            @php $consumo = $booking->addonsByPaymentStatus(); @endphp

            {{-- Antes del check-in no se vende nada: solo se muestra el consumo ya cargado, si lo hay. --}}
            @if ($booking->checked_in_at || $booking->addons->isNotEmpty())
            <div class="card" id="consumo">
                <div class="row" style="border-bottom:none; padding-bottom:4px;"><span class="muted">Consumo / extras</span><span></span></div>

                @if ($booking->balanceDue() > 0)
                    <div class="pending-alert">
                        <span><strong>Saldo pendiente:</strong> ${{ number_format($booking->balanceDue(), 0, ',', '.') }}</span>
                        <small>Se cobra desde el botón superior</small>
                    </div>
                @endif

                @if ($consumo['paid']->isNotEmpty())
                    <div class="addon-sep" style="margin-top:2px;">Ya pagado</div>
                    @foreach ($consumo['paid'] as $addon)
                        <div class="row" style="opacity:.6;"><span>✓ {{ $addon->description }}</span><span>${{ number_format($addon->amount, 0, ',', '.') }}</span></div>
                    @endforeach
                @endif

                @if ($consumo['pending']->isNotEmpty())
                    <div class="addon-sep" style="color:#ff7918;">Orden nueva — pendiente de pago</div>
                    @foreach ($consumo['pending'] as $addon)
                        <div class="row"><span>{{ $addon->description }}</span><span>${{ number_format($addon->amount, 0, ',', '.') }}</span></div>
                    @endforeach
                @endif

                @if ($booking->checked_in_at && ! in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA']))
                    <form method="POST" action="{{ route('addons.store', $booking->code) }}" class="addon-panel" id="addon-panel">
                        @csrf
                        <div class="addon-head"><b>Agregar al consumo</b></div>

                        @if ($combos->isNotEmpty())
                            <div class="addon-cat">Combos</div>
                            <div class="addon-combos">
                                @foreach ($combos as $combo)
                                    <div class="addon-combo addon-tile-combo {{ $combo->isOutOfStock() ? 'out' : '' }}" data-name="{{ mb_strtolower($combo->name) }}">
                                        <div class="addon-combo-name">{{ $combo->name }}</div>
                                        <div class="addon-combo-items">{{ $combo->description ?: $combo->itemsLabel() }}</div>
                                        <div class="addon-combo-price">${{ number_format($combo->price, 0, ',', '.') }}{!! $combo->isOutOfStock() ? ' <small>agotado</small>' : '' !!}</div>
                                        <div class="qty">
                                            <button type="button" class="q-minus" aria-label="Menos" @disabled($combo->isOutOfStock())>−</button>
                                            <input type="number" name="combo_quantities[{{ $combo->id }}]" data-combo-id="{{ $combo->id }}" data-choices="{{ json_encode($combo->choicesSpec()) }}" value="0" min="0" max="{{ $combo->availableCount() !== null ? min(20, $combo->availableCount()) : 20 }}" data-price="{{ $combo->price }}" @disabled($combo->isOutOfStock())>
                                            <button type="button" class="q-plus" aria-label="Más" @disabled($combo->isOutOfStock())>+</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @php
                            $catLabels = ['bebida' => 'Bebidas', 'snack' => 'Snacks', 'sexshop' => 'Sexshop', 'servicio' => 'Servicios', 'cortesia' => 'Cortesía'];
                            $productGroups = $products->sortBy(fn ($p) => [$p->isOutOfStock() ? 1 : 0, $p->display_order, $p->name])->groupBy(fn ($p) => $p->category ?: 'otros');
                            $outCount = $products->filter->isOutOfStock()->count() + $combos->filter->isOutOfStock()->count();
                        @endphp
                        <div class="addon-tools">
                            <input type="search" id="addon-search" placeholder="🔍 Buscar producto…" autocomplete="off">
                            <div class="addon-chips" id="addon-chips">
                                <button type="button" class="on" data-cat="">Todos</button>
                                @foreach ($productGroups->keys() as $cat)
                                    <button type="button" data-cat="{{ $cat }}">{{ $catLabels[$cat] ?? ucfirst($cat) }}</button>
                                @endforeach
                            </div>
                            @if ($outCount)
                                <label class="addon-show-out"><input type="checkbox" id="addon-show-out"> Ver agotados ({{ $outCount }})</label>
                            @endif
                        </div>

                        @foreach ($productGroups as $cat => $items)
                            <div class="addon-block" data-cat="{{ $cat }}">
                                <div class="addon-cat">{{ $catLabels[$cat] ?? ucfirst($cat) }}</div>
                                <div class="addon-grid">
                                    @foreach ($items as $product)
                                        <div class="addon-tile {{ $product->isOutOfStock() ? 'out' : ($product->isLowStock() ? 'low' : '') }}" data-name="{{ mb_strtolower($product->name) }}">
                                            <div class="addon-name">{{ $product->name }}</div>
                                            <div class="addon-meta">
                                                <span class="addon-price">${{ number_format($product->price, 0, ',', '.') }}</span>
                                                @if ($product->track_inventory)
                                                    <span class="addon-stock">{{ $product->isOutOfStock() ? 'Agotado' : $product->stock.' disp.' }}</span>
                                                @endif
                                            </div>
                                            <div class="qty">
                                                <button type="button" class="q-minus" aria-label="Menos" @disabled($product->isOutOfStock())>−</button>
                                                <input type="number" name="quantities[{{ $product->id }}]" value="0" min="0" max="{{ $product->track_inventory ? min(20, $product->stock) : 20 }}" data-price="{{ $product->price }}" @disabled($product->isOutOfStock())>
                                                <button type="button" class="q-plus" aria-label="Más" @disabled($product->isOutOfStock())>+</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <p class="addon-empty" id="addon-empty" hidden>No hay productos con ese nombre.</p>

                        <div class="addon-bar">
                            <span class="addon-total" id="addon-total">Nada seleccionado</span>
                            <button type="submit" class="addon-submit" id="addon-submit" disabled>Agregar al consumo</button>
                        </div>
                    </form>

                    <div class="addon-more">
                        <form method="POST" action="{{ route('addons.extra-hour', $booking->code) }}">
                            @csrf
                            @if ($extraHourPrice > 0)
                                <button type="submit" class="addon-hour">⏱ Vender 1 hora adicional <b>${{ number_format($extraHourPrice, 0, ',', '.') }}</b></button>
                            @else
                                <div class="addon-hour-off">
                                    <b>Hora adicional sin precio.</b> La tarifa {{ $booking->rate_rule_name_snapshot ?? '' }} no lo tiene configurado.
                                    @if (auth()->user()?->isAdministrador())
                                        <a href="{{ route('admin.rates.index') }}" style="color:#ff7918;">Configurarlo →</a>
                                    @else
                                        Avísale a un administrador.
                                    @endif
                                </div>
                            @endif
                        </form>
                        <details class="addon-other">
                            <summary>+ Otro concepto que no está en la lista</summary>
                            <form class="addon-form" method="POST" action="{{ route('addons.store', $booking->code) }}">
                                @csrf
                                <input type="text" name="description" placeholder="Descripción">
                                <input type="number" name="amount" placeholder="$" min="1">
                                <button type="submit">Agregar</button>
                            </form>
                        </details>
                    </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if (auth()->user()?->isSoporte())
        @php
            $closed = in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW'], true);
            $hadStay = ! $booking->canBeDeleted();
        @endphp
        <div class="card" style="margin-top:22px;border:1px solid #5a2327;background:#1d1315;">
            <strong>Eliminar reserva</strong>
            @if ($hadStay)
                <p style="margin:6px 0 12px;font-size:13px;color:#bbb;">Esta reserva ya tuvo check-in{{ $booking->booking_status === 'FINALIZADA' ? ' y está finalizada' : '' }}. Solo soporte puede borrarla. Hazlo únicamente si es una <b>prueba o un error de carga</b>: se borra{{ $booking->payments->isNotEmpty() ? ' junto con sus pagos ($'.number_format($booking->paidAmount(), 0, ',', '.').')' : '' }}{{ $booking->addons->isNotEmpty() ? ' y sus consumos' : '' }}, <b>descuenta sus montos de la caja y de las ventas</b>, devuelve al inventario lo consumido y <b>no se puede deshacer</b>. Queda registrada en auditoría. El Playroom no cambia de estado.</p>
            @elseif ($closed)
                <p style="margin:6px 0 12px;font-size:13px;color:#bbb;">Esta reserva no llegó a ocurrir ({{ $booking->booking_status }}). Si es un <b>dato de prueba</b>, puedes borrarla{{ $booking->payments->isNotEmpty() ? ' junto con sus pagos' : '' }}. Esto descuenta sus montos de la caja y de las ventas, y <b>no se puede deshacer</b>. Queda registrada en auditoría.</p>
            @else
                <p style="margin:6px 0 12px;font-size:13px;color:#bbb;">Esta reserva sigue vigente ({{ $booking->booking_status }}) y todavía no tuvo check-in. Si fue una <b>prueba o un error</b>, puedes borrarla{{ $booking->payments->isNotEmpty() ? ' junto con sus pagos' : '' }}: libera el Playroom y descuenta sus montos de la caja y de las ventas. Si el cliente solo canceló, mejor usa <b>Cancelar</b> para conservar el registro. <b>No se puede deshacer.</b></p>
            @endif
            <form method="POST" action="{{ route('bookings.destroy', $booking->code) }}" onsubmit="return confirm('¿Eliminar la reserva {{ $booking->code }}? No se puede deshacer.');">
                @csrf @method('DELETE')
                <label style="display:block;font-size:12.5px;color:#bbb;margin-bottom:6px;">Para confirmar, escribe <b>ELIMINAR</b></label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input name="confirm_text" autocomplete="off" placeholder="ELIMINAR" style="max-width:200px;">
                    <button type="submit" style="background:#b3202f;color:#fff;border:0;border-radius:8px;padding:9px 16px;font-weight:700;cursor:pointer;">Eliminar reserva</button>
                </div>
            </form>
        </div>
    @endif



    </div>
    <script>
        // Deja constancia de que recepción ya le mandó los datos de pago al
        // cliente (por WhatsApp o copiando el texto) -- así el panel de
        // "Pendientes de gestionar" del tablero puede distinguir una reserva
        // recién llegada de una que ya se gestionó y solo falta que el
        // cliente transfiera. Silencioso: si falla, no interrumpe el flujo
        // real (enviar el WhatsApp o copiar el texto), solo no se actualiza
        // el tag hasta la próxima carga de la página.
        let hhInstructionsMarked = false;
        function hhMarkInstructionsSent() {
            if (hhInstructionsMarked) return;
            hhInstructionsMarked = true;
            fetch('{{ route('bookings.payment_instructions', $booking->code) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then((r) => r.ok ? r.json() : Promise.reject()).then(() => {
                if (!document.getElementById('instructions-sent-tag')) {
                    const tag = document.createElement('span');
                    tag.className = 'sent-tag';
                    tag.id = 'instructions-sent-tag';
                    tag.textContent = '✓ Instrucciones enviadas recién';
                    document.querySelector('.payment-actions').prepend(tag);
                }
            }).catch(() => { hhInstructionsMarked = false; });
        }

        // navigator.clipboard falla callado en varios navegadores/contextos
        // sin HTTPS -- con execCommand('copy') como respaldo, y mostrando el
        // resultado real (no el optimista), igual que en catalog/room.
        function hhCopyText(elementId, btn) {
            const el = document.getElementById(elementId);
            const original = btn.textContent;
            const show = (ok) => {
                btn.textContent = ok ? '¡Copiado!' : 'No se pudo copiar';
                setTimeout(() => { btn.textContent = original; }, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(el.value).then(() => show(true)).catch(() => {
                    el.select();
                    try { show(document.execCommand('copy')); } catch (e) { show(false); }
                });
            } else {
                el.select();
                try { show(document.execCommand('copy')); } catch (e) { show(false); }
            }
        }
    </script>
    <script>
        // Consumo durante la estadía: se eligen cantidades y se agrega todo junto.
        (function () {
            const form = document.getElementById('addon-panel');
            if (!form) return;
            const inputs = Array.from(form.querySelectorAll('.qty input'));
            const total = document.getElementById('addon-total');
            const submit = document.getElementById('addon-submit');
            const money = (n) => '$' + Number(n).toLocaleString('es-CL');
            function refresh() {
                let sum = 0, items = 0;
                inputs.forEach((i) => {
                    const q = Math.max(0, Number(i.value) || 0);
                    sum += q * (Number(i.dataset.price) || 0);
                    items += q;
                    i.closest('.qty').classList.toggle('has', q > 0);
                });
                total.textContent = items ? (money(sum) + ' · ' + items + (items === 1 ? ' ítem' : ' ítems')) : 'Nada seleccionado';
                total.classList.toggle('has', items > 0);
                submit.disabled = items === 0;
                submit.textContent = items ? ('Agregar ' + items + (items === 1 ? ' ítem' : ' ítems') + ' · ' + money(sum)) : 'Agregar al consumo';
            }
            form.querySelectorAll('.qty').forEach((box) => {
                const input = box.querySelector('input');
                const step = (d) => {
                    const min = Number(input.min) || 0, max = Number(input.max) || 20;
                    input.value = Math.min(max, Math.max(min, (Number(input.value) || 0) + d));
                    refresh();
                };
                box.querySelector('.q-minus').addEventListener('click', () => step(-1));
                box.querySelector('.q-plus').addEventListener('click', () => step(1));
            });
            refresh();

            // Buscador, categorías y agotados: solo esconden, lo elegido no se pierde.
            const search = document.getElementById('addon-search');
            const chips = document.getElementById('addon-chips');
            const showOut = document.getElementById('addon-show-out');
            let cat = '';
            function filter() {
                const q = (search.value || '').trim().toLowerCase();
                let visible = 0;
                form.querySelectorAll('.addon-block').forEach((block) => {
                    let any = false;
                    block.querySelectorAll('.addon-tile').forEach((t) => {
                        const ok = (!cat || block.dataset.cat === cat) && (!q || t.dataset.name.includes(q));
                        t.classList.toggle('hide', !ok);
                        if (ok && (!t.classList.contains('out') || form.classList.contains('show-out'))) any = true;
                    });
                    block.classList.toggle('hide', !any);
                    if (any) visible++;
                });
                form.querySelectorAll('.addon-combo').forEach((c) => c.classList.toggle('hide', !!cat || (q && !c.dataset.name.includes(q))));
                document.getElementById('addon-empty').hidden = visible > 0;
            }
            search.addEventListener('input', filter);
            chips.addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (!b) return;
                cat = b.dataset.cat;
                chips.querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
                filter();
            });
            if (showOut) showOut.addEventListener('change', () => { form.classList.toggle('show-out', showOut.checked); filter(); });
            filter();
        })();
    </script>
@include('partials.combo-choices')
</body>
</html>
