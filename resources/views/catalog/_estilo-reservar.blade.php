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

    /* Nada de verde: los avisos van en el mismo beige de la página. */
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
