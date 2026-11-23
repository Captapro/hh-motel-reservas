<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Panel del contador') — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <style>
        :root { --ink:#1b1f24; --muted:#667085; --line:#e4e7ec; --bg:#f6f7f9; --card:#fff; --accent:#c2410c; --ok:#15803d; --bad:#b42318; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:-apple-system,"Segoe UI",Roboto,sans-serif; font-size:14px; }
        header.top { background:#fff; border-bottom:1px solid var(--line); }
        .bar { max-width:1280px; margin:0 auto; padding:12px 20px; display:flex; flex-wrap:wrap; gap:10px 24px; align-items:center; }
        .brand { font-weight:800; letter-spacing:.02em; }
        .brand small { display:block; font-weight:500; color:var(--muted); font-size:11px; letter-spacing:0; }
        nav.tabs { display:flex; gap:4px; flex-wrap:wrap; flex:1; }
        nav.tabs a { padding:8px 14px; border-radius:8px; color:var(--muted); text-decoration:none; font-weight:600; }
        nav.tabs a.on { background:#fff1e8; color:var(--accent); }
        .who { color:var(--muted); font-size:12.5px; display:flex; gap:12px; align-items:center; }
        .who a, .who button { color:var(--muted); background:none; border:0; cursor:pointer; font:inherit; text-decoration:underline; padding:0; }
        main { max-width:1280px; margin:0 auto; padding:22px 20px 60px; }
        h1 { font-size:22px; margin:0 0 4px; }
        .sub { color:var(--muted); margin:0 0 16px; }
        .head { display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; align-items:flex-end; margin-bottom:16px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .btn { display:inline-block; padding:8px 14px; border-radius:8px; border:1px solid var(--line); background:#fff; color:var(--ink); text-decoration:none; font-weight:600; font-size:13px; }
        .btn.primary { background:var(--accent); border-color:var(--accent); color:#fff; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; margin-bottom:16px; overflow-x:auto; }
        .card h2 { font-size:15px; margin:0 0 10px; }
        .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; margin-bottom:16px; }
        .kpi { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; }
        .kpi .k { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.05em; }
        .kpi .v { font-size:24px; font-weight:800; margin-top:4px; font-variant-numeric:tabular-nums; }
        .kpi .n { color:var(--muted); font-size:12px; margin-top:2px; }
        table { width:100%; border-collapse:collapse; font-size:13.5px; }
        th { text-align:left; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; padding:8px 10px; border-bottom:1px solid var(--line); white-space:nowrap; }
        td { padding:8px 10px; border-bottom:1px solid #f0f1f3; vertical-align:top; }
        tr:last-child td { border-bottom:0; }
        tfoot td { font-weight:800; border-top:2px solid var(--line); }
        .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .muted { color:var(--muted); }
        .ok { color:var(--ok); font-weight:600; }
        .bad { color:var(--bad); font-weight:600; }
        tr.flag td { background:#fef3f2; }
        .alert { background:#fef3f2; border:1px solid #fda29b; color:var(--bad); border-radius:10px; padding:12px 14px; margin-bottom:16px; }
        .note { background:#f0f9ff; border:1px solid #b9e6fe; color:#065986; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .month { display:flex; gap:8px; align-items:center; }
        @media (max-width:640px) { .bar, main { padding-left:14px; padding-right:14px; } }
        @media print { header.top nav, .actions, .who { display:none; } body { background:#fff; } .card, .kpi { border-color:#ccc; } }
    </style>
</head>
<body>
@php $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.'); @endphp
<header class="top">
    <div class="bar">
        <div class="brand">HH MOTEL<small>Panel del contador · solo lectura</small></div>
        <nav class="tabs" aria-label="Secciones">
            @foreach ([['contador.index', 'Resumen'], ['contador.ventas', 'Libro de ventas'], ['contador.pagos', 'Libro de cobros'], ['contador.conciliacion', 'Conciliación Mercado Pago']] as [$r, $label])
                <a href="{{ route($r, ['mes' => request('mes')]) }}" class="{{ request()->routeIs($r) ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="who">
            <span>{{ auth()->user()->name }}</span>
            <a href="{{ route('account.password') }}">Cambiar clave</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Salir</button></form>
        </div>
    </div>
</header>
<main>
    @if (session('status'))<div class="note">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
