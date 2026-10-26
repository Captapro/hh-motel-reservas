<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>@yield('title', 'Administración') — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .wrap { max-width: 1560px; margin: 0; padding: 18px 24px 48px; }
        h1 { font-size: 19px; margin: 0 0 4px; }
        .sub { color:#999; font-size:13px; margin-bottom:14px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; }
        h2 { font-size:14.5px; margin:0 0 8px; }
        h3 { font-size:14px; margin:0 0 8px; }
        table { width:100%; border-collapse: collapse; font-size: 14px; }
        th { text-align:left; color:#888; font-weight:500; font-size:11px; text-transform:uppercase; padding: 6px 8px; border-bottom:1px solid #333; }
        td { padding: 6px 8px; border-bottom:1px solid #262626; vertical-align: middle; }
        tr:last-child td { border-bottom:none; }
        a.btn, button.btn { display:inline-block; background:#ff7918; color:#fff; border:none; padding:9px 16px; border-radius:7px; font-size:13.5px; font-weight:600; text-decoration:none; cursor:pointer; }
        a.btn.secondary, button.btn.secondary { background:#2a2a2a; }
        a.link { color:#ff7918; text-decoration:none; font-size:13px; }
        label { display:block; font-size: 12.5px; color:#bbb; margin: 14px 0 6px; }
        input, select, textarea { width:100%; box-sizing:border-box; background:#1c1c1c; border:1px solid #333; color:#fff; padding:9px 11px; border-radius:8px; font-size:14.5px; font-family:inherit; }
        .row2 { display:flex; gap:12px; }
        .row2 > div { flex:1; }
        .pill { display:inline-block; font-family: ui-monospace, monospace; font-size: 10.5px; padding:2px 8px; border-radius:20px; background:#2a2a2a; color:#ccc; }
        .pill-warn { background:#3a331c; color:#e8c76f; }
        .pill-bad { background:#3a1c22; color:#e88a9a; }
        .stock-form { display:flex; gap:4px; align-items:center; }
        .stock-form input { width:56px; background:#111; border:1px solid #333; color:#eee; border-radius:6px; font-size:12px; padding:4px; }
        .stock-form button { background:#2a2a2a; color:#eee; border:1px solid #444; border-radius:6px; font-size:11px; padding:4px 8px; cursor:pointer; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        .actions { display:flex; gap:16px; align-items:center; }
        form.inline { display:inline; }
        /* Estructura común de las pantallas de administración */
        .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px 20px; flex-wrap:wrap; margin-bottom:14px; }
        .page-head .sub { margin:2px 0 0; }
        .cols { display:grid; gap:16px; align-items:start; }
        .cols-2 { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .cols-3 { grid-template-columns:repeat(3, minmax(0,1fr)); }
        .cols-4 { grid-template-columns:repeat(4, minmax(0,1fr)); }
        @media (max-width:1200px) { .cols-3, .cols-4 { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:820px) { .cols-2, .cols-3, .cols-4 { grid-template-columns: minmax(0, 1fr); } }
        .cols > .card { margin-bottom:0; }
        .stack { display:flex; flex-direction:column; gap:14px; min-width:0; }
        .stack > .card { margin-bottom:0; }
        .cols-h { display:flex; align-items:baseline; justify-content:space-between; gap:10px; margin-bottom:8px; }
        .cols-h h2 { margin:0; }
        .tbl-compact td { font-size:13.5px; }
        .add-row { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; }
        .add-row > div { flex:1 1 130px; min-width:0; }
        .add-row label { margin:0 0 4px; }
        .add-row button { flex:none; }
        /* Edición en una ventanita flotante, sin estirar la fila */
        details.pop { position:relative; display:inline-block; }
        details.pop > summary { cursor:pointer; color:#ff7918; font-size:13px; list-style:none; }
        details.pop > summary::-webkit-details-marker { display:none; }
        details.pop > summary.btn { display:inline-block; background:#ff7918; color:#fff; padding:9px 16px; border-radius:7px; font-size:13.5px; font-weight:600; }
        details.pop > summary.btn.secondary { background:#2a2a2a; }
        .pop-body { position:absolute; right:0; top:calc(100% + 6px); z-index:30; width:380px; max-width:86vw; background:#232323; border:1px solid #444; border-radius:10px; padding:14px; box-shadow:0 14px 34px #000a; text-align:left; }
        .pop-body form.pop-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .pop-body form.pop-grid .full { grid-column:1 / -1; }
        .pop-body input, .pop-body select { padding:7px 9px; font-size:13.5px; }
        .pop-body label { margin:0 0 3px; }
        .pop-body .danger { margin-top:10px; padding-top:10px; border-top:1px solid #3a3a3a; }
        .danger-btn { background:#b3202f; color:#fff; border:0; border-radius:7px; padding:6px 12px; font-size:12.5px; cursor:pointer; }
        /* Celular: las tablas anchas se deslizan dentro de su tarjeta en vez de ensanchar la página. */
        @media (max-width: 649px) {
            .card, .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .card > table, .table-wrap > table { min-width: 100%; }
            .page-inner h1 + .sub, .actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="wrap">
        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if (session('status'))
            <div class="card" style="border-color:#2e6e45; color:#6fd39a;">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
<script>
        // Las ventanitas de edición se cierran al tocar fuera de ellas.
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.pop[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
        });
    </script>
</body>
</html>
