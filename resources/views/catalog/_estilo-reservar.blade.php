{{--
    Estilo de la pantalla de reservar: solo repinta, no cambia ni un paso del
    formulario. Va después del <style> propio de la página y de
    _estilo-nuevo, que trae los colores comunes del catálogo.
--}}
<style>
    :root {
        --hh-bg: #f6f4f0;
        --hh-bg-2: #ffffff;
        --hh-line: #e4e0d8;
        --hh-soft: #6c6f75;
        --hh-tinta: #1b1c1e;
    }
    body { background: var(--hh-bg); color: var(--hh-tinta); }
    html { background: var(--hh-bg); }
    .wrap { max-width: 560px; padding-top: 26px; }
    h1 { font-size: 32px; font-weight: 800; letter-spacing: -.035em; }
    p.sub { color: var(--hh-soft); }
    a.back { color: var(--hh-soft); }

    /* La tarjeta del formulario pasa de oscura a blanca. */
    .card { background: var(--hh-bg-2); color: var(--hh-tinta); border: 1px solid var(--hh-line); border-radius: 18px; box-shadow: 0 2px 12px rgba(30,25,20,.05); }
    .step-title { color: var(--hh-tinta); }
    .step-back { color: var(--hh-soft); }
    label { color: #4b4e54; }
    .field-note, .time-range, .tp-empty, .legal, .extras p { color: var(--hh-soft); }
    .tp-note { color: #a8541a; }

    .steps { color: #9a9da2; }
    .steps button { border-bottom-color: var(--hh-line); color: #9a9da2; }
    .steps button.done { color: #4b4e54; border-color: #c9c5bd; }
    .steps button.on { color: var(--hh-accent); border-color: var(--hh-accent); }

    /* Campos: fondo claro, borde suave, texto oscuro. */
    input, select { background: #fbfaf8; border: 1px solid #d6d2c9; color: var(--hh-tinta); border-radius: 12px; }
    input:focus, select:focus { outline: 2px solid #ffc28f; outline-offset: 1px; border-color: var(--hh-accent); }
    input[type="date"], input[type="time"] { color-scheme: light; }
    input::placeholder { color: #a9a49d; }

    .people-stepper button { background: #f2efe9; border-color: #d6d2c9; color: var(--hh-tinta); border-radius: 14px; }
    .people-stepper button:active:not(:disabled) { background: var(--hh-accent); border-color: var(--hh-accent); color: #21170e; }

    .tp { background: #faf8f5; border-color: var(--hh-line); border-radius: 16px; }
    .tp-col button { background: #f2efe9; border-color: #d6d2c9; color: var(--hh-tinta); border-radius: 12px; }
    .tp-col button:active:not(:disabled) { background: var(--hh-accent); border-color: var(--hh-accent); color: #21170e; }
    .tp-col output { background: #fff; border-color: #d6d2c9; color: var(--hh-tinta); border-radius: 12px; }
    .tp-colon { color: #b4b0a8; }

    .recap { background: #faf8f5; border-color: var(--hh-line); color: #3a3d42; border-radius: 12px; }
    .recap button { color: #a8541a; }

    /* Avisos del formulario. */
    .hint-box { background: #f3f0ea; border-color: var(--hh-line); color: #4b4e54; border-radius: 12px; }
    .errors, .step-error { background: #fdeeee; border: 1px solid #f0cccc; color: #9a2b2b; border-radius: 12px; }
    .consent-row { color: #4b4e54; }
    .consent-row a { color: #a8541a; }
    .consent-error { color: #c0392b; }

    .coupon-applied { background: #f0f8fc; border-color: #cfe4f0; color: #1c6b96; border-radius: 12px; }
    .coupon-applied a { color: #1c6b96; }
    .coupon-warning { background: #fff7e8; border-color: #f0dcb4; color: #8a6412; border-radius: 12px; }
    .coupon-entry button { border-radius: 12px; color: #a8541a; }
    .coupon-error { color: #9a2b2b; }

    .avail-status { border-radius: 12px; }
    .avail-status.checking { background: #f2efe9; color: #4b4e54; }
    .avail-status.ok { background: #f3f0ea; border: 1px solid var(--hh-line); color: #2a2c30; font-weight: 650; }
    .avail-status.no { background: #fdeeee; border: 1px solid #f0cccc; color: #9a2b2b; }
    .avail-status.short { background: #fff7e8; border: 1px solid #f0dcb4; color: #7a5a14; }
    .avail-status.short b { color: #1b1c1e; }
    .short-accept { border-color: #e0c489; color: #7a5a14; border-radius: 12px; }

    /* Extras: una tarjeta de producto, sin la barra de color al costado
       que la hacía parecer un cuadro de advertencia. */
    .extra-item { background: var(--hh-bg-2); border: 1px solid var(--hh-line); border-left: 1px solid var(--hh-line); border-radius: 14px; box-shadow: none; padding: 14px; }
    .extra-item[hidden] { display:none; }
    .extra-name { color: var(--hh-tinta); }
    .extra-detail { color: var(--hh-soft); }
    .extra-badge { background: #f2efe9; color: #6c6f75; font-weight: 700; letter-spacing: .06em; }
    .extra-price { color: var(--hh-tinta); font-weight: 800; }
    .extras-total { color: var(--hh-soft); }
    .extras h2 { color: var(--hh-tinta); }

    .summary { border-color: var(--hh-line); border-radius: 14px; }

    .booking-price { position:sticky; top:calc(64px + env(safe-area-inset-top, 0px)); z-index:35; background:#fff; color:var(--hh-tinta,#202123); border:1px solid var(--hh-line,#dedbd5); border-radius:14px; padding:14px 16px; margin:0 0 20px; box-shadow:0 4px 14px #0000000d; }
    .booking-price [hidden] { display:none !important; }
    .price-heading { display:flex; justify-content:space-between; font-weight:800; margin-bottom:8px; }
    .price-heading > span { color:var(--hh-soft,#6c6f75); font-size:12px; font-weight:600; }
    .price-row { display:flex; justify-content:space-between; align-items:baseline; gap:12px; font-size:14px; line-height:1.6; }
    .price-row > :last-child { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .price-discount { color:#27633d; }
    .price-total { border-top:1px solid var(--hh-line,#dedbd5); margin-top:7px; padding-top:7px; font-size:18px; }
    .price-note, #price-empty { color:var(--hh-soft,#6c6f75); font-size:12px; line-height:1.4; }
    .price-note { margin:6px 0 0; }
    .price-note:empty { display:none; }
    .price-retry { margin-top:8px; border:1px solid var(--hh-accent); border-radius:8px; background:#fff; padding:8px 12px; color:var(--hh-tinta); cursor:pointer; }
    @media(max-height:650px) { .booking-price { position:static; } }

    /* Botones redondos, como en el catálogo. */
    button.submit { border-radius: 14px; box-shadow: 0 8px 24px rgba(255,121,24,.28); }
    /* Sin texto, la línea no deja un hueco bajo el selector de hora. */
    .time-range:empty { min-height: 0; margin-top: 0; }

    /* Diseño de la propuesta: foto protagonista, cuatro selectores y total junto a la acción. */
    .wrap { padding:16px 20px 280px; max-width:560px; }
    h1 { font-size:29px; margin:0 0 8px; }
    p.sub { font-size:14px; margin:0 0 10px; line-height:1.5; }
    .card { background:transparent; border:0; padding:0; box-shadow:none; }
    .steps { margin-bottom:14px; gap:12px; }
    .steps button { font-size:11px; text-transform:uppercase; letter-spacing:.08em; padding:12px 0; border-bottom-width:3px; }
    .room-hero { margin:0 0 16px; height:180px; border-radius:16px; overflow:hidden; position:relative; background:#ded5ca; }
    .room-hero img { width:100%; height:100%; object-fit:cover; }
    .room-hero img[hidden] { display:none; }
    .room-hero figcaption { position:absolute; bottom:0; left:0; right:0; padding:28px 16px 16px; color:white; background:linear-gradient(transparent,rgba(0,0,0,.72)); }
    .room-hero figcaption span { display:block; font-size:9px; letter-spacing:.18em; margin-bottom:4px; }
    .room-hero figcaption label { display:none; }
    .room-hero figcaption select { background:transparent; border:0; border-radius:0; color:white; font-size:23px; font-weight:750; padding:0; min-height:32px; }
    .room-hero figcaption select option { background:white; color:#202123; }
    .room-hero figcaption strong { font-size:23px; font-weight:750; }
    label { font-size:12px; font-weight:650; margin:12px 0 6px; }
    input, select { background:#fff; min-height:48px; font-size:15px; width:100%; padding:10px 12px; }
    .stay-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:0 12px; }
    .stay-grid > div { min-width:0; }
    .stay-grid input { box-sizing:border-box; max-width:100%; }
    .avail-status.ok { background:#eaf3e9; border:0; color:#347544; font-size:13px; padding:12px; margin-top:14px; }

    .booking-price { position:fixed; top:auto; bottom:0; left:50%; transform:translateX(-50%); width:min(560px,100%); box-sizing:border-box; border:1px solid #e4e0d8; border-radius:20px 20px 0 0; padding:16px 20px calc(12px + env(safe-area-inset-bottom,0px)); margin:0; z-index:40; box-shadow:0 -6px 28px #392c1510; }
    .price-heading { display:none; }
    .price-row { font-size:13px; line-height:1.6; }
    .price-total { font-size:21px; margin-top:8px; padding-top:10px; }
    .booking-price button.submit { width:100%; margin:12px 0 0; min-height:48px; font-size:16px; background:#ff781c; border:0; color:#fff; font-weight:750; border-radius:12px; box-shadow:none; }
    .booking-help { display:block; text-align:center; font-size:10px; color:#74716b; margin-top:10px; text-decoration:none; }
    .step-action { display:none !important; }
    .extras h2 { display:none; }
    .extras { margin:0; padding:0; border:0; }
    .extras p { font-size:14px; margin-top:0; }
    .extra-item { padding:18px 14px; align-items:center; gap:12px; margin-top:12px; }
    .extra-item input { width:20px; height:20px; }
    .extra-badge { display:none; }
    .extra-price { font-size:14px; }
    .extra-name { font-size:16px; }
    .coupon-entry { margin-bottom:12px; }
    body[data-reserve-step="2"] p.sub, body[data-reserve-step="3"] p.sub { display:none; }
    @media(min-width:760px) { .room-hero { height:250px; } }
    @media(max-height:650px) { .booking-price { position:fixed; } .booking-help { display:none; } }
    .wrap > p.sub { display:none; }
    .wrap > h1 { font-size:27px; margin-bottom:8px; }
    .room-hero { height:160px; }
    .steps button { padding:10px 0; }
    .stay-grid label { margin-top:8px; }
    .stay-grid input, .stay-grid select { min-height:44px; }
</style>
<script>
    // "Horario disponible: 12:15 a 05:45" confunde: se lee como el horario del
    // motel cuando en realidad son las horas de llegada que quedan hoy. Se
    // oculta solo ese texto; los avisos de "sin horarios" y "elige la hora"
    // se siguen mostrando.
    document.addEventListener('DOMContentLoaded', function () {
        const caja = document.getElementById('time-range');
        if (!caja) return;
        const limpiar = () => {
            if (caja.textContent.trim().startsWith('Horario disponible')) caja.textContent = '';
        };
        new MutationObserver(limpiar).observe(caja, { childList: true, characterData: true, subtree: true });
        limpiar();
    });
</script>
