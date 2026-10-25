<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>HH Motel — Nueva reserva</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 1200px; margin: 0; padding: 24px 24px 60px; }
        .page-head { display:flex; align-items:baseline; gap:12px; margin-bottom:28px; padding-bottom:18px; border-bottom:1px solid #262626; }
        h1 { font-size: 22px; font-weight:700; letter-spacing:.01em; margin:0; }
        .sub { color:#999; font-size: 13.5px; margin:0; }
        label { display:block; font-size: 11.5px; font-weight:600; text-transform:uppercase; letter-spacing:.045em; color:#999; margin: 20px 0 8px; }
        input, select, textarea { width:100%; background:#161616; border:1px solid #333; color:#fff; padding:11px 13px; border-radius:8px; font-size:15px; font-family:inherit; transition: border-color .12s, box-shadow .12s; }
        input:hover, select:hover, textarea:hover { border-color:#484848; }
        input:focus, select:focus, textarea:focus { outline:none; border-color:#ff7918; box-shadow: 0 0 0 3px rgba(255,121,24,.18); }
        input:disabled { opacity:.5; cursor:not-allowed; }
        textarea { resize: vertical; min-height: 60px; }
        .panel { background:#161616; border:1px solid #272727; border-radius:14px; padding:24px 26px; }
        @media (max-width: 700px) { .panel { padding:20px; } }
        .row { display:flex; gap:14px; }
        .row > div { flex:1; }
        /* Fecha (una rueda) y Hora (dos ruedas lado a lado) no pesan lo
           mismo -- a 50/50 estricto, Hora no entraba en su mitad y las
           ruedas se salían del panel en pantallas anchas (el panel de "La
           estadía" ya es la mitad de la página, así que Hora terminaba con
           menos de 300px). Con wrap, si no entran lado a lado, Hora baja a
           su propia línea en vez de desbordar. */
        .row-datetime { flex-wrap: wrap; }
        /* hh-theme.css trae ".panel > .row > div { flex:1 1 230px }" con la
           misma especificidad y carga después (via navbar) -- le gana en el
           cascade a un ".row-datetime > div" simple. Hay que igualar el
           prefijo ".panel >" para ganar por especificidad, no por orden. */
        .panel > .row-datetime > div:first-child { flex: 1 1 220px; }
        .panel > .row-datetime > div:last-child { flex: 1 1 300px; }
        .dob-row { display:flex; gap:8px; }
        .dob-row select { flex:1; min-width:0; }
        .guest-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
        .guest-row input { flex:1; min-width:0; }
        .guest-row .guest-remove { flex:none; width:auto; background:#241717; border:1px solid #4a2a2a; color:#c47a7a; padding:10px 13px; border-radius:7px; cursor:pointer; font-size:14px; }
        .guest-row .guest-remove:hover { border-color:#a83a3a; color:#e88a8a; }
        .guest-add-btn { width:100%; background:#1c1c1c; border:1px dashed #444; color:#999; padding:10px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; }
        .guest-add-btn:hover { border-color:#ff7918; color:#ff7918; }
        .doc-row { display:flex; gap:10px; }
        .doc-row select { flex:0 0 130px; }
        .doc-row input { flex:1; }
        .doc-row input.doc-valid { border-color:#2e6e45; }
        .doc-row input.doc-invalid { border-color:#7a2d2d; }
        #document-hint.err { color:#f3b8b8; }
        #document-hint.ok { color:#8fe0ad; }
        .doc-missing-warn { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:10px 12px; border-radius:8px; font-size:13px; font-weight:600; margin-bottom:12px; }
        .time-row { display:flex; gap:8px; }
        .time-row select { flex: 1 1 0; width:auto; text-align:center; }
        button.submit { width:100%; margin-top:32px; background:#ff7918; color:#fff; border:none; padding:14px; border-radius:8px; font-size:15px; font-weight:600; letter-spacing:.02em; cursor:pointer; }
        button.submit:disabled { background:#4a2a35; cursor:not-allowed; opacity:.7; }
        .time-warning { display:none; background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:8px 10px; border-radius:7px; font-size:12px; margin-top:6px; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        .hint { font-size: 11.5px; color:#777; margin-top:5px; text-transform:none; letter-spacing:normal; font-weight:400; }
        .time-quick { display:flex; gap:6px; margin-top:8px; }
        .time-quick button { width:auto; flex:1; background:#1c1c1c; border:1px solid #333; color:#ccc; padding:7px 4px; border-radius:6px; font-size:11.5px; font-weight:500; cursor:pointer; }
        .time-quick button:hover { border-color:#ff7918; color:#ff7918; }
        .wheel { display:flex; align-items:center; gap:3px; background:#161616; border:1px solid #333; border-radius:9px; padding:4px; user-select:none; touch-action: pan-y; }
        .wheel-arrow { flex:none; width:24px; height:30px; background:#1c1c1c; border:1px solid #333; color:#888; border-radius:6px; cursor:pointer; font-size:14px; line-height:1; padding:0; }
        .wheel-arrow:hover { border-color:#ff7918; color:#ff7918; }
        .wheel-track { display:flex; align-items:center; justify-content:center; gap:2px; flex:1; overflow:hidden; }
        .wheel-item { flex:none; text-align:center; padding:5px 4px; font-size:11.5px; font-weight:600; color:#555; cursor:pointer; border-radius:6px; width:32px; line-height:1.25; }
        .wheel-item:hover { color:#aaa; }
        .wheel-item.disabled { color:#3a3a3a; cursor:default; }
        .wheel-item.disabled:hover { color:#3a3a3a; }
        .wheel-item .dow { display:block; font-size:9px; text-transform:uppercase; letter-spacing:.03em; }
        .wheel-item .dnum { display:block; font-size:13px; }
        .wheel-item.center { font-size:15px; color:#fff; background:#2a1520; border:1px solid #ff7918; cursor:default; width:44px; padding:5px 6px; }
        .wheel-item.center .dnum { font-size:16px; font-weight:700; }
        .wheel-sm .wheel-item { width:26px; font-size:13px; }
        .wheel-sm .wheel-item.center { width:34px; font-size:16px; }
        .wheel-date .wheel-track { gap:1px; }
        .date-month-badge { display:inline-block; margin-left:6px; background:#2a1520; border:1px solid #ff7918; color:#f5a3c7; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:2px 8px; border-radius:20px; vertical-align:middle; }
        .wheel-item .dow, .wheel-item .dnum { width:100%; }
        .cat-quick { display:flex; gap:6px; margin-bottom:8px; flex-wrap:wrap; }
        .cat-quick button { width:auto; background:#1c1c1c; border:1px solid #333; color:#888; padding:6px 13px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
        .cat-quick button[data-cat="todas"] { color:#ccc; }
        .cat-quick button[data-cat="go"]   { color:#5b9dd9; }
        .cat-quick button[data-cat="lite"] { color:#4ecdc4; }
        .cat-quick button[data-cat="plus"] { color:#b088e8; }
        .cat-quick button[data-cat="max"]  { color:#f2994a; }
        .cat-quick button[data-cat="new-lite"] { color:#e8c76f; background:#2a2410; border-color:#6b5a1e; }
        .cat-quick button:hover { border-color: currentColor; }
        .cat-quick button.active { border-color: currentColor; background:#242424; }
        .cat-quick button[data-cat="new-lite"].active { background:#3a3216; }
        .dur-quick { display:flex; gap:6px; flex-wrap:wrap; }
        .dur-quick button { flex:1; min-width:52px; width:auto; background:#1c1c1c; border:1.5px solid #333; color:#999; padding:9px 4px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; transition: background .1s, color .1s, border-color .1s; }
        .dur-quick button.dur-60   { color:#4ecdc4; }
        .dur-quick button.dur-120  { color:#5b9dd9; }
        .dur-quick button.dur-180  { color:#b088e8; }
        .dur-quick button.dur-360  { color:#f2994a; }
        .dur-quick button.dur-720  { color:#ff7918; }
        .dur-quick button:hover { border-color: currentColor; }
        .dur-quick button.active { color:#fff; border-color: transparent; }
        .dur-quick button.dur-60.active   { background:#4ecdc4; }
        .dur-quick button.dur-120.active  { background:#5b9dd9; }
        .dur-quick button.dur-180.active  { background:#b088e8; }
        .dur-quick button.dur-360.active  { background:#f2994a; }
        .dur-quick button.dur-720.active  { background:#ff7918; }
        .cust-badge { font-size:12.5px; margin-top:8px; padding:8px 12px; border-radius:7px; line-height:1.4; }
        .cust-badge.known { background:#14251c; border:1px solid #2e6e45; color:#8fe0ad; }
        .cust-badge.known.hot { background:#2a2410; border:1px solid #7a6a1e; color:#f0d98a; }
        .cust-badge.nuevo { background:#1c2f3a; border:1px solid #3a5a72; color:#a9d3e6; }
        .cust-ficha { background:#1c1c1c; border:1px solid #333; border-radius:9px; padding:12px 14px; margin-top:14px; }
        .cust-ficha .ficha-row { display:flex; justify-content:space-between; gap:12px; padding:4px 0; font-size:14px; }
        .cust-ficha .ficha-label { color:#888; font-size:11.5px; text-transform:uppercase; letter-spacing:.03em; }
        .cust-ficha .ficha-value { font-weight:600; text-align:right; }
        .ficha-edit-btn { margin-top:8px; width:100%; background:transparent; border:1px solid #444; color:#bbb; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .ficha-edit-btn:hover { border-color:#ff7918; color:#ff7918; }
        #cust-editable-fields[hidden] { display:none; }
        .price-box { margin-top:10px; padding:12px 14px; border-radius:8px; background:#1c1c1c; border:1px solid #333; font-size:13.5px; }
        .price-box.has-offer { background:#241520; border-color:#7a3355; }
        .price-box .p-sum { display:flex; justify-content:space-between; align-items:baseline; padding:3px 0; color:#bbb; }
        .price-box .p-sum.disc { color:#f5a3c7; }
        .price-box .p-sum.sub { color:#888; font-size:12.5px; }
        .price-box .p-sum.total { border-top:1px solid #333; margin-top:5px; padding-top:7px; color:#eee; }
        .price-box .p-sum.total span:first-child { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#999; }
        .price-box .p-sum.total span:last-child { font-family: ui-monospace, monospace; font-weight:700; font-size:18px; }
        .price-box .p-sum.total span:last-child.offer { color:#f5a3c7; }
        .price-box .p-note { font-size:11.5px; color:#e8c76f; margin-top:6px; }
        .price-box .p-note.err { color:#f3b8b8; }
        .price-box .p-err { color:#f3b8b8; font-size:13px; }
        .price-box .p-wait { color:#777; font-size:12.5px; }

        .upsell-overlay { position:fixed; inset:0; background:rgba(0,0,0,.72); display:flex; align-items:center; justify-content:center; z-index:200; padding:20px; }
        .upsell-overlay[hidden] { display:none; }
        .upsell-modal { background:#1c1c1c; border:1px solid #444; border-radius:12px; padding:20px 22px; max-width:440px; width:100%; max-height:88vh; display:flex; flex-direction:column; }
        .upsell-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .upsell-title { font-size:16px; font-weight:700; color:#f0d98a; }
        .upsell-x { width:auto; background:none; border:none; color:#888; font-size:18px; cursor:pointer; padding:0; margin:0; }
        .upsell-sub { font-size:13px; color:#bbb; margin:6px 0 14px; line-height:1.4; flex:none; }
        .upsell-list { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; overflow-y:auto; flex:1; }
        .upsell-item { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#141414; border:1px solid #2c2c2c; border-radius:9px; padding:10px 12px; }
        .upsell-item.picked { border-color:#e8c76f; background:#211d10; }
        .ui-name { font-size:14px; font-weight:600; }
        .ui-price { font-size:12px; color:#999; margin-top:2px; }
        .ui-pick { width:auto; flex:none; background:#2a2a2a; border:1px solid #555; color:#eee; border-radius:7px; padding:7px 13px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .ui-pick:hover { border-color:#ff7918; }
        .upsell-item.picked .ui-pick { background:#e8c76f; color:#1c1c1c; border-color:#e8c76f; }
        .upsell-add { width:100%; margin:0 0 8px; background:#ff7918; color:#fff; border:none; padding:13px; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
        .upsell-skip { width:100%; margin:0; background:transparent; border:1px solid #444; color:#aaa; padding:11px; border-radius:8px; font-size:13.5px; cursor:pointer; }
        .upsell-skip:hover { border-color:#666; color:#ccc; }
        /* stretch (no align-items:start): en pantallas anchas "El cliente"
           siempre es mas alto que "La estadia" -- con align-items:start el
           panel corto se quedaba mas bajo y dejaba un hueco de fondo de
           pagina a la vista en vez de verse como dos columnas parejas. */
        .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 0 24px; }
        .form-grid > .panel { height:100%; }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } }
        .form-col-head { font-size:13.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#eee; margin-top:0; padding-bottom:14px; margin-bottom:6px; border-bottom:1px solid #292929; }
        .sub-head { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#777; margin:28px 0 4px; padding-top:18px; border-top:1px solid #232323; }
        .sub-head span { text-transform:none; letter-spacing:normal; font-weight:400; color:#666; }
        .extras-card { background:#161616; border:1px solid #272727; border-radius: 14px; padding: 20px 22px; margin-top:24px; }
        .extras-card .cat-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.04em; margin: 14px 0 8px; }
        .extras-card .cat-label:first-child { margin-top:0; }
        .prod-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-top:1px solid #262626; gap:12px; }
        .prod-row:first-of-type { border-top:none; }
        .prod-row .name { font-size:14px; }
        .prod-row .price { font-size:12px; color:#888; }
        .prod-row input { width:60px; flex:none; background:#111; border:1px solid #333; color:#fff; padding:7px; border-radius:6px; text-align:center; font-size:14px; }
        .manual-extras summary { cursor:pointer; list-style:none; font-size:13.5px; color:#999; padding:4px; }
        .manual-extras summary::-webkit-details-marker { display:none; }
        .manual-extras summary::before { content:'+ '; color:#ff7918; font-weight:700; }
        .manual-extras[open] summary::before { content:'− '; }
        .manual-extras summary:hover { color:#eee; }
        .manual-extras .cat-label:first-of-type { margin-top:14px; }
        /* Distribución en pantalla ancha: estadía | cliente | resumen, sin scroll en un notebook */
        .page-inner { max-width: 1760px; padding-bottom: 28px; }
        .page-head { margin-bottom:16px; padding-bottom:12px; }
        .form-grid { grid-template-columns: minmax(0,1.45fr) minmax(0,1.05fr) minmax(300px,.8fr); gap: 0 16px; align-items:start; }
        .form-grid > .panel { height:auto; padding:16px 20px 18px; }
        .form-col-head { padding-bottom:10px; margin-bottom:2px; }
        .panel label { margin:12px 0 6px; }
        .panel input, .panel select { padding:9px 12px; font-size:14.5px; }
        .panel .hint { margin-top:4px; }
        .panel-summary { position:sticky; top:12px; }
        .panel-summary .price-box { margin:6px 0 4px; }
        .row-id { gap:12px; flex-wrap:wrap; }
        .row-id > div { min-width:150px; }
        .guest-details { margin-top:14px; }
        .guest-details summary { cursor:pointer; list-style:none; font-size:12.5px; font-weight:600; text-transform:uppercase; letter-spacing:.045em; color:#999; padding:4px 0; }
        .guest-details summary::-webkit-details-marker { display:none; }
        .guest-details summary::before { content:'+ '; color:#ff7918; font-weight:700; }
        .guest-details[open] summary::before { content:'− '; }
        .panel-summary .extras-card { background:transparent; border:none; padding:0; margin-top:12px; }
        .panel-summary button.submit { margin-top:16px; }
        /* Guía de pasos: se enciende el panel donde toca trabajar */
        .step-panel { transition: border-color .25s, box-shadow .25s, opacity .25s; }
        .form-col-head { display:flex; align-items:center; }
        .step-num { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; margin-right:9px; border-radius:50%; background:#2f2f2f; color:#aaa; font-size:12px; font-weight:800; letter-spacing:0; }
        .step-tag { margin-left:auto; font-size:10.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:transparent; }
        .step-panel.step-active { border-color:#ff7918; box-shadow:0 0 0 1px #ff7918, 0 0 30px rgba(255,121,24,.16); }
        .step-panel.step-active .step-num { background:#ff7918; color:#111; }
        .step-panel.step-active .step-tag { color:#ff7918; }
        .step-panel.step-pending { opacity:.7; }
        .step-panel.step-done { border-color:#2e6e45; }
        .step-panel.step-done .step-num { background:#2e6e45; color:#fff; font-size:0; }
        .step-panel.step-done .step-num::after { content:'✓'; font-size:13px; }
        .step-panel.step-done .step-tag { color:#6fd39a; }
        .panel-summary.step-active button.submit { animation: stepPulse 1.8s ease-in-out infinite; }
        @keyframes stepPulse { 0%,100% { box-shadow:0 0 0 0 rgba(255,121,24,.5); } 50% { box-shadow:0 0 0 8px rgba(255,121,24,0); } }
        @media (prefers-reduced-motion: reduce) { .panel-summary.step-active button.submit { animation:none; } }
        .walkin-now { margin-top:14px; padding:10px 14px; border:1px solid #6b3a14; background:#2a1a0c; color:#ffb379; border-radius:9px; font-size:14px; }
        .walkin-now b { color:#ff7918; }
        /* Ingreso presencial: estadía | cliente + acompañantes | ofrecer consumo | resumen */
        .consumo-panel .cat-label { font-size:10.5px; color:#888; text-transform:uppercase; letter-spacing:.06em; margin:10px 0 4px; }
        .consumo-total { font-size:12.5px; font-family:ui-monospace,monospace; color:#888; margin:2px 0 6px; }
        .consumo-total.has { color:#8fe0ad; font-weight:700; }
        .consumo-panel .combo-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:8px; margin-bottom:8px; }
        .consumo-panel .combo-card { display:flex; flex-direction:column; align-items:center; text-align:center; gap:3px; padding:10px 8px; background:#202020; border:1px solid #333; border-radius:11px; }
        .consumo-panel .combo-card.out { opacity:.5; }
        .consumo-panel .combo-name { font-size:13.5px; font-weight:700; color:#fff; line-height:1.2; }
        .consumo-panel .combo-items { font-size:11px; color:#9a9a9a; line-height:1.3; min-height:28px; }
        .consumo-panel .combo-price { font-size:16px; font-weight:800; color:#ff7918; margin:1px 0 3px; }
        .consumo-panel .prod-cols { column-count:2; column-gap:22px; }
        .consumo-panel .cat-block { break-inside:avoid; margin-bottom:4px; }
        .consumo-panel .prod-row { padding:3px 0; gap:8px; border-top:none; }
        .consumo-panel .prod-row .name { font-size:13px; line-height:1.25; color:#f1f1f1; font-weight:600; }
        .consumo-panel .prod-row .price { font-size:11px; color:#8d8f94; margin-top:1px; }
        .qty { display:flex; align-items:center; gap:2px; flex:none; }
        .consumo-panel .qty button { width:28px; height:28px; margin:0; padding:0; border-radius:7px; background:#262626; border:1px solid #444; color:#ddd; font-size:17px; line-height:1; font-weight:600; min-height:0; cursor:pointer; }
        .consumo-panel .qty button:hover:not(:disabled) { border-color:#ff7918; color:#ff7918; }
        .consumo-panel .qty input[type=number] { width:30px !important; height:28px !important; min-height:0 !important; margin:0 !important; padding:0 !important; text-align:center; background:transparent !important; border:none !important; border-radius:0 !important; box-shadow:none !important; color:#9a9a9a !important; font-size:15px; line-height:28px; font-weight:700; -moz-appearance:textfield; appearance:textfield; }
        .consumo-panel .qty input::-webkit-inner-spin-button, .consumo-panel .qty input::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
        .consumo-panel .qty.has input[type=number] { color:#ff7918 !important; }
        .offer-actions { margin-top:14px; display:flex; gap:10px; }
        .offer-actions button { flex:1; margin:0; padding:11px 14px; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; }
        .offer-none { background:#262626; border:1px solid #4a4a4a; color:#ddd; }
        .offer-none:hover { border-color:#ff7918; color:#fff; }
        .offer-ok { background:#ff7918; border:1px solid #ff7918; color:#111; }
        .step-panel.step-done .offer-actions { display:none; }
        #submit-btn:disabled { opacity:.4; cursor:not-allowed; animation:none; }
        .offer-needed { margin-top:8px; font-size:12px; color:#e8c76f; text-align:center; }
        .consumo-sum { margin:0 0 4px; padding:8px 14px; border-radius:8px; background:#1c1c1c; border:1px solid #333; font-size:13.5px; }
        .consumo-sum .p-sum { display:flex; justify-content:space-between; padding:2px 0; color:#bbb; }
        .consumo-sum .p-sum.total { border-top:1px solid #333; margin-top:4px; padding-top:6px; color:#eee; font-weight:700; }
        .consumo-sum .p-sum.total span:last-child { font-family:ui-monospace,monospace; font-size:18px; }
        @media (min-width: 1500px) { .form-grid.walkin-grid { grid-template-columns: minmax(0,.95fr) minmax(0,1.15fr) minmax(0,1.45fr) minmax(235px,.65fr); } .form-grid.walkin-grid .panel .row > div { flex:1 1 130px; min-width:0; } .form-grid.walkin-grid .panel .row-id > div { min-width:0; } }
        .form-grid.walkin-grid .panel input:not([type=checkbox]), .form-grid.walkin-grid .panel select { padding:7px 11px; font-size:14px; }
        .form-grid.walkin-grid .panel label { margin:9px 0 4px; }
        .form-grid.walkin-grid .sub-head { margin:12px 0 2px; padding-top:10px; }
        .form-grid.walkin-grid .sub-head span { display:none; }
        .form-grid.walkin-grid .panel .hint { font-size:11.5px; margin-top:3px; }
        .form-grid.walkin-grid .guest-row input { padding:7px 11px; }
        @media (max-width: 1499px) and (min-width: 701px) { .form-grid.walkin-grid { grid-template-columns: 1fr 1fr; gap:16px; } .form-grid.walkin-grid .panel-summary { grid-column:auto; position:static; margin-top:0; } }
        @media (max-width: 700px) { .consumo-panel .prod-cols { column-count:1; } }
        @media (max-width: 1280px) { .form-grid { grid-template-columns: 1fr 1fr; } .panel-summary { grid-column: 1 / -1; position:static; margin-top:16px; } }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <div class="page-head">
        @if ($walkInMode)
            <h1>Ingreso presencial</h1>
            <p class="sub">El cliente está en recepción: se registra, se cobra y se hace el check-in · <a href="{{ route('reservations.create') }}" style="color:#ff7918;">¿Es para más tarde? Hacer una reserva</a></p>
        @else
            <h1>Nueva reserva</h1>
            <p class="sub">Para una hora más adelante · <a href="{{ route('reservations.create', ['modo' => 'ingreso']) }}" style="color:#ff7918;">¿El cliente está aquí? Ingreso presencial</a></p>
        @endif
    </div>

    @if ($errors->any())
        <div class="errors">
            @foreach (array_unique($errors->all()) as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('reservations.store') }}">
        @csrf

        <input type="hidden" name="walk_in" id="walk-in-flag" value="{{ $walkInMode ? 1 : 0 }}">
        <div class="form-grid{{ $walkInMode ? ' walkin-grid' : '' }}">
            <div class="panel step-panel" data-step="1">
                <div class="form-col-head"><span class="step-num">1</span>La estadía<span class="step-tag"></span></div>

                <label>Habitación</label>
                @php $roomCats = $rooms->pluck('category')->unique('id')->sortBy('display_order')->values(); @endphp
                @if ($roomCats->count() > 1)
                    <div class="cat-quick" id="room-cat-quick">
                        <button type="button" data-cat="todas" onclick="hhFilterRooms('todas')">Todas</button>
                        @foreach ($roomCats as $cat)
                            <button type="button" data-cat="{{ Str::slug($cat->name) }}" onclick="hhFilterRooms('{{ Str::slug($cat->name) }}')">{{ $cat->name === 'NEW LITE' ? '✨ '.$cat->name : $cat->name }}</button>
                        @endforeach
                    </div>
                @endif
                <select name="room_id" id="room-select" required>
                    <option value="" @selected(! $selectedRoomId) disabled>— Elige una habitación —</option>
                    @foreach ($rooms as $room)
                        @php
                            $occ = $room->current_status['occupancy'];
                            $etaMin = $room->current_status['eta_minutes'];
                            $statusLabel = match(true) {
                                $occ === 'ocupada' && $etaMin >= 0 => 'Ocupada, libera en '.$etaMin.' min',
                                $occ === 'ocupada' => 'Ocupada (pasó su horario)',
                                default => 'Libre ahora',
                            };
                        @endphp
                        <option value="{{ $room->id }}" data-cat="{{ Str::slug($room->category->name) }}" @selected($selectedRoomId == $room->id)>
                            {{ $room->name }} — {{ $room->category->name }} · {{ $statusLabel }}
                        </option>
                    @endforeach
                </select>
                <div class="hint">El estado es el de <strong>ahora mismo</strong> — si reservas para más adelante, puede estar libre igual aunque diga ocupada.</div>

                <div class="row row-datetime"@if ($walkInMode) style="display:none"@endif>
                    <div>
                        <label>Fecha <span id="date-wheel-month" class="date-month-badge"></span></label>
                        <div class="wheel wheel-date" id="date-wheel">
                            <button type="button" class="wheel-arrow" data-dir="-1">‹</button>
                            <div class="wheel-track" id="date-wheel-track"></div>
                            <button type="button" class="wheel-arrow" data-dir="1">›</button>
                        </div>
                        @php
                            $oldDate = old('date', request()->query('date', now()->toDateString()));
                            // Offset en días contra hoy — si el formulario vuelve por un
                            // error de validación, la rueda tiene que arrancar centrada en
                            // lo que la recepción ya había elegido, no resetear a "hoy".
                            $initialDateOffset = (int) now('America/Santiago')->startOfDay()
                                ->diffInDays(\Carbon\Carbon::parse($oldDate, 'America/Santiago')->startOfDay(), false);
                        @endphp
                        <input type="hidden" name="date" id="date-input" value="{{ $oldDate }}">
                    </div>
                    <div>
                        <label>Hora <span class="hint" style="margin:0;">— gira la rueda del mouse sobre el número</span></label>
                        @php
                            $oldHour = old('time_hour', now()->timezone('America/Santiago')->format('G'));
                            $oldMinute = old('time_minute', (int) floor(now()->timezone('America/Santiago')->minute / 5) * 5);
                        @endphp
                        <div class="time-row">
                            <div class="wheel wheel-sm" id="hour-wheel">
                                <button type="button" class="wheel-arrow" data-dir="-1">‹</button>
                                <div class="wheel-track" id="hour-wheel-track"></div>
                                <button type="button" class="wheel-arrow" data-dir="1">›</button>
                            </div>
                            <div class="wheel wheel-sm" id="minute-wheel">
                                <button type="button" class="wheel-arrow" data-dir="-1">‹</button>
                                <div class="wheel-track" id="minute-wheel-track"></div>
                                <button type="button" class="wheel-arrow" data-dir="1">›</button>
                            </div>
                            <input type="hidden" name="time_hour" id="time-hour" value="{{ $oldHour }}">
                            <input type="hidden" name="time_minute" id="time-minute" value="{{ $oldMinute }}">
                        </div>
                        <div class="hint">Lun a jue 10:30 a 03:00 · vie 10:30 a dom 22:30 corrido. Fuera de eso, el sistema rechaza la reserva.</div>
                        <div class="time-warning" id="time-warning">⚠ Ese horario (u hora + duración) no corresponde a ninguna tarifa vigente — no se va a poder crear la reserva.</div>
                    </div>
                </div>

                @if ($walkInMode)
                    <div class="walkin-now">Ingresa <b>ahora</b> · <span id="walkin-clock"></span></div>
                @endif

                <label>Duración <span class="hint" style="margin:0;">— según la categoría</span></label>
                @php
                    $selDur = (int) old('duration_minutes', 60);
                    $durLabels = [60 => '1 h', 120 => '2 h', 180 => '3 h', 360 => '6 h', 720 => '12 h'];
                    $allDurs = collect($categoryDurations)->flatten()->map(fn ($m) => (int) $m)->unique()->sort()->values();
                @endphp
                <input type="hidden" name="duration_minutes" id="duration-select" value="{{ $selDur }}" required>
                <div class="dur-quick" id="dur-quick">
                    @foreach ($allDurs as $mins)
                        <button type="button" data-mins="{{ $mins }}" class="dur-{{ $mins }} {{ $selDur === $mins ? 'active' : '' }}" onclick="hhSetDuration({{ $mins }})">{{ $durLabels[$mins] ?? ($mins / 60).' h' }}</button>
                    @endforeach
                </div>

                <div class="row">
                    <div>
                        <label>Personas</label>
                        <input type="number" name="guests_count" min="1" max="10" value="{{ old('guests_count', 2) }}" required>
                    </div>
                    <div id="deposit-wrap" hidden>{{-- el abono ya no se pide: el pago se registra en el paso siguiente --}}
                        <label>Abono <span class="hint" style="margin:0;">— si el cliente paga solo una parte ahora, opcional</span></label>
                        <input type="number" name="deposit_amount" id="deposit-amount" min="0" step="1" value="" placeholder="Ej. 10000" disabled>
                    </div>
                </div>
            </div>

            <div class="panel step-panel" data-step="2">
                <div class="form-col-head"><span class="step-num">2</span>El cliente<span class="step-tag"></span></div>

                <label>Teléfono / WhatsApp</label>
                <input type="text" name="customer_phone" id="customer-phone" placeholder="Pega el número del cliente — +56 9 ..." value="{{ old('customer_phone') }}" autocomplete="off" required>
                <div class="cust-badge" id="cust-badge" hidden></div>
                <div class="hint">Es la identidad del cliente. Si ya reservó antes, se completan solos el nombre y el email.</div>

                <div class="cust-ficha" id="cust-ficha" hidden>
                    <div class="ficha-row"><span class="ficha-label">Nombre</span><span class="ficha-value" id="ficha-name"></span></div>
                    <div class="ficha-row"><span class="ficha-label">Email</span><span class="ficha-value" id="ficha-email"></span></div>
                    <div id="ficha-extra"></div>
                    <button type="button" class="ficha-edit-btn" id="ficha-edit-btn">✎ Editar datos</button>
                </div>

                <div id="cust-editable-fields">
                    <div class="row">
                        <div>
                            <label>Nombre</label>
                            <input type="text" name="customer_first_name" id="customer-first-name" value="{{ old('customer_first_name') }}" required>
                        </div>
                        <div>
                            <label>Apellido</label>
                            <input type="text" name="customer_last_name" id="customer-last-name" value="{{ old('customer_last_name') }}" required>
                        </div>
                    </div>

                    <label>Email</label>
                    <input type="email" name="customer_email" id="customer-email" value="{{ old('customer_email') }}" required>

                    <div class="sub-head">Identificación <span>— el documento es obligatorio; nacionalidad y fecha de nacimiento no, salvo para cupones que la exigen</span></div>

                    <div class="row row-id">
                        <div>
                    <label>Nacionalidad</label>
                    <select id="nationality-select" onchange="hhSyncNationality()">
                        <option value="Chilena">Chilena</option>
                        <option value="Argentina">Argentina</option>
                        <option value="Peruana">Peruana</option>
                        <option value="Venezolana">Venezolana</option>
                        <option value="Colombiana">Colombiana</option>
                        <option value="Boliviana">Boliviana</option>
                        <option value="Ecuatoriana">Ecuatoriana</option>
                        <option value="Brasileña">Brasileña</option>
                        <option value="Paraguaya">Paraguaya</option>
                        <option value="Uruguaya">Uruguaya</option>
                        <option value="">Otra…</option>
                    </select>
                    <input type="text" id="nationality-other" placeholder="Escribe la nacionalidad" hidden style="margin-top:8px;">
                    <input type="hidden" name="nationality" id="customer-nationality" value="{{ old('nationality', 'Chilena') }}">

                        </div>
                        <div>
                    <label>Fecha de nacimiento</label>
                    <div class="dob-row">
                        <select id="birth-day" onchange="hhSyncBirthDate()">
                            <option value="">Día</option>
                            @for ($d = 1; $d <= 31; $d++)
                                <option value="{{ str_pad($d, 2, '0', STR_PAD_LEFT) }}">{{ $d }}</option>
                            @endfor
                        </select>
                        <select id="birth-month" onchange="hhSyncBirthDate()">
                            <option value="">Mes</option>
                            @foreach (['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'] as $mVal => $mLabel)
                                <option value="{{ $mVal }}">{{ $mLabel }}</option>
                            @endforeach
                        </select>
                        <select id="birth-year" onchange="hhSyncBirthDate()">
                            <option value="">Año</option>
                            @for ($y = now()->year - 18; $y >= now()->year - 100; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <input type="hidden" name="birth_date" id="customer-birth-date" value="{{ old('birth_date') }}">
                        </div>
                    </div>
                </div>

                <div id="cust-document-fields">
                    <div class="doc-missing-warn" id="doc-missing-warn" hidden>⚠ A este cliente le falta el RUT o pasaporte — hay que cargarlo.</div>
                    <label>Documento de identidad</label>
                    <div class="doc-row">
                        <select name="document_type" id="document-type" required>
                            <option value="" @selected(old('document_type', '') === '')>— Tipo —</option>
                            <option value="rut" @selected(old('document_type') === 'rut')>RUT</option>
                            <option value="pasaporte" @selected(old('document_type') === 'pasaporte')>Pasaporte</option>
                        </select>
                        <input type="text" name="document_number" id="document-number" placeholder="Elige el tipo primero" value="{{ old('document_number') }}" disabled required>
                    </div>
                    <div class="hint" id="document-hint">&nbsp;</div>
                </div>

                @if ($walkInMode)
                    <div class="sub-head">Acompañantes <span>— nombre y apellido de quienes entran con el cliente</span></div>
                    <div id="guest-rows"></div>
                    <button type="button" class="guest-add-btn" onclick="hhAddGuestRow()">+ Agregar acompañante</button>
                    <textarea name="guest_names" id="guest-names-hidden" hidden>{{ old('guest_names') }}</textarea>
                @endif
            </div>

            @if ($walkInMode)
                <div class="panel step-panel consumo-panel" data-step="3">
                    <div class="form-col-head"><span class="step-num">3</span>Ofrecer productos y combos<span class="step-tag"></span></div>
                    <div class="consumo-total" id="consumo-total">Sin consumo todavía</div>

                    @if ($combos->isNotEmpty())
                        <div class="cat-label">Combos</div>
                        <div class="combo-cards">
                            @foreach ($combos as $combo)
                                <div class="combo-card {{ $combo->isOutOfStock() ? 'out' : '' }}">
                                    <div class="combo-name">{{ $combo->name }}</div>
                                    <div class="combo-items">{{ $combo->description ?: $combo->items->map(fn ($i) => $i->quantity.' × '.($i->product->name ?? ''))->join(' · ') }}</div>
                                    <div class="combo-price">${{ number_format($combo->price, 0, ',', '.') }}{!! $combo->isOutOfStock() ? ' <small>agotado</small>' : '' !!}</div>
                                    <div class="qty">
                                        <button type="button" class="q-minus" aria-label="Menos" @disabled($combo->isOutOfStock())>−</button>
                                        <input type="number" name="combo_quantities[{{ $combo->id }}]" value="{{ old('combo_quantities.'.$combo->id, 0) }}" min="0" max="{{ $combo->availableCount() !== null ? min(20, $combo->availableCount()) : 20 }}" data-price="{{ $combo->price }}" @disabled($combo->isOutOfStock())>
                                        <button type="button" class="q-plus" aria-label="Más" @disabled($combo->isOutOfStock())>+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="prod-cols">
                        @foreach ($products->groupBy('category') as $category => $items)
                            <div class="cat-block">
                                <div class="cat-label">{{ $category ?? 'Otros' }}</div>
                                @foreach ($items as $product)
                                    <div class="prod-row">
                                        <div>
                                            <div class="name">{{ $product->name }}</div>
                                            <div class="price">
                                                ${{ number_format($product->price, 0, ',', '.') }}
                                                @if ($product->track_inventory)
                                                    · {{ $product->isOutOfStock() ? 'agotado' : $product->stock.' disp.' }}
                                                @endif
                                            </div>
                                        </div>
                                        <div class="qty">
                                            <button type="button" class="q-minus" aria-label="Menos" @disabled($product->isOutOfStock())>−</button>
                                            <input type="number" name="quantities[{{ $product->id }}]" value="{{ old('quantities.'.$product->id, 0) }}" min="0" max="{{ $product->track_inventory ? min(20, $product->stock) : 20 }}" data-price="{{ $product->price }}" @disabled($product->isOutOfStock())>
                                            <button type="button" class="q-plus" aria-label="Más" @disabled($product->isOutOfStock())>+</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="offer-actions">
                        <button type="button" class="offer-none" id="offer-none">El cliente no quiere nada · continuar →</button>
                        <button type="button" class="offer-ok" id="offer-ok" hidden>Continuar con el consumo →</button>
                    </div>
                </div>
            @endif

            <div class="panel panel-summary step-panel" data-step="{{ $walkInMode ? 4 : 3 }}">
                <div class="form-col-head"><span class="step-num">{{ $walkInMode ? 4 : 3 }}</span>Resumen<span class="step-tag"></span></div>

                <div class="price-box" id="price-box" hidden></div>
                @if ($walkInMode)
                    <div class="consumo-sum" id="consumo-sum" hidden></div>
                @endif

                <label>Código promocional (opcional)</label>
                <select name="coupon_code" id="coupon-select" onchange="hhQuotePrice()">
                    <option value="" @selected(old('coupon_code', '') === '')>Sin cupón</option>
                    @foreach ($coupons as $coupon)
                        @php
                            $discountLabel = $coupon->discount_type === 'percentage'
                                ? '-'.$coupon->discount_value.'%'
                                : '-$'.number_format($coupon->discount_value, 0, ',', '.');
                        @endphp
                        <option value="{{ $coupon->code }}" @selected(old('coupon_code') === $coupon->code)>
                            {{ $coupon->code }} — {{ $coupon->internal_name }} ({{ $discountLabel }}){{ $coupon->min_age ? ' · requiere '.$coupon->min_age.'+ años' : ($coupon->requires_verification ? ' · pide verificación' : '') }}
                        </option>
                    @endforeach
                </select>
                <div class="hint">Si exige cédula o credencial, verifícala antes de aplicarlo. Los que piden edad mínima no quedan activos si falta la fecha de nacimiento del cliente.</div>

                @unless ($walkInMode)
                <div hidden>{{-- acompañantes y consumo se piden en el check-in --}}
                <details class="guest-details"{{ old('guest_names') ? ' open' : '' }}>
                    <summary>Acompañantes (opcional)</summary>
                <label>Acompañantes (opcional) <span class="hint" style="margin:0;">— registro obligatorio para mayores de 18 años</span></label>
                <div id="guest-rows"></div>
                <button type="button" class="guest-add-btn" onclick="hhAddGuestRow()">+ Agregar acompañante</button>
                <textarea name="guest_names" id="guest-names-hidden" hidden>{{ old('guest_names') }}</textarea>

                </details>


        @if (! $walkInMode && ($combos->isNotEmpty() || $products->isNotEmpty()))
            <div class="extras-card">
                <details class="manual-extras">
                    <summary>Agregar consumo manualmente (combos, bebidas, etc.)</summary>

                    @if ($combos->isNotEmpty())
                        <div class="cat-label">Combos</div>
                        @foreach ($combos as $combo)
                            <div class="prod-row">
                                <div>
                                    <div class="name">{{ $combo->name }}</div>
                                    <div class="price">
                                        ${{ number_format($combo->price, 0, ',', '.') }}
                                        @if ($combo->description)
                                            · {{ $combo->description }}
                                        @endif
                                        {{ $combo->isOutOfStock() ? '· agotado' : '' }}
                                    </div>
                                </div>
                                <input type="number" name="combo_quantities[{{ $combo->id }}]" value="{{ old('combo_quantities.'.$combo->id, 0) }}" min="0" max="{{ $combo->availableCount() !== null ? min(20, $combo->availableCount()) : 20 }}" @disabled($combo->isOutOfStock())>
                            </div>
                        @endforeach
                    @endif

                    @if ($products->isNotEmpty())
                        @foreach ($products->groupBy('category') as $category => $items)
                            <div class="cat-label">{{ $category ?? 'Otros' }}</div>
                            @foreach ($items as $product)
                                <div class="prod-row">
                                    <div>
                                        <div class="name">{{ $product->name }}</div>
                                        <div class="price">
                                            ${{ number_format($product->price, 0, ',', '.') }}
                                            @if ($product->track_inventory)
                                                · {{ $product->isOutOfStock() ? 'agotado' : $product->stock.' disp.' }}
                                            @endif
                                        </div>
                                    </div>
                                    <input type="number" name="quantities[{{ $product->id }}]" value="{{ old('quantities.'.$product->id, 0) }}" min="0" max="{{ $product->track_inventory ? min(20, $product->stock) : 20 }}" @disabled($product->isOutOfStock())>
                                </div>
                            @endforeach
                        @endforeach
                    @endif
                </details>
            </div>
        @endif
                </div>
                @endunless


                <button class="submit" id="submit-btn" type="submit">Crear reserva</button>
                @if ($walkInMode)
                    <div class="offer-needed" id="offer-needed" hidden>Antes de cobrar, ofrece productos y combos (paso 3).</div>
                @endif
            </div>
        </div>

    </form>
    </div>

    <div class="upsell-overlay" id="upsell-overlay" hidden>
        <div class="upsell-modal">
            <div class="upsell-head">
                <div class="upsell-title">💡 ¿Le ofreciste algo al cliente?</div>
                <button type="button" class="upsell-x" onclick="hhCloseUpsell()">✕</button>
            </div>
            <p class="upsell-sub">Ofrece siempre — subir de categoría, más tiempo o un pack. Es venta fácil.</p>
            <div class="upsell-list" id="upsell-list"></div>
            <button type="button" class="upsell-add" onclick="hhConfirmUpsell(true)">Agregar lo elegido y crear reserva</button>
            <button type="button" class="upsell-skip" onclick="hhConfirmUpsell(false)">El cliente no quiere nada — crear igual</button>
        </div>
    </div>
    @php
        $fallbackCombos = $combos->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'price' => $c->price, 'detail' => $c->description])->values();
    @endphp
    <script>
        // Combos activos como fallback si todavía no hay upsells configurados.
        const HH_FALLBACK_COMBOS = {{ Illuminate\Support\Js::from($fallbackCombos) }};
        let hhLatestUpsells = [];

        // Selector "rueda": el valor del centro es el elegido, se ve
        // destacado, y se puede girar con la rueda del mouse o con las
        // flechas — igual para fecha, hora y minutos, en vez de un
        // <select> nativo larguísimo o un calendario que ocupa media
        // pantalla.
        const DATE_DOW = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
        const DATE_MONTH = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        function hhIsoDate(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        // El día solo (ej. "21") es ambiguo al cruzar de mes — se muestra el
        // mes/año del día elegido (el del centro de la rueda) aparte, al
        // lado de la etiqueta "Fecha".
        function hhUpdateDateMonthLabel(offset) {
            const el = document.getElementById('date-wheel-month');
            if (!el) return;
            const d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() + offset);
            el.textContent = DATE_MONTH[d.getMonth()] + '. ' + d.getFullYear();
        }
        function hhInitWheel(opts) {
            const track = document.getElementById(opts.trackId);
            const wheel = track.closest('.wheel');
            let current = opts.initial;

            function clamp(v) {
                if (opts.wrap) {
                    const span = opts.max - opts.min + opts.step;
                    let n = v;
                    while (n < opts.min) n += span;
                    while (n > opts.max) n -= span;
                    return n;
                }
                return Math.min(opts.max, Math.max(opts.min, v));
            }

            function render() {
                track.innerHTML = '';
                for (let off = -opts.radius; off <= opts.radius; off++) {
                    const raw = current + off * opts.step;
                    // Sin wrap (la fecha no da vueltas), los vecinos fuera de
                    // rango igual se muestran — para que la rueda no se vea
                    // coja pegada al día de hoy — pero quedan atenuados y sin
                    // click, no se puede "elegir ayer".
                    const outOfBounds = !opts.wrap && (raw < opts.min || raw > opts.max);
                    const val = opts.wrap ? clamp(raw) : Math.min(opts.max, Math.max(opts.min, raw));
                    const item = document.createElement('div');
                    item.className = 'wheel-item' + (off === 0 ? ' center' : '') + (outOfBounds ? ' disabled' : '');
                    item.innerHTML = opts.formatLabel(outOfBounds ? raw : val);
                    if (off !== 0 && !outOfBounds) {
                        item.addEventListener('click', function () { current = val; render(); opts.onChange(current); });
                    }
                    track.appendChild(item);
                }
            }

            function step(dir) {
                current = clamp(current + dir * opts.step);
                render();
                opts.onChange(current);
            }

            wheel.addEventListener('wheel', function (e) {
                e.preventDefault();
                step(e.deltaY > 0 ? 1 : -1);
            }, { passive: false });

            wheel.querySelectorAll('.wheel-arrow').forEach(function (btn) {
                btn.addEventListener('click', function () { step(Number(btn.dataset.dir)); });
            });

            render();
            return { set: function (v) { current = clamp(v); render(); } };
        }

        const dateWheel = hhInitWheel({
            trackId: 'date-wheel-track',
            min: 0,
            max: 3650,
            step: 1,
            radius: 2,
            wrap: false,
            initial: {{ $initialDateOffset }},
            formatLabel: function (offset) {
                const d = new Date();
                d.setHours(0, 0, 0, 0);
                d.setDate(d.getDate() + offset);
                const label = offset === 0 ? 'HOY' : (offset === 1 ? 'MAÑ' : DATE_DOW[d.getDay()]);
                return '<span class="dow">' + label + '</span><span class="dnum">' + d.getDate() + '</span>';
            },
            onChange: function (offset) {
                const d = new Date();
                d.setHours(0, 0, 0, 0);
                d.setDate(d.getDate() + offset);
                document.getElementById('date-input').value = hhIsoDate(d);
                hhUpdateDateMonthLabel(offset);
                hhValidateSchedule();
                hhQuotePrice();
            },
        });
        hhUpdateDateMonthLabel({{ $initialDateOffset }});

        const hourWheel = hhInitWheel({
            trackId: 'hour-wheel-track',
            min: 0,
            max: 23,
            step: 1,
            radius: 1,
            wrap: true,
            initial: Number(document.getElementById('time-hour').value),
            formatLabel: function (v) { return String(v).padStart(2, '0'); },
            onChange: function (v) {
                document.getElementById('time-hour').value = String(v);
                hhValidateSchedule();
                hhQuotePrice();
            },
        });

        const minuteWheel = hhInitWheel({
            trackId: 'minute-wheel-track',
            min: 0,
            max: 55,
            step: 5,
            radius: 1,
            wrap: true,
            initial: Number(document.getElementById('time-minute').value),
            formatLabel: function (v) { return String(v).padStart(2, '0'); },
            onChange: function (v) {
                document.getElementById('time-minute').value = String(v);
                hhValidateSchedule();
                hhQuotePrice();
            },
        });

        // Acompañantes: nombre y apellido en campos separados por persona,
        // en vez de un textarea libre — se combinan acá mismo en el textarea
        // oculto que ya espera el servidor (una línea por acompañante), así
        // no hubo que tocar nada del guardado en el backend.
        //
        // Además queda sincronizado con "Personas" en los dos sentidos:
        // cambiar personas ajusta cuántas filas de acompañante hay (siempre
        // personas - 1, el titular ya cuenta aparte), y agregar/quitar un
        // acompañante ajusta el número de personas para que cuadren.
        function hhSyncGuestNames() {
            const rows = document.querySelectorAll('#guest-rows .guest-row');
            const names = [];
            rows.forEach(function (row) {
                const first = row.querySelector('.guest-first').value.trim();
                const last = row.querySelector('.guest-last').value.trim();
                const full = (first + ' ' + last).trim();
                if (full) names.push(full);
            });
            document.getElementById('guest-names-hidden').value = names.join('\n');
        }
        function hhGuestRowCount() {
            return document.querySelectorAll('#guest-rows .guest-row').length;
        }
        function hhSetPersonas(n) {
            const input = document.querySelector('input[name="guests_count"]');
            const clamped = Math.max(1, Math.min(10, n));
            if (Number(input.value) !== clamped) {
                input.value = clamped;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        function hhAddGuestRowRaw(first, last) {
            const container = document.getElementById('guest-rows');
            const row = document.createElement('div');
            row.className = 'guest-row';
            const firstInput = document.createElement('input');
            firstInput.type = 'text';
            firstInput.placeholder = 'Nombre';
            firstInput.className = 'guest-first';
            firstInput.value = first || '';
            const lastInput = document.createElement('input');
            lastInput.type = 'text';
            lastInput.placeholder = 'Apellido';
            lastInput.className = 'guest-last';
            lastInput.value = last || '';
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'guest-remove';
            removeBtn.textContent = '✕';
            removeBtn.addEventListener('click', function () {
                row.remove();
                hhSyncGuestNames();
                hhSetPersonas(hhGuestRowCount() + 1);
            });
            [firstInput, lastInput].forEach(function (inp) { inp.addEventListener('input', hhSyncGuestNames); });
            row.appendChild(firstInput);
            row.appendChild(lastInput);
            row.appendChild(removeBtn);
            container.appendChild(row);
        }
        // Único punto que decide cuántas filas hay que mostrar — todo lo
        // demás (tocar "Personas", tocar "+ Agregar acompañante") solo
        // cambia el número de personas y deja que esto reaccione.
        function hhSyncGuestRowsToPersonas() {
            const personas = Math.max(1, Number(document.querySelector('input[name="guests_count"]').value) || 1);
            const needed = Math.max(0, personas - 1);
            while (hhGuestRowCount() < needed) hhAddGuestRowRaw();
            while (hhGuestRowCount() > needed) {
                const rows = document.querySelectorAll('#guest-rows .guest-row');
                rows[rows.length - 1].remove();
            }
            hhSyncGuestNames();
        }
        function hhAddGuestRow() {
            hhSetPersonas(hhGuestRowCount() + 2);
        }
        (function () {
            const existing = document.getElementById('guest-names-hidden').value.split(/\r\n|\r|\n/).map(function (s) { return s.trim(); }).filter(Boolean);
            existing.forEach(function (name) {
                const parts = name.split(' ');
                hhAddGuestRowRaw(parts[0], parts.slice(1).join(' '));
            });
            document.querySelector('input[name="guests_count"]').addEventListener('input', hhSyncGuestRowsToPersonas);
            hhSyncGuestRowsToPersonas();
        })();
        function hhSetDuration(mins) {
            document.getElementById('duration-select').value = mins;
            document.querySelectorAll('#dur-quick button').forEach(b => b.classList.toggle('active', Number(b.dataset.mins) === mins));
            hhValidateSchedule();
            hhQuotePrice();
        }

        // Precio en vivo + oferta aplicada (sin código).
        let hhQuoteTimer = null;
        function hhQuotePrice() {
            clearTimeout(hhQuoteTimer);
            hhQuoteTimer = setTimeout(hhQuotePriceNow, 350);
        }
        async function hhQuotePriceNow() {
            const box = document.getElementById('price-box');
            const room = document.getElementById('room-select').value;
            const date = document.getElementById('date-input').value;
            const h = document.getElementById('time-hour').value;
            const m = document.getElementById('time-minute').value;
            const dur = document.getElementById('duration-select').value;
            box.hidden = false;
            if (!room || !date || h === '' || m === '' || !dur) {
                box.className = 'price-box';
                box.innerHTML = '<div class="p-wait">Elige habitación, fecha, hora y duración para ver el precio.</div>';
                return;
            }
            const params = new URLSearchParams({ room_id: room, date: date, time_hour: h, time_minute: m, duration_minutes: dur });
            const guests = document.querySelector('input[name="guests_count"]');
            if (guests && guests.value) params.set('guests_count', guests.value);
            const codeEl = document.getElementById('coupon-select');
            if (codeEl && codeEl.value) params.set('coupon_code', codeEl.value);
            const birthEl = document.getElementById('customer-birth-date');
            if (birthEl && birthEl.value) params.set('birth_date', birthEl.value);
            try {
                const r = await fetch('{{ route('reservations.price') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (!r.ok) { box.className = 'price-box'; box.innerHTML = '<div class="p-wait">—</div>'; return; }
                const d = await r.json();
                box.hidden = false;
                if (d.error) {
                    box.className = 'price-box';
                    box.innerHTML = '<div class="p-err">' + d.error + '</div>';
                    return;
                }
                const fmt = n => '$' + Number(n).toLocaleString('es-CL');
                const applied = d.applied;
                const finalPrice = applied ? applied.price_final : d.price_original;
                const hasDiscount = !!applied;

                let rows = '<div class="p-sum"><span>Precio habitación</span><span>' + fmt(d.price_original) + '</span></div>';
                if (applied) {
                    const tag = applied.kind === 'offer' ? 'Oferta: ' + applied.label : 'Código: ' + applied.label;
                    rows += '<div class="p-sum disc"><span>' + tag + '</span><span>−' + fmt(applied.discount).slice(1) + '</span></div>';
                }
                rows += '<div class="p-sum total"><span>Total a pagar</span><span class="' + (hasDiscount ? 'offer' : '') + '">' + fmt(finalPrice) + '</span></div>';
                let note = '';
                if (d.code_blocked) {
                    note = '<div class="p-note">El código no aplica — esta habitación ya está en oferta.</div>';
                } else if (d.code_error) {
                    note = '<div class="p-note err">Código: ' + d.code_error + '</div>';
                }

                box.className = 'price-box' + (hasDiscount ? ' has-offer' : '');
                box.innerHTML = rows + note;
                box.dataset.final = finalPrice;
                if (window.hhRefreshConsumo) window.hhRefreshConsumo();
                hhLatestUpsells = Array.isArray(d.upsells) ? d.upsells : [];
            } catch (e) { box.className = 'price-box'; box.innerHTML = '<div class="p-wait">—</div>'; }
        }

        // Muestra solo las duraciones que tienen precio en la categoría de la
        // habitación elegida (GO 1-2 h, LITE 3 h, PLUS/MAX 3-6-12 h).
        const HH_CAT_DURATIONS = @json($categoryDurations);
        function hhSyncDurations(catOverride) {
            const roomSel = document.getElementById('room-select');
            const opt = roomSel.options[roomSel.selectedIndex];
            const cat = catOverride !== undefined ? catOverride : (opt ? opt.dataset.cat : null);
            const allowed = (cat && HH_CAT_DURATIONS[cat]) ? HH_CAT_DURATIONS[cat] : [];
            const btns = Array.from(document.querySelectorAll('#dur-quick button'));
            btns.forEach(b => { b.hidden = allowed.length > 0 && !allowed.includes(Number(b.dataset.mins)); });

            const current = Number(document.getElementById('duration-select').value);
            if (allowed.length > 0 && !allowed.includes(current)) {
                hhSetDuration(allowed[0]);
            } else {
                hhQuotePrice();
            }
        }

        // Ventanas horarias reales de las tarifas activas — mismo dato que usa
        // el servidor, para avisar (y bloquear el envío) ANTES de mandar el
        // formulario, no solo después con el rechazo del backend.
        const HH_WINDOWS = @json($windows);

        function hhTimeToMinutes(hhmm) {
            const parts = hhmm.split(':');
            return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        }

        // Misma ventana que cubre un instante dado — espejo de
        // RateRuleResolver::findWindowCovering() en el backend.
        function hhFindWindowCovering(point) {
            const weekday = point.getDay();
            const prevWeekday = (weekday + 6) % 7;
            const t = point.getHours() * 60 + point.getMinutes();

            for (const w of HH_WINDOWS) {
                const start = hhTimeToMinutes(w.start);
                const end = hhTimeToMinutes(w.end);
                if (w.weekday === weekday) {
                    if (w.wraps ? t >= start : (t >= start && t < end)) return w;
                }
                if (w.weekday === prevWeekday && w.wraps && t < end) return w;
            }
            return null;
        }

        // Hasta dónde llega la cobertura de esa ventana desde el punto actual —
        // espejo de RateRuleResolver::windowCoverageEnd().
        function hhWindowCoverageEnd(w, cursor) {
            const sameDay = w.weekday === cursor.getDay();
            const end = new Date(cursor.getTime());
            const endMinutes = hhTimeToMinutes(w.end);
            if (sameDay && w.wraps) {
                end.setDate(end.getDate() + 1);
            }
            end.setHours(Math.floor(endMinutes / 60), endMinutes % 60, 0, 0);
            return end;
        }

        // Camina el rango completo (inicio → inicio+duración) ventana por
        // ventana, igual que RateRuleResolver::rangeFullyCoveredByRule() —
        // así una hora de inicio válida pero con una duración que se pasa del
        // cierre también queda bloqueada, no solo el punto de partida.
        function hhRangeFullyCovered(startDate, endDate) {
            let cursor = new Date(startDate.getTime());
            let guard = 0;
            while (cursor.getTime() < endDate.getTime() && guard < 10) {
                guard++;
                const w = hhFindWindowCovering(cursor);
                if (!w) return false;
                cursor = hhWindowCoverageEnd(w, cursor);
            }
            return cursor.getTime() >= endDate.getTime();
        }

        function hhValidateSchedule() {
            const dateVal = document.getElementById('date-input').value;
            const hourEl = document.getElementById('time-hour');
            const minuteEl = document.getElementById('time-minute');
            const durationEl = document.getElementById('duration-select');
            const warningEl = document.getElementById('time-warning');
            const submitBtn = document.getElementById('submit-btn');
            if (!dateVal || !hourEl.value || minuteEl.value === '' || !durationEl.value) return;

            const [yyyy, mm, dd] = dateVal.split('-').map(Number);
            const startDate = new Date(yyyy, mm - 1, dd, parseInt(hourEl.value, 10), parseInt(minuteEl.value, 10), 0, 0);
            const endDate = new Date(startDate.getTime() + parseInt(durationEl.value, 10) * 60000);

            const covered = hhRangeFullyCovered(startDate, endDate);

            warningEl.style.display = covered ? 'none' : 'block';
            submitBtn.disabled = !covered;
        }

        hhValidateSchedule();

        ['date-input', 'time-hour', 'time-minute'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', hhQuotePrice);
        });
        document.querySelectorAll('input[name="guests_count"]').forEach(el => {
            el.addEventListener('input', hhQuotePrice);
        });
        hhQuotePrice();

        // Filtro rápido de habitación por categoría — reconstruye el <select>
        // con solo las opciones de la categoría elegida, así no hay que buscar
        // en un listado de 25+. Se recuerda la última elección.
        (function () {
            const sel = document.getElementById('room-select');
            const bar = document.getElementById('room-cat-quick');
            if (!sel || !bar) return;

            // La primera opción (value vacío) es el placeholder "— Elige una
            // habitación —" y siempre queda arriba de todo.
            const roomOptions = Array.from(sel.options)
                .filter(o => o.value !== '')
                .map(o => ({ value: o.value, text: o.textContent.trim(), cat: o.dataset.cat }));
            const selectedCat = (roomOptions.find(o => o.value === sel.value) || {}).cat;

            window.hhFilterRooms = function (cat) {
                const keep = sel.value;
                sel.innerHTML = '<option value="" disabled>— Elige una habitación —</option>';
                roomOptions
                    .filter(o => cat === 'todas' || o.cat === cat)
                    .forEach(o => {
                        const opt = document.createElement('option');
                        opt.value = o.value;
                        opt.textContent = o.text;
                        opt.dataset.cat = o.cat;
                        sel.appendChild(opt);
                    });
                sel.value = Array.from(sel.options).some(o => o.value === keep) ? keep : '';
                bar.querySelectorAll('button').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
                try { localStorage.setItem('hh-reserva-cat', cat); } catch (e) {}
                // Si quedó una habitación puntual elegida, su categoría manda;
                // si no (recién se filtró por chip, sin elegir habitación
                // todavía), la duración igual tiene que respetar el chip —
                // si no, "GO" seguía dejando elegir 6h o 12h hasta recién
                // elegir la habitación puntual.
                hhSyncDurations(sel.value ? undefined : (cat === 'todas' ? null : cat));
                hhQuotePrice();
            };

            sel.addEventListener('change', function () { hhSyncDurations(); hhQuotePrice(); });

            let start = 'todas';
            try {
                const saved = localStorage.getItem('hh-reserva-cat');
                if (saved && bar.querySelector('button[data-cat="' + saved + '"]')) start = saved;
            } catch (e) {}
            // Si venís desde "Reservar →" de una habitación puntual, arranca en su categoría.
            if (selectedCat && bar.querySelector('button[data-cat="' + selectedCat + '"]')) start = selectedCat;
            hhFilterRooms(start);
        })();

        // Al pegar / escribir el teléfono, busca al cliente y completa el
        // resto. El número es la llave — nombre y email son solo caché.
        (function () {
            const phone = document.getElementById('customer-phone');
            const fn = document.getElementById('customer-first-name');
            const ln = document.getElementById('customer-last-name');
            const em = document.getElementById('customer-email');
            const docType = document.getElementById('document-type');
            const docNumber = document.getElementById('document-number');
            const nationality = document.getElementById('customer-nationality');
            const birthDate = document.getElementById('customer-birth-date');
            const badge = document.getElementById('cust-badge');
            const ficha = document.getElementById('cust-ficha');
            const fichaName = document.getElementById('ficha-name');
            const fichaEmail = document.getElementById('ficha-email');
            const fichaExtra = document.getElementById('ficha-extra');
            const fichaEditBtn = document.getElementById('ficha-edit-btn');
            const editableFields = document.getElementById('cust-editable-fields');
            if (!phone) return;

            const DOC_LABELS = { rut: 'RUT', pasaporte: 'Pasaporte' };

            let lastQuery = '';
            let timer = null;
            let autofilled = false;
            let editing = false;

            function showBadge(cls, html) {
                badge.className = 'cust-badge ' + cls;
                badge.innerHTML = html;
                badge.hidden = false;
            }

            // Cliente ya identificado: se muestra como ficha compilada (no
            // como inputs "ya llenos") — recepción solo necesita editar si
            // algo cambió, no re-tipear lo que el sistema ya sabe.
            function showFicha() {
                editing = false;
                fichaName.textContent = (fn.value + ' ' + ln.value).trim();
                fichaEmail.textContent = em.value || '—';
                let extra = '';
                if (nationality.value) {
                    extra += '<div class="ficha-row"><span class="ficha-label">Nacionalidad</span><span class="ficha-value">' + nationality.value + '</span></div>';
                }
                if (birthDate.value) {
                    extra += '<div class="ficha-row"><span class="ficha-label">Nacimiento</span><span class="ficha-value">' + birthDate.value.split('-').reverse().join('/') + '</span></div>';
                }
                const hasDoc = docType.value && docNumber.value;
                if (hasDoc) {
                    extra += '<div class="ficha-row"><span class="ficha-label">' + DOC_LABELS[docType.value] + '</span><span class="ficha-value">' + docNumber.value + '</span></div>';
                }
                fichaExtra.innerHTML = extra;
                ficha.hidden = false;
                editableFields.hidden = true;
                // El documento es obligatorio — si este cliente conocido no
                // tiene uno cargado, no se puede esconder detrás de la ficha
                // sin más: hay que dejarlo a la vista con una advertencia,
                // si no recepción nunca se entera de que falta.
                const docFields = document.getElementById('cust-document-fields');
                const docWarn = document.getElementById('doc-missing-warn');
                if (docFields) docFields.hidden = hasDoc;
                if (docWarn) docWarn.hidden = hasDoc;
            }

            function showEditableFields() {
                editing = true;
                ficha.hidden = true;
                editableFields.hidden = false;
                const docFields = document.getElementById('cust-document-fields');
                const docWarn = document.getElementById('doc-missing-warn');
                if (docFields) docFields.hidden = false;
                if (docWarn) docWarn.hidden = true;
            }

            if (fichaEditBtn) {
                fichaEditBtn.addEventListener('click', showEditableFields);
            }

            async function lookup() {
                const val = phone.value.trim();
                const digits = val.replace(/\D/g, '');
                if (digits.length < 9) { badge.hidden = true; ficha.hidden = true; showEditableFields(); lastQuery = ''; return; }
                if (val === lastQuery) return;
                lastQuery = val;
                try {
                    const r = await fetch('{{ route('customers.lookup') }}?phone=' + encodeURIComponent(val), { headers: { 'Accept': 'application/json' } });
                    if (!r.ok) return;
                    const d = await r.json();
                    if (d.found) {
                        fn.value = d.first_name || fn.value;
                        ln.value = d.last_name || ln.value;
                        em.value = d.email || em.value;
                        if (d.document_type) { docType.value = d.document_type; hhSyncDocField(); }
                        if (d.document_number) docNumber.value = d.document_number;
                        if (d.nationality) hhSetNationality(d.nationality);
                        if (d.birth_date) hhSetBirthDate(d.birth_date);
                        autofilled = true;
                        showFicha();
                        // Si todo el historial del cliente quedó dentro de este
                        // mismo mes, "reservas este mes" y "estadías" totales
                        // son el mismo número — mostrar los dos se lee como que
                        // se repite el dato. Se omite el total en ese caso.
                        const hot = d.monthly_visits >= 3;
                        const sameAsMonthly = hot && d.total_stays === d.monthly_visits;
                        let parts;
                        if (d.total_stays > 0) {
                            parts = ['<b>' + d.segment_label + '</b>'];
                            if (!sameAsMonthly) {
                                parts.push(d.total_stays + (d.total_stays === 1 ? ' estadía' : ' estadías'));
                            }
                            if (d.last_visit) parts.push('última ' + d.last_visit);
                        } else {
                            parts = ['<b>Ya está en la base</b>', 'sin estadías registradas todavía'];
                        }
                        let html = '✓ Datos cargados — ' + parts.join(' · ');
                        if (hot) {
                            // Una estrella por cada reserva este mes — la
                            // cantidad de estrellas es literalmente el número
                            // que aparece al lado, sin fórmula oculta.
                            // Tope en 5 para que no se descontrole el ancho.
                            const stars = '⭐'.repeat(Math.min(5, d.monthly_visits));
                            html = stars + ' <b>Recurrente del mes</b> (' + d.monthly_visits + ' reservas este mes) · ' + html.replace('✓ Datos cargados — ', '');
                        }
                        if (d.history_url) {
                            html += ' · <a href="' + d.history_url + '" target="_blank" style="color:inherit;text-decoration:underline;">ver historial</a>';
                        }
                        showBadge('known' + (hot ? ' hot' : ''), html);
                    } else {
                        if (autofilled) {
                            fn.value = ''; ln.value = ''; em.value = '';
                            docType.value = ''; docNumber.value = ''; hhSyncDocField();
                            hhSetNationality('Chilena'); hhSetBirthDate('');
                            autofilled = false;
                        }
                        showBadge('nuevo', 'Cliente nuevo — no hay nadie con este número. Completa los datos.');
                        showEditableFields();
                    }
                } catch (e) { /* silencioso: si falla la búsqueda, se carga a mano */ }
            }

            phone.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(lookup, 400); });
            phone.addEventListener('change', lookup);
            if (phone.value.trim()) lookup();
        })();

        // RUT chileno: mismo algoritmo (módulo 11) que valida el servidor,
        // en vivo, para no descubrir el error recién al enviar el formulario.
        function hhRutIsValid(raw) {
            const clean = (raw || '').toUpperCase().replace(/[^0-9K]/g, '');
            if (clean.length < 8) return false;
            const body = clean.slice(0, -1);
            const dv = clean.slice(-1);
            let sum = 0, mult = 2;
            for (let i = body.length - 1; i >= 0; i--) {
                sum += Number(body[i]) * mult;
                mult = mult === 7 ? 2 : mult + 1;
            }
            const rem = 11 - (sum % 11);
            const expected = rem === 11 ? '0' : rem === 10 ? 'K' : String(rem);
            return dv === expected;
        }

        // Documento: el tipo elegido cambia qué se puede tipear y si se
        // valida con dígito verificador (solo RUT lo tiene).
        function hhSyncDocField() {
            const type = document.getElementById('document-type');
            const number = document.getElementById('document-number');
            const hint = document.getElementById('document-hint');
            if (!type || !number) return;
            number.className = '';
            hint.className = '';
            if (!type.value) {
                number.disabled = true;
                number.value = '';
                number.placeholder = 'Elige el tipo primero';
                hint.innerHTML = '&nbsp;';
                return;
            }
            number.disabled = false;
            number.placeholder = type.value === 'rut' ? '12.345.678-9' : 'Número de pasaporte';
            hhValidateDocNumber();
        }
        function hhValidateDocNumber() {
            const type = document.getElementById('document-type');
            const number = document.getElementById('document-number');
            const hint = document.getElementById('document-hint');
            if (!type || !type.value || !number.value) { number.className = ''; hint.className = ''; hint.innerHTML = '&nbsp;'; return; }
            if (type.value !== 'rut') { number.className = ''; hint.className = ''; hint.innerHTML = '&nbsp;'; return; }
            if (hhRutIsValid(number.value)) {
                number.className = 'doc-valid';
                hint.className = 'ok';
                hint.textContent = '✓ RUT válido';
            } else {
                number.className = 'doc-invalid';
                hint.className = 'err';
                hint.textContent = 'RUT inválido — revisa el dígito verificador';
            }
        }
        // Si recepción elige el tipo de documento a mano, esa elección
        // manda — la nacionalidad deja de proponer un tipo automáticamente.
        let hhDocTypeTouched = {{ old('document_type') ? 'true' : 'false' }};
        function hhSyncDocTypeFromNationality(value) {
            if (hhDocTypeTouched) return;
            const type = document.getElementById('document-type');
            if (!type) return;
            type.value = value === 'Chilena' ? 'rut' : 'pasaporte';
            hhSyncDocField();
        }
        (function () {
            const type = document.getElementById('document-type');
            const number = document.getElementById('document-number');
            if (!type || !number) return;
            type.addEventListener('change', function () { hhDocTypeTouched = true; hhSyncDocField(); });
            number.addEventListener('input', hhValidateDocNumber);
            hhSyncDocField();
        })();

        // Nacionalidad: lista de las más frecuentes (turismo/migración
        // regional) con "Otra…" como escape a texto libre — recepción no
        // debería tener que tipear "Chilena" a mano cada vez.
        const NATIONALITY_OPTIONS = ['Chilena', 'Argentina', 'Peruana', 'Venezolana', 'Colombiana', 'Boliviana', 'Ecuatoriana', 'Brasileña', 'Paraguaya', 'Uruguaya'];
        function hhSetNationality(value) {
            const sel = document.getElementById('nationality-select');
            const other = document.getElementById('nationality-other');
            const hiddenField = document.getElementById('customer-nationality');
            if (!sel || !other || !hiddenField) return;
            if (!value) {
                sel.value = 'Chilena';
                other.hidden = true;
                other.value = '';
                hiddenField.value = 'Chilena';
                hhSyncDocTypeFromNationality('Chilena');
                return;
            }
            if (NATIONALITY_OPTIONS.includes(value)) {
                sel.value = value;
                other.hidden = true;
                other.value = '';
            } else {
                sel.value = '';
                other.hidden = false;
                other.value = value;
            }
            hiddenField.value = value;
            hhSyncDocTypeFromNationality(value);
        }
        function hhSyncNationality() {
            const sel = document.getElementById('nationality-select');
            const other = document.getElementById('nationality-other');
            const hiddenField = document.getElementById('customer-nationality');
            if (sel.value === '') {
                other.hidden = false;
                other.value = '';
                hiddenField.value = '';
                other.focus();
                hhSyncDocTypeFromNationality('');
            } else {
                other.hidden = true;
                hiddenField.value = sel.value;
                hhSyncDocTypeFromNationality(sel.value);
            }
        }
        (function () {
            const other = document.getElementById('nationality-other');
            if (!other) return;
            other.addEventListener('input', function () {
                document.getElementById('customer-nationality').value = other.value;
            });
            hhSetNationality(document.getElementById('customer-nationality').value);
        })();

        // Fecha de nacimiento con 3 selects (día/mes/año) en vez del
        // <input type="date"> nativo — para una fecha de nacimiento el
        // calendario nativo obliga a retroceder mes a mes desde hoy hasta
        // décadas atrás, que es justo el caso de uso más común acá.
        function hhSyncBirthDate() {
            const day = document.getElementById('birth-day');
            const month = document.getElementById('birth-month');
            const year = document.getElementById('birth-year');
            const hidden = document.getElementById('customer-birth-date');
            if (day.value && month.value && year.value) {
                hidden.value = year.value + '-' + month.value + '-' + day.value;
            } else {
                hidden.value = '';
            }
            hhQuotePrice();
        }
        function hhSetBirthDate(iso) {
            const day = document.getElementById('birth-day');
            const month = document.getElementById('birth-month');
            const year = document.getElementById('birth-year');
            const hidden = document.getElementById('customer-birth-date');
            if (!day || !month || !year || !hidden) return;
            if (iso && /^\d{4}-\d{2}-\d{2}$/.test(iso)) {
                const [y, m, d] = iso.split('-');
                year.value = y; month.value = m; day.value = d;
                hidden.value = iso;
            } else {
                day.value = ''; month.value = ''; year.value = '';
                hidden.value = '';
            }
        }
        (function () {
            const hidden = document.getElementById('customer-birth-date');
            if (hidden && hidden.value) hhSetBirthDate(hidden.value);
        })();

        // Interstitial de upsell: al crear la reserva, si recepción no cargó
        // nada, salta el modal con los upsells que aplican. Solo aparece con
        // el formulario completo y si hay algo para ofrecer.
        (function () {
            const overlay = document.getElementById('upsell-overlay');
            const list = document.getElementById('upsell-list');
            const form = document.querySelector('form');
            if (!overlay || !form) return;
            let resolved = false;

            function extrasTotal() {
                let n = 0;
                form.querySelectorAll('input[name^="combo_quantities"], input[name^="quantities"]').forEach(i => { n += Number(i.value) || 0; });
                n += form.querySelectorAll('input[name="accepted_upsells[]"]').length;
                return n;
            }

            const FIXED_WALK_IN_UPSELL = {{ $walkInMode ? 'true' : 'false' }};
            const ICONS = { category_upgrade: '⬆️', time_extension: '⏰', combo: '🎁' };

            function buildCard(id, kind, name, price, detail) {
                const el = document.createElement('div');
                el.className = 'upsell-item';
                el.dataset.kind = kind;
                el.dataset.id = id;
                el.innerHTML =
                    '<div><div class="ui-name">' + (ICONS[(kind.split(':')[1] || kind)] || '🎁') + ' ' + name + '</div>'
                    + '<div class="ui-price">$' + Number(price).toLocaleString('es-CL') + (detail ? ' · ' + detail : '') + '</div></div>'
                    + '<button type="button" class="ui-pick">Agregar</button>';
                el.querySelector('.ui-pick').addEventListener('click', function () {
                    const on = el.classList.toggle('picked');
                    this.textContent = on ? '✓ Agregado' : 'Agregar';
                });
                return el;
            }

            // Devuelve las cards a mostrar (upsells del quote, o combos como fallback).
            function options() {
                if (FIXED_WALK_IN_UPSELL) {
                    return (Array.isArray(hhLatestUpsells) ? hhLatestUpsells : []).filter(u => u.type !== 'combo').map(u => ({ id: u.id, kind: 'upsell:' + u.type, name: u.label, price: u.price, detail: u.detail }));
                }
                if (Array.isArray(hhLatestUpsells) && hhLatestUpsells.length) {
                    return hhLatestUpsells.map(u => ({ id: u.id, kind: 'upsell:' + u.type, name: u.label, price: u.price, detail: u.detail }));
                }
                return (HH_FALLBACK_COMBOS || []).map(c => ({ id: c.id, kind: 'combo', name: c.name, price: c.price, detail: c.detail }));
            }

            function render(opts) {
                list.innerHTML = '';
                opts.forEach(o => list.appendChild(buildCard(o.id, o.kind, o.name, o.price, o.detail)));
            }

            window.hhCloseUpsell = function () { overlay.hidden = true; };

            window.hhConfirmUpsell = function (addThem) {
                form.querySelectorAll('input[name="accepted_upsells[]"]').forEach(i => i.remove());
                if (addThem) {
                    overlay.querySelectorAll('.upsell-item.picked').forEach(item => {
                        if (item.dataset.kind.startsWith('upsell:')) {
                            const inp = document.createElement('input');
                            inp.type = 'hidden'; inp.name = 'accepted_upsells[]'; inp.value = item.dataset.id;
                            form.appendChild(inp);
                        } else {
                            const c = form.querySelector('input[name="combo_quantities[' + item.dataset.id + ']"]');
                            if (c) c.value = Math.max(1, Number(c.value) || 0);
                        }
                    });
                }
                resolved = true;
                overlay.hidden = true;
                form.requestSubmit ? form.requestSubmit() : form.submit();
            };

            form.addEventListener('submit', async function (e) {
                // La reserva anticipada se crea directo: acompañantes y consumo se piden en el check-in.
                if (resolved || !FIXED_WALK_IN_UPSELL || extrasTotal() > 0) return;
                // Formulario incompleto: que el navegador muestre su validación, sin modal.
                if (!form.checkValidity()) { form.reportValidity(); e.preventDefault(); return; }
                e.preventDefault();
                // Traer los upsells frescos por si recepción fue más rápida que el debounce.
                try { await hhQuotePriceNow(); } catch (err) {}
                const opts = options();
                if (!opts.length) { resolved = true; form.requestSubmit(); return; }
                render(opts);
                overlay.hidden = false;
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !overlay.hidden) hhCloseUpsell();
            });
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) hhCloseUpsell();
            });
        })();
    </script>
    <script>
        // Guía de pasos: 1 La estadía -> 2 El cliente -> 3 Resumen.
        // Un paso está "listo" cuando tiene lo mínimo para avanzar; el primero
        // que no está listo es el activo, y el resto se atenúa o se marca listo.
        (function () {
            const panels = Array.from(document.querySelectorAll('.step-panel'));
            const val = (id) => (document.getElementById(id)?.value || '').trim();

            function stayDone() {
                const box = document.getElementById('price-box');
                const warn = document.getElementById('time-warning');
                const quoteOk = box && !box.hidden && !box.querySelector('.p-err, .p-wait');
                const warned = warn && getComputedStyle(warn).display !== 'none';
                return !!val('room-select') && !!val('duration-select') && quoteOk && !warned;
            }

            function clientDone() {
                const email = document.getElementById('customer-email');
                const doc = document.getElementById('document-number');
                return val('customer-phone').replace(/\D/g, '').length >= 8
                    && !!val('customer-first-name') && !!val('customer-last-name')
                    && !!email && email.validity.valid && !!val('customer-email')
                    && !!val('document-type') && !!doc && !doc.disabled && !!val('document-number');
            }

            // Un cliente que llega presencial paga al ingresar: el abono solo
            // tiene sentido cuando la reserva es para más tarde. Si la hora
            // elegida es de aquí a una hora o menos, se oculta y no se envía.
            const WALK_IN_MINUTES = 60;
            const FIXED_WALK_IN = {{ $walkInMode ? 'true' : 'false' }};
            function syncDeposit() {
                const wrap = document.getElementById('deposit-wrap');
                const input = document.getElementById('deposit-amount');
                const d = val('date-input'), h = val('time-hour'), m = val('time-minute');
                if (!wrap || !input) return;
                if (!FIXED_WALK_IN && (!d || h === '' || m === '')) return;
                const start = FIXED_WALK_IN ? new Date() : new Date(d + 'T' + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':00');
                // Una reserva para dentro de una hora o menos no lleva abono, pero sigue siendo reserva:
                // el ingreso presencial solo se activa desde su propia entrada (?modo=ingreso).
                const forNow = FIXED_WALK_IN || (start.getTime() - Date.now()) / 60000 <= WALK_IN_MINUTES;
                wrap.hidden = true;
                input.value = '';
                const flag = document.getElementById('walk-in-flag');
                if (flag) flag.value = FIXED_WALK_IN ? '1' : '0';
                const btn = document.getElementById('submit-btn');
                if (btn) btn.textContent = FIXED_WALK_IN ? 'Crear, cobrar y hacer check-in →' : 'Crear reserva';
            }

            function update() {
                syncDeposit();
                const last = panels.length;
                const walk = last === 4;
                const stay = stayDone();
                // En el ingreso presencial el paso del cliente incluye a sus acompañantes: cada fila con nombre y apellido.
                const guestsOk = !walk || Array.from(document.querySelectorAll('#guest-rows .guest-row')).every((r) => r.querySelector('.guest-first').value.trim() && r.querySelector('.guest-last').value.trim());
                const client = stay && clientDone() && guestsOk;
                let active;
                if (!stay) active = 1;
                else if (!client) active = 2;
                else if (walk && !window.hhOfferResolved) active = 3;
                else active = last;
                panels.forEach((panel) => {
                    const n = Number(panel.dataset.step);
                    const isDone = n < active;
                    panel.classList.toggle('step-active', n === active);
                    panel.classList.toggle('step-done', isDone);
                    panel.classList.toggle('step-pending', n > active);
                    panel.querySelector('.step-tag').textContent = n === active ? 'Paso actual' : (isDone ? 'Listo' : '');
                });
                if (walk) {
                    const btn = document.getElementById('submit-btn');
                    const blocked = active < last;
                    btn.disabled = blocked;
                    document.getElementById('offer-needed').hidden = !(client && !window.hhOfferResolved);
                }
            }

            const form = document.querySelector('.form-grid').closest('form');
            ['input', 'change'].forEach((evt) => form.addEventListener(evt, update));
            const box = document.getElementById('price-box');
            if (box) new MutationObserver(update).observe(box, { childList: true, subtree: true, attributes: true });
            document.addEventListener('click', (e) => { if (e.target.closest('#dur-quick, .wheel, .time-quick, #room-cat-quick')) setTimeout(update, 60); });
            // El cliente existente se carga solo (sin tocar ningún campo): sin esto el paso
            // siguiente no se enciende hasta que recepción haga otra cosa en el formulario.
            setInterval(update, 700);
            update();
            setTimeout(update, 600);
        })();

        // Ingreso presencial: ofrecer consumo antes de cobrar. Lo elegido se suma
        // al total que se cobra en el pago (el servidor lo carga como consumo).
        (function () {
            const panel = document.querySelector('.consumo-panel');
            if (!panel) return;
            const inputs = Array.from(panel.querySelectorAll('.qty input'));
            const money = (n) => '$' + Number(n).toLocaleString('es-CL');
            function refresh() {
                let total = 0, items = 0;
                inputs.forEach((i) => {
                    const q = Math.max(0, Number(i.value) || 0);
                    total += q * (Number(i.dataset.price) || 0);
                    items += q;
                    i.closest('.qty').classList.toggle('has', q > 0);
                });
                const head = document.getElementById('consumo-total');
                head.textContent = items ? ('A cargar: ' + money(total) + ' · ' + items + (items === 1 ? ' ítem' : ' ítems')) : 'Sin consumo todavía';
                head.classList.toggle('has', items > 0);
                none.hidden = items > 0; ok.hidden = items === 0;
                ok.textContent = 'Continuar con ' + items + (items === 1 ? ' ítem' : ' ítems') + ' →';
                const sum = document.getElementById('consumo-sum');
                const final = Number(document.getElementById('price-box').dataset.final);
                if (items && final) {
                    sum.hidden = false;
                    sum.innerHTML = '<div class="p-sum"><span>Consumo · ' + items + (items === 1 ? ' ítem' : ' ítems') + '</span><span>+' + money(total) + '</span></div>'
                        + '<div class="p-sum total"><span>Total a cobrar</span><span>' + money(final + total) + '</span></div>';
                } else {
                    sum.hidden = true;
                }
            }
            window.hhRefreshConsumo = refresh;
            window.hhOfferResolved = false;
            const none = document.getElementById('offer-none'), ok = document.getElementById('offer-ok');
            const resolve = () => { window.hhOfferResolved = true; document.querySelector('.form-grid').closest('form').dispatchEvent(new Event('change', { bubbles: true })); };
            none.addEventListener('click', resolve);
            ok.addEventListener('click', resolve);
            panel.querySelectorAll('.qty').forEach((box) => {
                const input = box.querySelector('input');
                const step = (d) => {
                    const min = Number(input.min) || 0, max = Number(input.max) || 20;
                    input.value = Math.min(max, Math.max(min, (Number(input.value) || 0) + d));
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                };
                box.querySelector('.q-minus').addEventListener('click', () => step(-1));
                box.querySelector('.q-plus').addEventListener('click', () => step(1));
                input.addEventListener('input', refresh);
            });
            refresh();
        })();

        // Ingreso presencial: la hora es la de este instante, no la de cuando se abrió el formulario.
        (function () {
            if (!{{ $walkInMode ? 'true' : 'false' }}) return;
            const form = document.querySelector('.form-grid').closest('form');
            const clock = document.getElementById('walkin-clock');
            const pad = (n) => String(n).padStart(2, '0');
            const tick = () => { if (clock) { const n = new Date(); clock.textContent = pad(n.getHours()) + ':' + pad(n.getMinutes()); } };
            tick(); setInterval(tick, 20000);
            form.addEventListener('submit', function () {
                const n = new Date();
                document.getElementById('date-input').value = n.getFullYear() + '-' + pad(n.getMonth() + 1) + '-' + pad(n.getDate());
                document.getElementById('time-hour').value = String(n.getHours());
                document.getElementById('time-minute').value = String(Math.floor(n.getMinutes() / 5) * 5);
            }, true);
        })();
    </script>
</body>
</html>
