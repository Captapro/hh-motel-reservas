<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <noscript><meta http-equiv="refresh" content="30"></noscript>
    <title>Tablero de Playrooms — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 1700px; width:100%; margin: 0; padding: 24px 24px 60px; }
        .scan-bridge { flex:none; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:8px 12px; }
        .scan-bridge .label { font-size:10px; font-weight:700; color:#bbb; text-transform:uppercase; letter-spacing:.04em; text-align:center; white-space:nowrap; }
        .scan-bridge img { width:110px; height:110px; border-radius:6px; display:block; }
        @media print, (max-width: 720px) { .scan-bridge { display:none; } }
        .flash-status { background:#0f2b22; border:1px solid #1c9169; color:#6ee7b7; padding:12px 16px; border-radius:9px; font-size:14px; font-weight:600; margin-bottom:20px; }
        .topline { margin-bottom: 14px; display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
        .topline-toolbar { display:flex; gap:8px; flex-wrap:wrap; min-width:0; }
        a.compact-toggle { text-decoration:none; display:inline-flex; align-items:center; }
        h1 { font-size: 18px; letter-spacing: .04em; margin-bottom: 2px; }
        .sub { color:#999; font-size: 13px; margin:0; }
        .sub a { color:#ff7918; text-decoration:none; }

        /* Reservas nuevas en PENDIENTE_PAGO -- lo primero que hay que ver al
           entrar o al recibir el turno, por eso va arriba de todo el
           tablero, no mezclado con las secciones de habitaciones. */
        {{-- El resto del tablero se ve claro en la práctica: partials.navbar
             inyecta public/css/hh-theme.css, que pisa (con !important) los
             colores oscuros que este archivo define más abajo. Ese stylesheet
             no conoce estas clases nuevas, así que hay que definirlas ya en
             el tono claro de una vez, no en el oscuro que "parece" el resto
             de este <style>. --}}
        .pending-row-wrap.no-pending { justify-content:flex-end; margin-bottom:12px; }
        .pending-row-wrap.no-pending .scan-bridge { flex-direction:row-reverse; gap:10px; padding:6px 12px; }
        .pending-row-wrap.no-pending .scan-bridge img { width:56px; height:56px; }
        .pending-row-wrap.no-pending .scan-bridge .label { text-align:right; }
        .pending-row-wrap { display:flex; align-items:stretch; gap:16px; margin-bottom: 26px; }
        .pending-panel { flex:1; min-width:0; background:#fff; border:1px solid #f0d9b8; border-left:4px solid #e8a23f; border-radius:11px; padding:16px 18px; box-shadow: 0 3px 10px #1011120b; }
        .pending-head { display:flex; align-items:baseline; gap:10px; margin-bottom:12px; }
        .pending-head h2 { font-size:14px; margin:0; text-transform:uppercase; letter-spacing:.05em; color:#956009; }
        .pending-head .count { font-family: ui-monospace, monospace; font-size:12px; color:#8a7256; }
        .pending-empty { color:#6b7280; font-size:13px; }
        .pending-row { display:grid; grid-template-columns: 90px 1fr auto auto 20px; align-items:center; gap:14px; padding:10px 0; border-top:1px solid #f0e6d8; text-decoration:none; color:#202124; }
        .pending-row:first-child { border-top:none; }
        .pending-row:hover { color:#b64c0a; }
        .pending-row .pcode { font-family: ui-monospace, monospace; font-size:12px; color:#8a7256; }
        .pending-row .pinfo strong { display:block; font-size:13.5px; }
        .pending-row .pinfo small { color:#6b7280; font-size:11.5px; }
        .pending-row .page { font-size:11.5px; color:#8a7256; white-space:nowrap; }
        .pending-row .ptags { display:flex; gap:6px; align-items:center; justify-content:flex-end; }
        .pending-row .ptag { font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:20px; white-space:nowrap; }
        .pending-row .ptag.sent { background:#e7f7ef; color:#0e9f6e; }
        .pending-row .ptag.unsent { background:#fdecdc; color:#b64c0a; border:1px solid #f0c89a; }
        /* Temporizador de atención: recién llegada se ve neutra, a los 3
           minutos pasa a amarillo y a los 5 a rojo -- para que una reserva
           nueva nunca quede sin que alguien la mire. Se recalcula solo cada
           30s (la recarga automática de la página), no hace falta JS. */
        .pending-row.age-warn { background:#fffaf0; border-top-color:#f5d99a; }
        .pending-row.age-warn .pcode, .pending-row.age-warn .pinfo small { color:#a3690a; }
        .pending-row.age-urgent { background:#fdf0f0; border-top-color:#f0b8b8; }
        .pending-row.age-urgent .pcode, .pending-row.age-urgent .pinfo small { color:#c0392b; font-weight:700; }
        .pending-row.age-urgent .pinfo strong { color:#c0392b; }
        @media (max-width:700px) { .pending-row { grid-template-columns: 1fr auto; grid-template-areas:"info tag" "code page"; } .pending-row .pinfo { grid-area:info; } .pending-row .ptag { grid-area:tag; } .pending-row .pcode { grid-area:code; } .pending-row .page { grid-area:page; } .pending-row .arrow { display:none; } }

        .summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom: 30px; }
        .summary .chip { display:flex; align-items:baseline; gap:7px; background:#1c1c1c; border:1px solid #333; border-radius:8px; padding:9px 14px; }
        .summary .chip b { font-size:18px; font-family: ui-monospace, monospace; }
        .summary .chip span { font-size:11.5px; color:#999; text-transform:uppercase; letter-spacing:.04em; }
        .summary .chip.ocupadas b { color:#e88a9a; }
        .summary .chip.aseo b { color:#e8c76f; }
        .summary .chip.disponibles b { color:#34d399; }
        .summary .chip.proximas b { color:#e8c76f; }
        .summary .chip.fuera b { color:#aaa; }

        a.sales-btn { background:#1c1c1c; border-color:#333; color:#6ee7b7; font-weight:600; }
        a.sales-btn:hover { border-color:#34d399; color:#fff; }

        .daily-summary { display:grid; grid-template-columns:repeat(5, 1fr); gap:8px; margin-bottom:30px; }
        @media (max-width: 760px) { .daily-summary { grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); } }
        .dsum-tile { display:flex; flex-direction:column; gap:3px; background:#1c1c1c; border:1px solid #333; border-radius:9px; padding:10px 12px; text-decoration:none; min-width:0; }
        .dsum-tile:hover { border-color:#666; }
        .dsum-tile.total { background:#1c1c1c; border-color:#ff7918; }
        .dsum-label { font-size:10px; color:#999; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dsum-tile.total .dsum-label { color:#ffb379; }
        .dsum-value { font-family: ui-monospace, monospace; font-weight:700; font-size:16px; color:#eee; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .page-grid { display:grid; grid-template-columns: 1fr 280px; gap:28px; align-items:start; }
        @media (max-width: 1100px) { .page-grid { grid-template-columns: minmax(0, 1fr); } }

        .section { margin-bottom: 34px; }
        .section-head { display:flex; align-items:baseline; gap:10px; margin-bottom: 14px; }
        .section-head h2 { font-size: 14px; margin:0; text-transform:uppercase; letter-spacing:.05em; }
        .section-head .count { font-family: ui-monospace, monospace; font-size: 12px; color:#777; }
        .section-head .bar { flex:1; height:1px; background:#292929; }
        .section.ocupadas .section-head h2 { color:#e88a9a; }
        /* Panel enmarcado: mismo marco que "Por llegar", pero horizontal. */
        .section.panel-box { border:1px solid #dddfe1; border-radius:18px; background:#fff; padding:14px 14px 4px; }
        .section.ocupadas.panel-box .section-head h2 { color:#a63a4a; }
        .section.aseo.panel-box .section-head h2 { color:#9a7414; }
        .section.fuera.panel-box .section-head h2 { color:#5f6368; }
        .section.panel-box .grid { margin-bottom:10px; }
        .panel-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:14px; margin-bottom:34px; align-items:start; }
        .panel-row > .section { margin-bottom:0; min-width:0; }
        .section.aseo .section-head h2 { color:#e8c76f; }
        .section.disponibles .section-head h2 { color:#34d399; }
        .section.fuera .section-head h2 { color:#aaa; }
        .section-empty { color:#666; font-size:13px; }

        .cat-filter { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
        /* Sin relleno y con un punto del color de la categoría (el mismo del
           borde de la tarjeta); el filtro activo queda blanco con borde oscuro. */
        .cat-filter button { display:inline-flex; align-items:center; gap:7px; background:transparent; border:1px solid #d1d3d8; color:#42464e; border-radius:7px; padding:6px 13px; font-size:12px; font-weight:600; cursor:pointer; --dot:#888; }
        .cat-filter button[data-cat]::before { content:""; width:8px; height:8px; border-radius:50%; background:var(--dot); flex:none; }
        .cat-filter button[data-cat="todas"] { --dot:#6fd39a; }
        .cat-filter button[data-cat="go"]   { --dot:#5b9dd9; }
        .cat-filter button[data-cat="lite"] { --dot:#4ecdc4; }
        .cat-filter button[data-cat="plus"] { --dot:#b088e8; }
        .cat-filter button[data-cat="max"]  { --dot:#f2994a; }
        .cat-filter button[data-cat="new-lite"] { --dot:#e8c76f; }
        .cat-filter button:hover { border-color:#9a9da3; color:#202124; }
        .cat-filter button.active { background:#fff; border-color:#202124; color:#202124; box-shadow:0 1px 3px #10111214; }
        .cat-hidden { display:none !important; }
        .cat-filter .upcoming-filter svg { width:14px; height:14px; flex:none; }
        .cat-filter .cat-filter-sep { width:1px; align-self:stretch; background:#d1d3d8; margin:2px 4px; }

        .sidebar { position:sticky; top:20px; align-self:start; border:1px solid #dddfe1; border-radius:18px; background:#fff; padding:14px 14px 4px; }
        .sidebar .sidebar-title h2 { color:#8a5a00; }
        .sidebar .sidebar-hint { color:#6b6b6b; }
        .sidebar .proxima-card { max-width:none; background:#fff1e3 !important; border:1px solid #f5b98a !important; border-left-width:4px !important; box-shadow:none; }
        .sidebar .proxima-card.cat-go { border-left-color:#5b9dd9 !important; }
        .sidebar .proxima-card.cat-lite { border-left-color:#2fb3aa !important; }
        .sidebar .proxima-card.cat-plus { border-left-color:#9b6fe0 !important; }
        .sidebar .proxima-card.cat-max { border-left-color:#f2994a !important; }
        .sidebar .proxima-card.cat-new-lite { border-left-color:#d4a72c !important; }
        .sidebar .proxima-card .card-head b { color:#1a1a1a; }
        .sidebar .proxima-card .cat { color:#7a6a58; }
        .sidebar .proxima-card .guest { color:#2a2a2a; }
        .sidebar .proxima-card .when { color:#b4540a; }
        .sidebar .proxima-card:hover { box-shadow:0 6px 18px rgba(255,121,24,.22); }
        .sidebar-title { display:flex; align-items:baseline; gap:8px; margin-bottom:12px; }
        .sidebar-title h2 { font-size: 13px; margin:0; text-transform:uppercase; letter-spacing:.05em; color:#e8c76f; }
        .sidebar-title .count { font-family: ui-monospace, monospace; font-size: 12px; color:#777; }
        .sidebar-hint { font-size:11.5px; color:#999; margin:0 0 14px; line-height:1.4; }
        .sidebar-empty { color:#8a8d94; font-size:12.5px; border:1px dashed #d4d6da; border-radius:10px; padding:14px; text-align:center; margin-bottom:10px; }
        .sidebar.is-empty .sidebar-hint { display:none; }
        @media (max-width: 1100px) { .sidebar.is-empty { display:none; } }
        @media (orientation: portrait) and (min-width: 650px) { .sidebar.is-empty { display:none; } }
        .proxima-card { background:#231f14; border:1px solid #5a5230; border-left-width:4px; border-radius:10px; padding:10px 12px; margin-bottom:10px; max-width:520px; transition: transform .13s ease, box-shadow .13s ease; }
        .proxima-card { cursor:pointer; }
        .proxima-card:focus-visible { outline:2px solid #111; outline-offset:2px; }
        .proxima-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(232,199,111,.18); }
        .proxima-card.cat-go { border-left-color:#5b9dd9; }
        .proxima-card.cat-lite { border-left-color:#4ecdc4; }
        .proxima-card.cat-plus { border-left-color:#b088e8; }
        .proxima-card.cat-max { border-left-color:#f2994a; }
        .proxima-card.cat-new-lite { border-left-color:#e8c76f; }
        .proxima-card .card-head { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:2px; }
        .proxima-card .card-head b { font-size:14px; }
        .proxima-card .cat { font-size:11px; color:#999; margin-bottom:8px; }
        .proxima-card .guest { font-size:12px; color:#ddd; margin-bottom:2px; }
        .proxima-card .when { font-size:11px; color:#e8c76f; font-weight:600; margin-bottom:8px; }
        .proxima-card form { margin-top:0; }
        .checkin-btn { display:block; width:100%; text-align:center; background:#ff7918; color:#fff; border:1px solid #ff7918; text-decoration:none; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .checkin-btn:hover { background:#df6209; }

        .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
        .card { background:#1c1c1c; border:1px solid #333; border-left-width:4px; border-radius: 10px; padding: 14px 16px; transition: transform .13s ease, box-shadow .13s ease, border-color .13s ease; }
        /* Solo transform + box-shadow: los compone la GPU, no re-maquetan la grilla — no se siente lento. */
        .card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.45); border-color:#555; }
        .card.cat-go:hover { border-left-color:#7fbfe8; box-shadow: 0 6px 20px rgba(91,157,217,.22); }
        .card.cat-lite:hover { border-left-color:#6fe4db; box-shadow: 0 6px 20px rgba(78,205,196,.22); }
        .card.cat-plus:hover { border-left-color:#c9a6f5; box-shadow: 0 6px 20px rgba(176,136,232,.22); }
        .card.cat-max:hover { border-left-color:#f7b06a; box-shadow: 0 6px 20px rgba(242,153,74,.22); }
        .card.cat-new-lite { border-left-width:3px; box-shadow: 0 0 0 1px rgba(232,199,111,.25) inset; }
        .card.cat-new-lite:hover { border-left-color:#f0d98a; box-shadow: 0 6px 20px rgba(232,199,111,.3); }
        @media (prefers-reduced-motion: reduce) { .card { transition: none; } .card:hover { transform: none; } }
        .card-head { display:flex; justify-content:space-between; align-items:baseline; gap:6px; margin-bottom:2px; }
        .card-head b { font-size: 16px; }
        .offer-badge { font-size:11px; font-weight:800; background:linear-gradient(135deg,#ff8a1f,#e94d13); color:#fff; border:1px solid #ffb15c; border-radius:7px; padding:5px 9px; white-space:nowrap; flex:none; box-shadow:0 3px 10px rgba(255,112,20,.28); letter-spacing:.01em; }
        .compact .offer-badge { font-size:10px; padding:4px 7px; }
        .maintenance-badge { font-size:10.5px; font-weight:700; background:#3a1c22; color:#e88a9a; border:1px solid #7a2d2d; border-radius:20px; padding:2px 8px; white-space:nowrap; flex:none; }
        .compact .maintenance-badge { font-size:9px; padding:1px 6px; }
        .card.needs-maintenance { box-shadow: 0 0 0 1px rgba(232,138,154,.4) inset; }
        a.inspect-btn { display:block; text-align:center; margin-top:8px; text-decoration:none; padding:7px; border-radius:7px; font-size:12px; font-weight:600; background:transparent; border:1px dashed #4a3a3a; color:#c98a8a; }
        a.inspect-btn:hover { border-color:#e88a9a; color:#e88a9a; border-style:solid; }
        .compact a.inspect-btn { padding: 5px; font-size: 10.5px; margin-top: 5px; }
        .cat { font-size: 11.5px; color:#999; margin-bottom: 10px; }
        /* Franja de color por categoría — para reconocer de un vistazo sin leer el texto. */
        .card.cat-go { border-left-color:#5b9dd9; }
        .card.cat-lite { border-left-color:#4ecdc4; }
        .card.cat-plus { border-left-color:#b088e8; }
        .card.cat-max { border-left-color:#f2994a; }
        .card.cat-new-lite { border-left-color:#e8c76f; }
        .cat-go .cat { color:#5b9dd9; }
        .cat-lite .cat { color:#4ecdc4; }
        .cat-plus .cat { color:#b088e8; }
        .cat-max .cat { color:#f2994a; }
        .cat-new-lite .cat { color:#e8c76f; font-weight:700; }
        .pill { display:inline-block; font-family: ui-monospace, monospace; font-size: 11px; padding:3px 9px; border-radius:20px; font-weight:600; margin-bottom:6px; }
        .pill-libre { background:#0f2b22; color:#34d399; }
        .pill-ocupada { background:#3a1c22; color:#e88a9a; }
        .pill-reservada { background:#2a2010; color:#e8a23f; }
        .pill-aseo { background:#3a331c; color:#e8c76f; }
        .pill-mantencion, .pill-inactiva { background:#2a2a2a; color:#aaa; }
        .pill-activa { background:#1c2f3a; color:#7fbcdc; }
        .eta { font-size: 11.5px; color:#999; margin-top: 6px; }
        .eta.overtime { color:#e88a9a; font-weight:600; }
        .overdue-room { border-color:#e05252 !important; box-shadow:0 0 0 2px rgba(224,82,82,.2), 0 8px 22px rgba(224,82,82,.12); }
        .overdue-room .overtime { color:#ff7777; font-weight:800; animation: overdue-warning 1.5s ease-in-out infinite; }
        .overdue-room .pill-ocupada { background:#7d2635; color:#fff; }
        @keyframes overdue-warning { 0%,100% { opacity:1; text-shadow:0 0 0 rgba(255,82,82,0); } 50% { opacity:.45; text-shadow:0 0 10px rgba(255,82,82,.9); } }
        @media (prefers-reduced-motion: reduce) { .overdue-room .overtime { animation:none; } }
        .code { font-family: ui-monospace, monospace; font-size: 11px; margin-top:2px; color:#888; }
        a.consumo-btn { display:block; text-align:center; margin-top:6px; background:#1c2f3a; border:1px solid #3a5a72; color:#7fbcdc; text-decoration:none; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; }
        a.consumo-btn:hover { border-color:#7fbcdc; }
        details { margin-top: 8px; }
        summary {
            font-size: 12px; color:#ccc; cursor:pointer; list-style:none;
            text-align:center; padding:8px; border-radius:7px; border:1px solid #3a3a3a; background:#242424;
        }
        summary::-webkit-details-marker { display:none; }
        summary:hover { border-color:#666; color:#fff; }
        details[open] summary { border-color:#ff7918; color:#ff7918; margin-bottom:8px; }
        form { margin-top: 8px; display:flex; gap:6px; }
        select { flex:1; background:#111; border:1px solid #333; color:#eee; border-radius:6px; font-size:12px; padding:6px; }
        button { background:#2a2a2a; color:#eee; border:1px solid #444; border-radius:6px; font-size:11.5px; padding:4px 10px; cursor:pointer; }
        button:hover { border-color:#ff7918; }
        a.reserve-btn { display:block; text-align:center; margin-top:10px; background:#ff7918; border:1px solid #ff7918; color:#fff; text-decoration:none; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; }
        a.reserve-alt { display:block; text-align:center; margin-top:6px; color:#9a9ca1; font-size:11.5px; text-decoration:none; }
        a.reserve-alt:hover { color:#ff7918; }
        .compact a.reserve-alt, .chips a.reserve-alt { display:none; }
        .aseo-ready-form { margin-top:8px; display:block; }
        .aseo-cleaner-input { display:block; width:100%; box-sizing:border-box; margin-bottom:6px; }
        .aseo-cleaner-input:focus { outline:none; border-color:#e8c76f; }
        .aseo-ready-btn { display:block; width:100%; text-align:center; background:#3a331c; border:1px solid #6b5a1e; color:#e8c76f; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .aseo-ready-btn:hover { border-color:#e8c76f; }
        a.checkout-btn { display:block; text-align:center; margin-top:8px; background:#2a2a2a; border:1px solid #444; color:#eee; text-decoration:none; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; }
        a.checkout-btn:hover { border-color:#ff7918; color:#ff7918; }
        a.checkout-btn.pending-balance { background:#2a2010; border-color:#6b5a1e; color:#e8a23f; }
        a.checkout-btn.pending-balance:hover { border-color:#e8a23f; color:#ffcf7a; }
        .bookings-btn { display:block; width:100%; box-sizing:border-box; text-align:center; margin-top:8px; text-decoration:none; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; background:none; font-family:inherit; cursor:pointer; }
        /* hh-theme.css (inyectado por partials.navbar, que en el documento
           queda DESPUÉS de este <style>) trae su propia
           "a.bookings-btn.has-bookings" -- misma o mayor especificidad que
           la de acá, así que gana igual sin importar qué tan abajo esté
           este bloque. Hace falta una especificidad mayor de verdad
           (calificado por .card), no solo igualarla. */
        /* Mismo tono que .pill-aseo (ya probado en el resto del tablero) en
           vez de un ámbar más saturado/duro. */
        .card a.bookings-btn.has-bookings { background:#3a331c; border:1px solid #6b5f30; color:#e8c76f; font-weight:800; }
        .card a.bookings-btn.has-bookings:hover { border-color:#e8c76f; background:#463d20; }
        .bookings-btn.empty { background:transparent; border:1px dashed #333; color:#666; font-weight:500; }
        .bookings-btn.empty:hover { border-color:#555; color:#999; }
        .copy-link-btn { display:block; width:100%; box-sizing:border-box; text-align:center; margin-top:8px; padding:8px; border-radius:7px; font-size:12.5px; font-weight:600; font-family:inherit; cursor:pointer; background:#1c2f3a; border:1px solid #3a5a72; color:#7fbcdc; }
        .copy-link-btn:hover { border-color:#7fbcdc; background:#213847; }

        /* Vista compacta — para cuando hay muchas habitaciones y hay que ver más de un vistazo. */
        .compact-toggle { background:#1c1c1c; border:1px solid #333; color:#ccc; border-radius:7px; padding:8px 13px; font-size:12.5px; font-weight:600; cursor:pointer; white-space:nowrap; }
        .compact-toggle:hover { border-color:#ff7918; color:#ff7918; }
        .compact .grid { grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 10px; }
        .compact .card { padding: 8px 10px; }
        .compact .card-head b { font-size: 13px; }
        .compact .cat { font-size: 10px; margin-bottom: 4px; }
        .compact .pill { font-size: 9.5px; padding: 2px 7px; margin-bottom: 3px; }
        .compact .eta { font-size: 10px; margin-top: 3px; }
        .compact .code { font-size: 9px; }
        .compact a.reserve-btn, .compact .aseo-ready-btn, .compact .bookings-btn, .compact a.checkout-btn, .compact .checkin-btn, .compact a.consumo-btn, .compact .copy-link-btn { padding: 5px; font-size: 10.5px; margin-top: 5px; }
        .compact .aseo-cleaner-input { padding: 5px 7px; font-size: 10.5px; margin-bottom: 5px; }
        .compact details { margin-top: 5px; }
        .compact summary { padding: 5px; font-size: 10.5px; }
        .compact details form { flex-direction: column; gap: 5px; }
        .compact details select { width: 100%; font-size: 10.5px; padding: 5px; }
        .compact details button { width: 100%; font-size: 10.5px; padding: 5px; }
        .compact .section { margin-bottom: 20px; }
        .compact .proxima-card { padding: 8px 10px; margin-bottom: 8px; }
        /* Vista compacta: tarjeta de ~80px. Nombre + estado, categoría + situación y una sola fila de botones. */
        .card .sm, .card .brk { display:none; }
        .compact .page-grid .main-col .grid, .compact .grid { grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)) !important; gap: 8px; align-items: start; }
        .compact .card, .compact .page-grid .main-col .card { display:flex; flex-wrap:wrap; align-items:center; gap:3px 5px; padding:6px 8px; }
        .compact .card .card-head { order:1; flex:1 1 auto; margin:0; min-width:0; }
        .compact .card .pill { order:2; flex:0 1 auto; margin:0 0 0 auto; max-width:100%; white-space:normal; }
        .compact .card .brk { display:block; flex:1 1 100%; height:0; }
        .compact .card .b1 { order:3; } .compact .card .b2 { order:6; }
        .compact .card .cat { order:4; flex:0 0 auto; margin:0; }
        .compact .card .cat-label { display:none; }
        .compact .card .eta { order:5; flex:1 1 0; min-width:0; margin:0; text-align:right; }
        .compact .card .eta.aseo-help, .compact .card .eta[style] { flex:1 1 100%; text-align:left; }
        .compact .card .code { order:5; flex:1 1 100%; margin:0; }
        .compact .card a.reserve-btn { order:7; flex:1 1 0; min-width:0; margin:0; padding:5px 4px; font-size:11px; white-space:nowrap; }
        .compact .card a.consumo-btn, .compact .card a.checkout-btn, .compact .card .checkin-btn { order:7; flex:1 1 100%; margin:0; padding:5px; }
        .compact .card .aseo-ready-form { order:7; flex:1 1 100%; margin:2px 0 0; }
        .compact .card .chip-actions { order:7; }
        .compact .card .bookings-btn, .compact .card .copy-link-btn { order:8; flex:0 0 auto; width:auto; margin:0; padding:4px 6px; font-size:11px; white-space:nowrap; }
        .compact .card details { order:8; flex:0 0 auto; margin:0; }
        .compact .card details[open] { flex:1 1 100%; }
        .compact .card summary { padding:4px 7px; font-size:11px; margin:0; }
        .compact .card .lg { display:none; } .compact .card .sm { display:inline; }
        .compact .section { margin-bottom: 14px; }

        /* Vista compacta, más limpia: tarjeta sobria, categoría con un punto,
           texto secundario apagado, "Ingresar" como botón de contorno (antes
           eran 24 bloques naranjos iguales) y los atajos como íconos sin caja. */
        /* Mismo ancho de tarjeta en todas las secciones (Ocupadas, Disponibles,
           En aseo, Fuera de servicio). hhSyncCompactWidth() calcula ese ancho
           para que entren todas las columnas posibles en la columna principal
           sin pasarse a "Por llegar"; la altura sí cambia según las acciones. */
        .compact .page-grid .main-col .grid, .compact .grid { grid-template-columns: repeat(auto-fill, var(--hh-card-w, 210px)) !important; gap: 10px; }
        .compact .card, .compact .page-grid .main-col .card { border-top-color:#2a2a2a; border-right-color:#2a2a2a; border-bottom-color:#2a2a2a; border-left-width:3px; border-radius:10px; padding:10px 11px 8px 12px; gap:5px 6px; }
        .compact .card:hover { transform:none; box-shadow:0 4px 14px rgba(0,0,0,.35); border-top-color:#3a3a3a; border-right-color:#3a3a3a; border-bottom-color:#3a3a3a; }
        .compact .card-head b { font-size:14px; font-weight:650; letter-spacing:-.01em; }
        .compact .card .pill { font-family:inherit; font-size:9.5px; font-weight:700; letter-spacing:.05em; padding:2px 7px; background:transparent !important; border:1px solid currentColor; opacity:.85; }
        .compact .card .cat { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:500; opacity:.95; }
        .compact .card .cat::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
        .compact .card .eta { font-size:11px; color:#8a8a8a; }
        .compact .card .eta.overtime { color:#ff8a8a; }
        .compact .card .code { font-size:9.5px; color:#6f6f6f; }
        .compact .card a.reserve-btn { flex:1 1 0; background:transparent !important; border:1px solid rgba(255,121,24,.6) !important; color:#ffa565 !important; font-size:11.5px; font-weight:600; padding:5px 8px; border-radius:7px; transition:background .12s ease, color .12s ease; }
        .compact .card a.reserve-btn:hover { background:#ff7918 !important; color:#21170e !important; border-color:#ff7918 !important; }
        .compact .card a.consumo-btn { background:transparent; border-color:#2f4a5c; font-size:11px; padding:5px; }
        .compact .card a.checkout-btn { font-size:11px; padding:5px; }
        .compact .card .aseo-ready-btn { font-size:11px; padding:6px; }
        .compact .card .bookings-btn, .compact .card .copy-link-btn, .compact .card summary { display:inline-flex; align-items:center; justify-content:center; gap:3px; min-width:26px; height:26px; padding:0 6px !important; background:transparent !important; border:1px solid transparent !important; border-radius:6px; color:#7d7d7d !important; font-size:11px; font-weight:600; box-shadow:none; }
        .compact .card .bookings-btn:hover, .compact .card .copy-link-btn:hover, .compact .card summary:hover { background:#262626 !important; color:#e5e5e5 !important; }
        .compact .card a.bookings-btn.has-bookings { color:#e8c76f !important; }
        .compact .card details[open] summary { color:#ff9a4d !important; }
        .compact .card .sm svg { width:14px; height:14px; display:block; }
        /* Vista chips — máxima densidad: solo categoría, número y estado.
           El resto (botones, código, detalle de estado) se oculta; la
           tarjeta entera pasa a ser clickeable hacia el detalle de la
           habitación (ver hhBindChipClicks). */
        .chips .grid { grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; }
        .chips .section { margin-bottom: 18px; }
        .chips .card { padding: 9px 10px; cursor: pointer; }
        .chips .card:hover { border-color: #666; }
        .chips .card-head b { font-size: 12.5px; }
        .chips .cat { font-size: 9.5px; margin-bottom: 5px; }
        .chips .pill { font-size: 9px; padding: 2px 6px; margin-bottom: 0; }
        .chips .eta:not(.aseo-help), .chips .code, .chips .offer-badge, .chips .maintenance-badge,
        .chips a.reserve-btn, .chips .bookings-btn, .chips .copy-link-btn,
        .chips a.checkout-btn, .chips .checkin-btn, .chips a.consumo-btn, .chips a.inspect-btn,
        /* Acotado a las tarjetas -- un selector ".chips form" a secas también
           escondía los formularios de los toggles de Ala Sur/categoría de
           más arriba, que no tienen nada que ver con la densidad de tarjetas. */
        .chips .card details, .chips .card form:not(.aseo-ready-form) { display: none !important; }
        /* En aseo, la acción de reactivar es lo único que recepción necesita hacer: se mantiene visible. */
        .chips .aseo-ready-form { margin-top:6px; }
        .chips .aseo-cleaner-input { font-size:10px; padding:3px 4px; margin-bottom:4px; }
        .chips .aseo-ready-btn { font-size:10px; padding:4px; }
        .chips .eta.aseo-help { font-size:10px; }
        .chips .proxima-card { padding: 8px 10px; margin-bottom: 8px; }
        /* Atajos de la vista chips (habitaciones ocupadas): historial y consumo rápido. */
        .chip-actions { display:none; flex-wrap:wrap; gap:5px; margin-top:7px; }
        .chips .chip-actions { display:flex; }
        .chip-actions a { flex:1 1 auto; text-align:center; text-decoration:none; background:#262626; border:1px solid #3a3a3a; color:#ddd; border-radius:6px; padding:5px 4px; font-size:10.5px; font-weight:600; white-space:nowrap; }
        .chip-actions a:hover { border-color:#ff7918; color:#fff; }
        .chip-actions a.has { border-color:#e8c76f; color:#e8c76f; }
        .chip-actions a.none { color:#888; }
        .chip-actions a.plus { background:#ff7918; border-color:#ff7918; color:#111; }
            #pass-scans:empty { display:none; }
        #pass-scans { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
        .pass-scan { display:flex; align-items:center; gap:14px; background:#0f2b22; border:1px solid #1c9169; border-left:5px solid #2dd4a0; color:#d6fff0; padding:12px 16px; border-radius:10px; animation:passPulse 1.6s ease-in-out 3; }
        .pass-scan .ps-main { flex:1; font-size:15px; }
        .pass-scan .ps-main small { display:block; color:#7fd8b6; font-size:12.5px; margin-top:2px; }
        .pass-scan a { background:#ff7918; color:#111; text-decoration:none; font-weight:700; padding:10px 18px; border-radius:8px; font-size:14px; white-space:nowrap; }
        .pass-scan.pay-alert { background:#12293a; border-color:#3a5a72; border-left-color:#6cb6ff; color:#dff1ff; }
        .pass-scan.pay-alert .ps-main small { color:#9edaff; }
        .pass-scan.pay-alert.review { background:#3a2a12; border-color:#7a5a1f; border-left-color:#e8a23f; color:#ffe6bd; }
        .pass-scan.pay-alert.review .ps-main small { color:#ffd699; }
        .pass-scan .pa-x { background:transparent; border:0; color:inherit; opacity:.7; cursor:pointer; font-size:16px; padding:6px; }
        .pass-scan .pa-x:hover { opacity:1; }
        @keyframes passPulse { 0%,100% { box-shadow:0 0 0 0 rgba(45,212,160,.5); } 50% { box-shadow:0 0 0 8px rgba(45,212,160,0); } }
        /* Más lugar para las habitaciones: encabezado, métricas y avisos en poco alto. */
        .topline h1 { font-size:20px; }
        .topline .sub { margin:2px 0 0; font-size:12px; }
        .topline-toolbar { align-items:center; }
        .topline-toolbar .scan-bridge { flex-direction:row-reverse; gap:10px; padding:5px 10px; }
        .topline-toolbar .scan-bridge img { width:54px; height:54px; }
        .topline-toolbar .scan-bridge .label { text-align:right; max-width:70px; white-space:normal; }
        .wing-filter { display:inline-flex; border:1px solid #333; border-radius:8px; overflow:hidden; background:#1c1c1c; }
        .wing-filter button { background:transparent; border:0; border-right:1px solid #333; color:#999; padding:7px 13px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .wing-filter button:last-child { border-right:0; }
        .wing-filter button:hover { color:#fff; }
        .wing-filter button.active { background:#ff7918; color:#111; }
        .wing-hidden { display:none !important; }
        .summary .chip { transition: border-color .12s, transform .12s; }
        .summary .chip:hover { border-color:#ff7918; transform: translateY(-1px); }
        .stats-row { display:flex; gap:14px; align-items:stretch; flex-wrap:wrap; margin-bottom:18px; }
        .stats-row .summary { margin-bottom:0; gap:8px; }
        .stats-row .summary .chip { padding:7px 12px; }
        .stats-row .daily-summary { flex:1; min-width:min(480px, 100%); margin-bottom:0; gap:6px; }
        .stats-row .dsum-tile { padding:6px 10px; }
        .pending-row-wrap { margin-bottom:14px; }
        .page-grid.no-sidebar { grid-template-columns: minmax(0, 1fr); }
        .section { margin-bottom:22px; }
        .section-head { margin-bottom:10px; }
</style>
    <script>
        // Tres niveles de densidad: normal → compacta → chips → normal.
        // Se aplica antes de pintar para no hacer parpadear el layout.
        // 'hh-compact-board'='1' es el valor viejo (antes de que existiera
        // "chips") — se migra a 'compact' para no perder la preferencia.
        (function () {
            let density = localStorage.getItem('hh-board-density');
            if (!density) density = localStorage.getItem('hh-compact-board') === '1' ? 'compact' : 'normal';
            if (density === 'compact' || density === 'chips') {
                document.documentElement.classList.add(density);
            }
        })();
    </script>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    @if (session('status'))
        <div class="flash-status">{{ session('status') }}</div>
    @endif
    <div class="topline">
        <div>
            <h1>HH MOTEL — Tablero de Playrooms</h1>
            <p class="sub">Se actualiza solo cada 30s · <a href="{{ route('rooms.board') }}">actualizar ahora</a></p>
        </div>
        <div class="topline-toolbar">
            <a class="compact-toggle" href="{{ route('catalog.index') }}" target="_blank" rel="noopener">Ver catálogo ↗</a>
            <button type="button" class="compact-toggle" id="catalog-link-btn" data-room-link="{{ route('catalog.index') }}" onclick="hhCopyRoomLink(this)">Copiar link del catálogo</button>
            <div class="wing-filter" id="wing-filter" role="group" aria-label="Filtrar por ala">
                <button type="button" data-wing="todas" onclick="hhSetWing('todas')">Todas las alas</button>
                <button type="button" data-wing="norte" onclick="hhSetWing('norte')">Ala norte</button>
                <button type="button" data-wing="sur" onclick="hhSetWing('sur')">Ala sur</button>
            </div>
            <button type="button" class="compact-toggle" id="compact-toggle-btn" onclick="hhToggleCompact()">⊟ Vista compacta</button>
        {{-- Puente PC → celular: la cámara para escanear el pase de un huésped
         está en el celular, no en este monitor -- recepción escanea este QR
         con su celular y ahí se le abre directo /reservas/escanear. --}}
    <div class="scan-bridge">
        <span class="label">Validador de tickets</span>
        <img src="{{ route('reservations.scan_qr_image') }}" alt="Escaneá con el celular para abrir la cámara de reservas">
    </div>
        </div>
    </div>

    {{-- Pases escaneados con el celular: aviso para atenderlos acá, en el computador. --}}
    <div id="pass-scans"></div>

    {{-- Los interruptores de Ala Sur / categoría / piso se movieron al panel
         de administración (Categorías) -- no es responsabilidad de quien
         está en el tablero prender o apagar disponibilidad. --}}

    @if ($pendingBookings->isNotEmpty())
    <div class="pending-row-wrap">
    <div class="pending-panel">
        <div class="pending-head"><h2>⚠ Pendientes de gestionar</h2><span class="count">{{ $pendingBookings->count() }}</span></div>
        @if ($pendingBookings->isEmpty())
            <p class="pending-empty">No hay reservas nuevas esperando confirmación de pago.</p>
        @else
            @foreach ($pendingBookings as $booking)
                @php $ageMinutes = $booking->created_at->diffInMinutes(now()); @endphp
                <a class="pending-row {{ $ageMinutes >= 5 ? 'age-urgent' : ($ageMinutes >= 3 ? 'age-warn' : '') }}" href="{{ route('reservations.show', $booking->code) }}">
                    <span class="pcode">{{ $booking->code }}</span>
                    <span class="pinfo"><strong>{{ $booking->customer->name }}</strong><small>{{ $booking->room->name }} · {{ $booking->room->category->name }} · llegó {{ $booking->created_at->timezone('America/Santiago')->diffForHumans() }}</small></span>
                    <span class="page">{{ $booking->starts_at->timezone('America/Santiago')->isToday() ? 'hoy '.$booking->starts_at->timezone('America/Santiago')->format('H:i') : $booking->starts_at->timezone('America/Santiago')->format('d/m H:i') }}</span>
                    <span class="ptags">
                        @if ($booking->expires_at)
                            <span class="ptag {{ $booking->expires_at->diffInMinutes(now(), false) > -3 ? 'unsent' : 'sent' }}">⏳ vence {{ $booking->expires_at->diffForHumans() }}</span>
                        @else
                            <span class="ptag sent">Sin vencimiento</span>
                        @endif
                        @if ($booking->payment_instructions_sent_at)
                            <span class="ptag sent">✓ Ya se le avisó</span>
                        @else
                            <span class="ptag unsent">Sin contactar</span>
                        @endif
                    </span>
                    <span class="arrow">→</span>
                </a>
            @endforeach
        @endif
    </div>
    </div>
    @endif

    <div class="stats-row">
    <div class="summary">
        <div class="chip ocupadas"><b>{{ $grouped['ocupadas']->count() }}</b><span>Ocupadas</span></div>
        <div class="chip aseo"><b>{{ $grouped['en_aseo']->count() }}</b><span>En aseo</span></div>
        <div class="chip disponibles"><b>{{ $grouped['disponibles']->count() }}</b><span>Disponibles</span></div>
        <div class="chip proximas"><b>{{ $grouped['proximas']->count() }}</b><span>Por llegar</span></div>
        <div class="chip fuera"><b>{{ $grouped['fuera_de_servicio']->count() }}</b><span>Fuera de servicio</span></div>
    </div>

    <div class="daily-summary">
        <a href="{{ auth()->user()?->canAccess('ventas') ? route('sales.daily') : '#' }}" class="dsum-tile">
            <span class="dsum-label">Playrooms vendidos hoy</span>
            <span class="dsum-value">{{ $dailySummary['rooms_count'] }}</span>
        </a>
        <a href="{{ auth()->user()?->canAccess('ventas') ? route('sales.daily') : '#' }}" class="dsum-tile">
            <span class="dsum-label">Extras vendidos hoy</span>
            <span class="dsum-value">{{ $dailySummary['extras_count'] }}</span>
        </a>
        <a href="{{ auth()->user()?->canAccess('ventas') ? route('sales.daily') : '#' }}" class="dsum-tile">
            <span class="dsum-label">Venta Playrooms</span>
            <span class="dsum-value">${{ number_format($dailySummary['rooms_revenue'], 0, ',', '.') }}</span>
        </a>
        <a href="{{ auth()->user()?->canAccess('ventas') ? route('sales.daily') : '#' }}" class="dsum-tile">
            <span class="dsum-label">Venta extras</span>
            <span class="dsum-value">${{ number_format($dailySummary['extras_revenue'], 0, ',', '.') }}</span>
        </a>
        <a href="{{ auth()->user()?->canAccess('ventas') ? route('sales.daily') : '#' }}" class="dsum-tile total">
            <span class="dsum-label">Total del día</span>
            <span class="dsum-value">${{ number_format($dailySummary['rooms_revenue'] + $dailySummary['extras_revenue'], 0, ',', '.') }}</span>
        </a>
    </div>

    </div>

    <div class="page-grid">
        <div class="main-col">
            {{-- Siempre ocupa su lugar (aunque no haya ocupadas), igual que "Por llegar", para que nada se corra al ocuparse una habitación. --}}
            <div class="section ocupadas panel-box">
                <div class="section-head"><h2>Ocupadas</h2><span class="count" id="ocupadas-count">{{ $grouped['ocupadas']->count() }}</span></div>
                <div class="sidebar-empty panel-empty" id="ocupadas-empty" @if ($grouped['ocupadas']->isNotEmpty()) hidden @endif>Ninguna habitación ocupada ahora.</div>
                @if ($grouped['ocupadas']->isNotEmpty())
                    <div class="grid">
                        @foreach ($grouped['ocupadas'] as $entry)
                            @include('rooms._card', ['entry' => $entry])
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="section disponibles">
                <div class="section-head"><h2>Disponibles</h2><span class="count" id="disponibles-count">{{ $grouped['disponibles']->count() }}</span><span class="bar"></span></div>
                @php
                    $boardCategories = collect()
                        ->concat($grouped['ocupadas'])->concat($grouped['en_aseo'])->concat($grouped['disponibles'])
                        ->concat($grouped['proximas'])->concat($grouped['fuera_de_servicio'])
                        ->pluck('room.category')->unique('id')->sortBy('display_order')->values();
                @endphp
                <div class="cat-filter" id="cat-filter">
                    @if ($boardCategories->count() > 1)
                        <button type="button" data-cat="todas" onclick="hhFilterCategory('todas')">Todas</button>
                        @foreach ($boardCategories as $cat)
                            <button type="button" data-cat="{{ Str::slug($cat->name) }}" onclick="hhFilterCategory('{{ Str::slug($cat->name) }}')">{{ $cat->name }}</button>
                        @endforeach
                    @endif
                    <button type="button" class="upcoming-filter" id="upcoming-filter-btn" onclick="hhToggleUpcomingFilter()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.8 21H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><path d="M16 3v4M8 3v4M4 11h10"/><circle cx="18" cy="18" r="4"/><path d="M18 16.5V18l1 1"/></svg>Con reservas futuras</button>
                    {{-- Mismo filtro de ala que el de arriba (sincronizados); aquí a mano junto a los demás filtros. --}}
                    <span class="cat-filter-sep" aria-hidden="true"></span>
                    <button type="button" class="wing-chip" data-wing-btn="norte" onclick="hhToggleWingChip('norte')">Ala norte</button>
                    <button type="button" class="wing-chip" data-wing-btn="sur" onclick="hhToggleWingChip('sur')">Ala sur</button>
                </div>
                @if ($grouped['disponibles']->isEmpty())
                    <p class="section-empty">No hay habitaciones disponibles ahora mismo.</p>
                @else
                    <div class="grid" id="disponibles-grid" data-total="{{ $grouped['disponibles']->count() }}">
                        @foreach ($grouped['disponibles'] as $entry)
                            @include('rooms._card', ['entry' => $entry])
                        @endforeach
                    </div>
                    <p class="section-empty cat-hidden" id="disponibles-none">Ninguna habitación disponible en esa categoría ahora mismo.</p>
                @endif
            </div>


            {{-- En aseo y Fuera de servicio: mismo panel enmarcado que Ocupadas, siempre visibles y lado a lado, debajo de Disponibles. --}}
            <div class="panel-row">
            <div class="section aseo panel-box">
                <div class="section-head"><h2>En aseo</h2><span class="count">{{ $grouped['en_aseo']->count() }}</span></div>
                <div class="sidebar-empty panel-empty" @if ($grouped['en_aseo']->isNotEmpty()) hidden @endif>Ninguna habitación en aseo ahora.</div>
                @if ($grouped['en_aseo']->isNotEmpty())
                    <p class="section-empty" style="margin-bottom:10px;">No vuelven a disponibles hasta que recepción marque "aseo listo" en cada una.</p>
                    <div class="grid">
                        @foreach ($grouped['en_aseo'] as $entry)
                            @include('rooms._card', ['entry' => $entry])
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="section fuera panel-box">
                <div class="section-head"><h2>Fuera de servicio</h2><span class="count">{{ $grouped['fuera_de_servicio']->count() }}</span></div>
                <div class="sidebar-empty panel-empty" @if ($grouped['fuera_de_servicio']->isNotEmpty()) hidden @endif>Ninguna habitación fuera de servicio.</div>
                @if ($grouped['fuera_de_servicio']->isNotEmpty())
                    <div class="grid">
                        @foreach ($grouped['fuera_de_servicio'] as $entry)
                            @include('rooms._card', ['entry' => $entry])
                        @endforeach
                    </div>
                @endif
            </div>
            </div>
        </div>

        {{-- La columna "Por llegar" siempre ocupa su lugar: así las habitaciones no se corren cuando aparece o desaparece una reserva. --}}
        <div class="sidebar {{ $grouped['proximas']->isEmpty() ? 'is-empty' : '' }}">
            <div class="sidebar-title"><h2>Por llegar</h2><span class="count">{{ $grouped['proximas']->count() }}</span></div>
            <p class="sidebar-hint">Libres ahora, con una reserva próxima: no las asignes a clientes sin reserva.</p>
            @foreach ($grouped['proximas'] as $entry)
                @php $mins = $entry['status']['minutes_until_next']; @endphp
                <div class="proxima-card cat-{{ Str::slug($entry['room']->category->name) }}" data-wing="{{ $entry['room']->wing ?? 'none' }}" data-href="{{ route('reservations.show', $entry['status']['next_booking']->code) }}" role="link" tabindex="0" title="Abrir la reserva">
                    <div class="card-head"><b>{{ $entry['room']->name }}</b></div>
                    <div class="cat">{{ $entry['room']->category->name }}</div>
                    <div class="guest">{{ $entry['status']['next_booking']->customer->name }}</div>
                    <div class="when">
                        @if ($mins > 0) llega en {{ \App\Support\TimeFormat::minutes($mins) }}
                        @elseif ($mins === 0) llegando ahora
                        @else atrasada {{ \App\Support\TimeFormat::minutes(abs($mins)) }} — sin check-in
                        @endif
                    </div>
                    <a class="checkin-btn" href="{{ route('bookings.checkin.show', $entry['status']['next_booking']->code) }}">Marcar check-in</a>
                </div>
            @endforeach
            <div class="sidebar-empty" id="sidebar-empty" @if ($grouped['proximas']->isNotEmpty()) hidden @endif>Ninguna reserva por llegar ahora.</div>
        </div>
    </div>
    </div>
    <script>
        // Tarjetas de "Por llegar": un clic en cualquier parte abre la reserva
        // (el botón de check-in conserva su propio enlace).
        document.querySelectorAll('.proxima-card[data-href]').forEach(function (card) {
            card.addEventListener('click', function (e) { if (!e.target.closest('a, button')) window.location.href = card.dataset.href; });
            card.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.target.closest('a, button')) window.location.href = card.dataset.href; });
        });
    </script>
    <script>
        // Ciclo de densidad: normal → compacta → chips → normal. Un solo
        // botón en vez de sumar más controles al toolbar — pedido explícito
        // de no llenar de elementos esa zona.
        const DENSITY_LABELS = { normal: '⊟ Vista compacta', compact: '▦ Vista chips', chips: '⊞ Vista normal' };
        const DENSITY_NEXT = { normal: 'compact', compact: 'chips', chips: 'normal' };
        function hhCurrentDensity() {
            if (document.documentElement.classList.contains('chips')) return 'chips';
            if (document.documentElement.classList.contains('compact')) return 'compact';
            return 'normal';
        }
        function hhSetCompactLabel() {
            document.getElementById('compact-toggle-btn').textContent = DENSITY_LABELS[hhCurrentDensity()];
        }
        function hhToggleCompact() {
            const next = DENSITY_NEXT[hhCurrentDensity()];
            document.documentElement.classList.remove('compact', 'chips');
            if (next !== 'normal') document.documentElement.classList.add(next);
            localStorage.setItem('hh-board-density', next);
            hhSetCompactLabel();
            hhSyncCompactWidth();
        }
        hhSetCompactLabel();

        // Vista compacta: cuántas tarjetas de al menos 200 px caben en el panel
        // de Ocupadas (el más angosto de la columna principal) y qué ancho
        // exacto llena esa fila; todas las secciones usan ese mismo ancho.
        function hhSyncCompactWidth() {
            const root = document.documentElement;
            const box = document.querySelector('.section.ocupadas.panel-box');
            if (!root.classList.contains('compact') || !box) { root.style.removeProperty('--hh-card-w'); return; }
            const cs = getComputedStyle(box);
            const inner = box.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
            const gap = 10, min = 200;
            const cols = Math.max(1, Math.floor((inner + gap) / (min + gap)));
            root.style.setProperty('--hh-card-w', Math.floor((inner - gap * (cols - 1)) / cols) + 'px');
        }
        hhSyncCompactWidth();
        window.addEventListener('resize', hhSyncCompactWidth);

        // Copiar el link de la ficha pública sin salir del tablero -- antes
        // el único camino era abrir la ficha en otra pestaña y copiarlo ahí,
        // lo que sacaba a recepción del flujo. navigator.clipboard falla
        // callado en algunos navegadores embebidos, así que hay respaldo
        // con execCommand('copy').
        function hhCopyRoomLink(btn) {
            const url = btn.dataset.roomLink;
            const original = btn.innerHTML;
            const show = (ok) => {
                btn.textContent = ok ? '¡Copiado!' : 'No se pudo copiar';
                setTimeout(() => { btn.innerHTML = original; }, 1800);
            };
            const fallback = () => {
                const tmp = document.createElement('input');
                tmp.value = url;
                document.body.appendChild(tmp);
                tmp.select();
                try { show(document.execCommand('copy')); } catch (e) { show(false); }
                document.body.removeChild(tmp);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => show(true)).catch(fallback);
            } else {
                fallback();
            }
        }

        // Vista chips: la tarjeta entera es clickeable hacia el detalle de
        // la habitación (sus botones quedan ocultos por CSS en ese modo).
        document.querySelectorAll('.grid .card[data-href]').forEach(function (card) {
            card.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, form')) return;
                if (hhCurrentDensity() === 'chips' || card.classList.contains('overdue-room') || card.querySelector('.pill-ocupada')) window.location.href = card.dataset.href;
            });
        });

        // Filtro de categoría — solo afecta la sección "Disponibles". Las
        // ocupadas y las "por llegar" quedan siempre visibles. La elección
        // se guarda y se re-aplica en cada refresco automático (cada 30s).
        function hhFilterCategory(cat) {
            localStorage.setItem('hh-board-category', cat);
            hhApplyCategoryFilter();
        }
        // Segundo filtro, independiente de la categoría: mostrar solo las
        // disponibles que YA tienen una reserva futura encima -- para no
        // ofrecérselas de largada a un walk-in sin fijarse.
        function hhToggleUpcomingFilter() {
            localStorage.setItem('hh-board-only-upcoming', localStorage.getItem('hh-board-only-upcoming') === '1' ? '0' : '1');
            hhApplyCategoryFilter();
        }
        function hhApplyCategoryFilter() {
            const grid = document.getElementById('disponibles-grid');
            const filterBar = document.getElementById('cat-filter');
            if (!filterBar) return;
            let cat = localStorage.getItem('hh-board-category') || 'todas';
            if (cat !== 'todas' && !filterBar.querySelector('button[data-cat="' + cat + '"]')) {
                cat = 'todas';
            }
            filterBar.querySelectorAll('button[data-cat]').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));

            const onlyUpcoming = localStorage.getItem('hh-board-only-upcoming') === '1';
            const upcomingBtn = document.getElementById('upcoming-filter-btn');
            if (upcomingBtn) upcomingBtn.classList.toggle('active', onlyUpcoming);

            const countEl = document.getElementById('disponibles-count');
            if (!grid) { return; }

            let shown = 0;
            grid.querySelectorAll('.card').forEach(card => {
                const match = (cat === 'todas' || card.classList.contains('cat-' + cat))
                    && (!onlyUpcoming || card.classList.contains('has-upcoming'));
                card.classList.toggle('cat-hidden', !match);
                if (match && !card.classList.contains('wing-hidden')) shown++;
            });

            const total = grid.querySelectorAll('.card:not(.wing-hidden)').length;
            if (countEl) countEl.textContent = (cat === 'todas' && !onlyUpcoming) ? total : (shown + ' de ' + total);
            const none = document.getElementById('disponibles-none');
            if (none) none.classList.toggle('cat-hidden', shown > 0);
        }
        // Filtro por ala (norte / sur): oculta las tarjetas de las otras alas en todo el tablero
        // y recalcula los contadores. Se recuerda en este navegador.
        function hhSetWing(w) {
            localStorage.setItem('hh-board-wing', w);
            hhApplyWingFilter();
            hhApplyCategoryFilter();
        }
        // Botones de ala junto a los filtros de Disponibles: apretar el que ya
        // está activo vuelve a "Todas las alas".
        function hhToggleWingChip(w) {
            hhSetWing((localStorage.getItem('hh-board-wing') || 'todas') === w ? 'todas' : w);
        }
        function hhApplyWingFilter() {
            const w = localStorage.getItem('hh-board-wing') || 'todas';
            document.querySelectorAll('[data-wing]').forEach(function (el) {
                if (el.closest('#wing-filter')) return;
                el.classList.toggle('wing-hidden', w !== 'todas' && el.dataset.wing !== w);
            });
            document.querySelectorAll('#wing-filter button').forEach(function (b) { b.classList.toggle('active', b.dataset.wing === w); });
            document.querySelectorAll('.cat-filter .wing-chip').forEach(function (b) { b.classList.toggle('active', b.dataset.wingBtn === w); });
            ['ocupadas', 'aseo', 'fuera', 'disponibles'].forEach(function (name) {
                const sec = document.querySelector('.section.' + name);
                if (!sec) return;
                const n = sec.querySelectorAll('.card:not(.wing-hidden)').length;
                const count = sec.querySelector('.section-head .count');
                if (count && name !== 'disponibles') count.textContent = n;
                const pe = sec.querySelector('.panel-empty'); if (pe) pe.hidden = n > 0;
                const chip = document.querySelector('.summary .chip.' + name + ' b');
                if (chip) chip.textContent = n;
            });
            const proximas = document.querySelectorAll('.sidebar .proxima-card:not(.wing-hidden)').length;
            const pc = document.querySelector('.summary .chip.proximas b'); if (pc) pc.textContent = proximas;
            const sc = document.querySelector('.sidebar-title .count'); if (sc) sc.textContent = proximas;
            const se = document.getElementById('sidebar-empty'); if (se) se.hidden = proximas > 0;
        }
        // Los contadores de arriba llevan a su sección.
        document.querySelectorAll('.summary .chip').forEach(function (chip) {
            const name = ['ocupadas', 'aseo', 'disponibles', 'fuera'].find(function (n) { return chip.classList.contains(n); });
            if (!name) return;
            chip.style.cursor = 'pointer';
            chip.title = 'Ir a la sección';
            chip.addEventListener('click', function () {
                const sec = document.querySelector('.section.' + name);
                if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
        hhApplyWingFilter();
        hhApplyCategoryFilter();
    </script>

    <script>
        // Avisos de pases escaneados con el celular (se consulta cada pocos segundos).
        (function () {
            const box = document.getElementById('pass-scans');
            if (!box) return;
            const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            let last = '';
            // Los avisos de pago se pueden cerrar; se recuerda cuáles en esta pestaña.
            const dismissed = () => { try { return JSON.parse(sessionStorage.getItem('hh-dismissed-alerts') || '[]'); } catch (e) { return []; } };
            box.addEventListener('click', (e) => {
                const x = e.target.closest('[data-dismiss]');
                if (!x) return;
                try { sessionStorage.setItem('hh-dismissed-alerts', JSON.stringify(dismissed().concat(x.dataset.dismiss))); } catch (err) { /* sin almacenamiento */ }
                x.closest('.pass-scan').remove();
            });
            async function poll() {
                try {
                    const r = await fetch('{{ route('rooms.pass_scans') }}', { headers: { 'Accept': 'application/json' } });
                    if (!r.ok) return;
                    const list = await r.json();
                    const sig = JSON.stringify(list);
                    if (sig === last) return;
                    last = sig;
                    box.innerHTML = list.filter((p) => !dismissed().includes(p.id)).map((p) => p.type === 'pago'
                        ? '<div class="pass-scan pay-alert' + (p.review ? ' review' : '') + '"><div class="ps-main"><b>💳 Pago recibido — ' + esc(p.customer) + ' · $' + Number(p.amount).toLocaleString('es-CL') + '</b><small>' + esc(p.room) + ' · ' + esc(p.code) + (p.review ? ' · revisa esta reserva: pagó fuera de tiempo o de más' : ' · reserva confirmada') + '</small></div><a href="' + esc(p.url) + '">Ver reserva →</a><button type="button" class="pa-x" data-dismiss="' + esc(p.id) + '" aria-label="Cerrar aviso">✕</button></div>'
                        : '<div class="pass-scan"><div class="ps-main"><b>📲 Pase escaneado — ' + esc(p.customer) + '</b><small>' + esc(p.room) + ' · ' + esc(p.code) + (p.balance > 0 ? ' · falta cobrar $' + Number(p.balance).toLocaleString('es-CL') : ' · pagado') + '</small></div><a href="' + esc(p.url) + '">' + (p.balance > 0 ? 'Cobrar y hacer check-in →' : 'Hacer check-in →') + '</a></div>').join('');
                } catch (e) { /* sin red: se reintenta */ }
            }
            poll();
            // Solo consulta con la pestaña a la vista: varias pantallas abiertas no cargan al servidor.
            setInterval(() => { if (!document.hidden) poll(); }, 6000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
        })();
    </script>
<script>
    // Se recarga solo cada 30 s, salvo mientras alguien usa el chat de Ayuda
    // (window.hhHoldRefresh lo marca partials/help-widget): recargar lo cerraría.
    setInterval(function () { if (!window.hhHoldRefresh) window.location.reload(); }, 30000);
</script>
</body>
</html>
