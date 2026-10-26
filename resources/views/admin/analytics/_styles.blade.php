<style>
        /* La grilla usa todo el ancho disponible (antes quedaba fija en
           1500px pegada a la izquierda, dejando un hueco enorme en
           monitores anchos). auto-fit reparte 2, 3 o más columnas según
           el espacio real en vez de un 50%/50% fijo. */
        .wrap { max-width:1700px !important; margin:0 auto !important; }
        .wrap > .flt, .wrap > .proj, .wrap > .kpis { grid-column:auto; }
        /* Secciones con título y tarjetas parejas: se lee de arriba hacia abajo (cuándo, qué, cómo se cobra). */
        .an-title { display:flex; align-items:center; gap:12px; margin:26px 0 12px; font-size:12px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:#9a9a9a; }
        .an-title::after { content:''; flex:1; height:1px; background:#2a2a2a; }
        .an-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; align-items:stretch; }
        .an-grid > .card { margin:0; height:100%; }
        .an-grid > .card.t-orange { border-top:3px solid #ff7918; }
        .an-grid > .card.t-green { border-top:3px solid #6fd39a; }
        .an-grid > .card.t-blue { border-top:3px solid #5b9dd9; }
        .an-grid > .card.span2 { grid-column: span 2; }
        @media (max-width:1150px) { .an-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } .an-grid > .card.span2 { grid-column:1 / -1; } }
        @media (max-width:760px) { .an-grid { grid-template-columns:1fr; } }
        .kpis.kpis-all { grid-template-columns:repeat(4, minmax(0, 1fr)); }
        @media (max-width:900px) { .kpis.kpis-all { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        .flt { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; margin-bottom:18px; }
        .flt-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:10px; }
        .flt-row:last-child { margin-bottom:0; }
        .flt .chip { background:#111; border:1px solid #333; border-radius:7px; padding:6px 12px; text-decoration:none; color:#ccc; font-size:12px; }
        .flt .chip.active { border-color:#ff7918; color:#ff7918; }
        .flt .chip:hover { border-color:#ff7918; }
        .flt label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.03em; margin:0; }
        .flt input[type=date], .flt select { background:#111; border:1px solid #333; color:#eee; border-radius:7px; padding:6px 9px; font-size:12.5px; width:auto; }
        .flt .go { background:#ff7918; color:#fff; border:none; border-radius:7px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .export-btn { background:#14251c; border:1px solid #2e6e45; color:#8fe0ad; border-radius:7px; padding:7px 14px; font-size:12.5px; font-weight:600; text-decoration:none; white-space:nowrap; }
        .export-btn:hover { border-color:#6fd39a; color:#fff; }
        .flt .metric-toggle { display:inline-flex; border:1px solid #333; border-radius:7px; overflow:hidden; }
        .flt .metric-toggle a { padding:6px 13px; font-size:12px; text-decoration:none; color:#aaa; background:#111; }
        .flt .metric-toggle a.on { background:#ff7918; color:#fff; }

        .kpis { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin-bottom:18px; }
        .kpi { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:13px 15px; }
        .kpi .k-label { font-size:10.5px; color:#999; text-transform:uppercase; letter-spacing:.03em; margin-bottom:5px; }
        .kpi .k-value { font-family: ui-monospace, monospace; font-weight:700; font-size:19px; color:#eee; }
        .kpi .k-delta { font-size:11.5px; font-family: ui-monospace, monospace; margin-top:3px; }
        .k-delta.up { color:#6fd39a; } .k-delta.down { color:#e88a9a; } .k-delta.flat { color:#777; }

        .proj { background:#14251c; border:1px solid #2e6e45; border-radius:10px; padding:14px 16px; margin-bottom:18px; }
        .proj h3 { margin:0 0 10px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#8fe0ad; }
        .proj-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; }
        .proj-grid div span { display:block; font-size:10.5px; color:#9cc7ac; text-transform:uppercase; letter-spacing:.03em; margin-bottom:3px; }
        .proj-grid div b { font-family: ui-monospace, monospace; font-size:17px; color:#eee; }
        .proj-grid div.big b { font-size:22px; color:#8fe0ad; }
        .proj .hint { font-size:11px; color:#7fb894; margin-top:8px; }

        .card h3 { margin:0 0 12px; font-size:13px; text-transform:uppercase; letter-spacing:.04em; color:#999; }
        .bar-row { display:grid; grid-template-columns: 130px 1fr 96px; align-items:center; gap:10px; padding:6px 0; }
        .bar-row .name { font-size:12.5px; color:#ddd; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .bar-row .cat { font-size:10px; color:#888; }
        .bar-track { background:#111; border-radius:5px; height:15px; overflow:hidden; position:relative; }
        .bar-fill { background:#ff7918; height:100%; border-radius:5px; min-width:2px; }
        .bar-fill.alt { background:#6fd39a; }
        .bar-prev { position:absolute; top:0; height:100%; border-right:2px dashed #888; }
        .bar-row .val { font-size:12px; color:#ddd; font-family: ui-monospace, monospace; text-align:right; }
        .bar-row .val small { color:#888; }

        .hour-grid { display:grid; grid-template-columns: repeat(12, 1fr); gap:5px; }
        @media (max-width: 700px) { .hour-grid { grid-template-columns: repeat(6, 1fr); } }
        .hour-cell { text-align:center; }
        .hour-track { background:#111; border-radius:4px; height:64px; display:flex; align-items:flex-end; overflow:hidden; }
        .hour-fill { background:#7fbcdc; width:100%; min-height:2px; }
        .hour-label { font-size:9.5px; color:#888; margin-top:3px; font-family: ui-monospace, monospace; }
        .hour-count { font-size:9.5px; color:#555; }

        .month-row { display:grid; grid-template-columns: 88px 1fr 100px 60px; align-items:center; gap:10px; padding:6px 0; border-top:1px solid #222; }
        .month-row:first-of-type { border-top:none; }
        .month-row .m-name { font-size:12.5px; color:#ddd; text-transform:capitalize; }
        .month-row .m-track { background:#111; border-radius:5px; height:15px; overflow:hidden; }
        .month-row .m-fill { background:#6fd39a; height:100%; border-radius:5px; min-width:2px; }
        .month-row .m-val { font-size:12px; color:#eee; font-family: ui-monospace, monospace; text-align:right; }
        .month-row .m-delta { font-size:11px; font-family: ui-monospace, monospace; text-align:right; }
        .m-delta.up { color:#6fd39a; } .m-delta.down { color:#e88a9a; } .m-delta.flat { color:#777; }
        .month-row .m-count { font-size:10.5px; color:#888; }

        .legend { font-size:11px; color:#888; margin-bottom:8px; }
        .legend .dash { display:inline-block; width:14px; border-top:2px dashed #888; vertical-align:middle; margin:0 3px; }
        .empty { color:#666; font-size:14px; padding: 16px 0; text-align:center; }

        /* Gráficos de línea (SVG, sin librería) para series continuas —
           meses y días de la semana. El resto sigue en barra porque son
           categorías sueltas, no una serie. */
        .chart-legend-row { display:flex; gap:18px; font-size:11.5px; color:#999; margin-bottom:10px; }
        .chart-legend-row .sw { display:inline-block; width:14px; height:2px; margin-right:6px; vertical-align:middle; background:#ff7918; }
        .chart-legend-row .sw.prev { background:none; border-top:2px dashed #888; height:0; }
        .svg-chart-wrap { position:relative; margin-bottom:14px; }
        .svg-chart-wrap svg { display:block; width:100%; height:auto; overflow:visible; }
        .chart-grid line { stroke:#262626; stroke-width:1; }
        .chart-axis-label { font-size:9px; fill:#666; font-family: ui-monospace, monospace; }
        .chart-xlabel { font-size:9px; fill:#777; text-anchor:middle; font-family: ui-monospace, monospace; }
        .chart-line { fill:none; stroke-width:2; }
        .chart-line.prev { stroke-dasharray:4,3; }
        .chart-area { opacity:.13; }
        .chart-point { fill:#151515; stroke-width:2; }
        .chart-crosshair { stroke:#555; stroke-width:1; stroke-dasharray:3,3; opacity:0; }
        .chart-hit { fill:transparent; }
        .chart-tooltip { position:absolute; pointer-events:none; background:#111; border:1px solid #333; border-radius:7px; padding:7px 11px; font-size:11.5px; color:#eee; box-shadow:0 6px 18px rgba(0,0,0,.5); opacity:0; transform:translate(-50%,-115%); transition:opacity .1s; white-space:nowrap; z-index:20; }
        .chart-tooltip.show { opacity:1; }
        .chart-tooltip b { display:block; font-size:12px; margin-bottom:3px; }
        .chart-tooltip .tt-row { display:flex; justify-content:space-between; gap:14px; color:#bbb; }
        .chart-tooltip .tt-row span:last-child { color:#eee; font-family: ui-monospace, monospace; }
        .chart-table { width:100%; border-collapse:collapse; font-size:12px; margin-top:8px; }
        .chart-table td { padding:4px 6px; color:#aaa; border-top:1px solid #232323; }
        .chart-table td.v { text-align:right; color:#ddd; font-family: ui-monospace, monospace; }
        .chart-detail { margin-top:10px; }
        .chart-detail summary { cursor:pointer; list-style:none; font-size:11.5px; color:#888; padding:6px 0; border-top:1px solid #232323; }
        .chart-detail summary::-webkit-details-marker { display:none; }
        .chart-detail summary::before { content:'▸ '; color:#666; }
        .chart-detail[open] summary::before { content:'▾ '; }
        .chart-detail summary:hover { color:#ccc; }
        .chart-detail[open] summary { color:#ccc; }
        .chart-table tr:first-child td { border-top:none; }

        /* ===== Tema claro: fondos blancos, sin verdes ===== */
        .an-nav { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 14px; }
        .an-nav a { padding:7px 14px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; color:#374151; font-size:13px; text-decoration:none; }
        .an-nav a:hover { border-color:#ff7918; color:#c2410c; }
        .an-nav a.on { background:#ff7918; border-color:#ff7918; color:#fff; font-weight:600; }
        .wrap h1 { color:#111827; }
        .wrap > .sub { color:#6b7280; }
        .an-title { color:#6b7280; }
        .an-title::after { background:#e5e7eb; }
        .wrap > .flt, .wrap .kpi, .wrap > .proj, .wrap .card { background:#fff !important; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(16,24,40,.05); color:#1f2937; }
        .wrap .card { border-color:#e5e7eb #e5e7eb #e5e7eb; }
        .wrap > .proj { background:#fff7ed !important; }
        .wrap .flt input, .wrap .flt select { background:#fff !important; color:#111827 !important; border:1px solid #d1d5db !important; color-scheme:light; }
        .wrap .flt select { min-height:34px; }
        .flt label { color:#6b7280; }
        .flt input[type=date], .flt select { background:#fff; border:1px solid #d1d5db; color:#111827; }
        .flt .chip { background:#fff; border:1px solid #d1d5db; color:#374151; }
        .export-btn { background:#fff; border:1px solid #ff7918; color:#c2410c; }
        .export-btn:hover { background:#fff7ed; border-color:#ff7918; color:#9a3412; }
        .kpi .k-label { color:#6b7280; }
        .kpi .k-value { color:#111827; }
        .k-delta.up, .m-delta.up { color:#2563eb; } .k-delta.down, .m-delta.down { color:#dc2626; } .k-delta.flat, .m-delta.flat { color:#6b7280; }
        .wrap > .proj { border:1px solid #fed7aa; }
        .proj h3 { color:#c2410c; }
        .proj-grid div span { color:#9a3412; }
        .proj-grid div b { color:#111827; }
        .proj-grid div.big b { color:#ea580c; }
        .proj .hint { color:#9a3412; }
        .card h3 { color:#6b7280; }
        .an-grid > .card.t-orange { border-top:3px solid #ff7918; }
        .an-grid > .card.t-green { border-top:3px solid #3b82f6; }
        .an-grid > .card.t-blue { border-top:3px solid #64748b; }
        .bar-row .name { color:#374151; }
        .bar-row .cat, .bar-row .val small, .hour-label, .hour-count, .month-row .m-count, .legend { color:#6b7280; }
        .bar-row .val, .month-row .m-val { color:#111827; }
        .bar-track, .hour-track, .month-row .m-track { background:#eef0f3; }
        .bar-fill.alt, .month-row .m-fill { background:#3b82f6; }
        .month-row { border-top:1px solid #eef0f3; }
        .month-row .m-name { color:#374151; }
        .chart-legend-row { color:#6b7280; }
        .chart-grid line { stroke:#e5e7eb; }
        .chart-axis-label, .chart-xlabel { fill:#6b7280; }
        .chart-crosshair { stroke:#9ca3af; }
        .chart-table td { color:#4b5563; border-top:1px solid #f1f2f4; }
        .chart-table td.v { color:#111827; }
        .chart-detail summary { color:#6b7280; border-top:1px solid #eef0f3; }
        .chart-detail summary:hover, .chart-detail[open] summary { color:#111827; }
        .empty { color:#9ca3af; }
    </style>
