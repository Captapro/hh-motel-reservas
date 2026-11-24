<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Panel del contador') — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <style>
        /* Mismo lenguaje visual que el sistema: riel oscuro a la izquierda, fondo gris cálido, tarjetas blancas y naranja de marca. */
        :root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --bg:#f5f5f4; --card:#fff; --accent:#ff7918; --accent-ink:#a74600; --ok:#15803d; --bad:#b42318; --rail:174px; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; font-size:14px; line-height:1.45; padding-left:var(--rail); font-variant-numeric:tabular-nums; }
        a { color:var(--accent-ink); }
        .rail { position:fixed; inset:0 auto 0 0; width:var(--rail); background:#101112; color:#eee; z-index:50; padding:22px 10px; display:flex; flex-direction:column; border-right:1px solid #252629; }
        .rail-brand { display:flex; align-items:center; gap:10px; padding:0 10px 25px; color:#fff; text-decoration:none; }
        .rail-brand b { color:var(--accent); font-size:27px; letter-spacing:-2px; }
        .rail-brand span { font-size:13px; font-weight:750; letter-spacing:.08em; }
        .rail-brand small { display:block; font-size:10px; color:#aaa; font-weight:400; letter-spacing:.02em; margin-top:2px; }
        .rail nav { display:grid; gap:7px; }
        .rail-link { display:flex; align-items:center; gap:12px; padding:12px 10px; border-radius:8px; color:#bfc1c5; font-size:12px; text-decoration:none; }
        .rail-link svg { width:21px; height:21px; flex-shrink:0; }
        .rail-link:hover { color:#fff; background:#252525; }
        .rail-link.is-active { color:#ffab6d; background:#382315; box-shadow:inset 3px 0 var(--accent); }
        .rail-footer { margin-top:auto; padding:16px 10px 0; color:#85878c; font-size:11px; display:grid; gap:6px; }
        .rail-footer a, .rail-footer button { color:#bfc1c5; background:none; border:0; cursor:pointer; font:inherit; text-decoration:none; padding:0; text-align:left; }
        .rail-footer a:hover, .rail-footer button:hover { color:#fff; }
        main { max-width:1500px; padding:25px 28px 60px; }
        h1 { font-size:25px; letter-spacing:-.035em; line-height:1.25; margin:0 0 4px; color:#111827; }
        .sub { color:var(--muted); margin:0 0 16px; }
        .head { display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; align-items:flex-end; margin-bottom:16px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .btn { display:inline-block; padding:9px 16px; border-radius:7px; border:1px solid #d1d5db; background:#fff; color:#374151; text-decoration:none; font-weight:600; font-size:13.5px; cursor:pointer; }
        .btn:hover { border-color:var(--accent); }
        .btn.primary { background:var(--accent); border-color:var(--accent); color:#21170e; font-weight:750; box-shadow:0 3px 8px #ff791822; }
        .btn.primary:hover { background:#ee6909; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; margin-bottom:16px; overflow-x:auto; box-shadow:0 1px 2px rgba(16,24,40,.05); }
        .card h2 { font-size:15px; margin:0 0 10px; color:#111827; }
        .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; margin-bottom:16px; }
        .kpi { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; box-shadow:0 1px 2px rgba(16,24,40,.05); }
        .kpi .k { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.05em; }
        .kpi .v { font-size:24px; font-weight:800; margin-top:4px; color:#111827; }
        .kpi .n { color:var(--muted); font-size:12px; margin-top:2px; }
        table { width:100%; border-collapse:collapse; font-size:13.5px; }
        th { text-align:left; background:#f9fafb; color:var(--muted); font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:.04em; padding:8px 10px; border-bottom:1px solid var(--line); white-space:nowrap; }
        td { padding:8px 10px; border-bottom:1px solid #eef0f3; vertical-align:top; }
        tbody tr:hover td { background:#fafafa; }
        tr:last-child td { border-bottom:0; }
        tfoot td { font-weight:800; border-top:2px solid var(--line); background:#f9fafb; }
        .num { text-align:right; white-space:nowrap; }
        .muted { color:var(--muted); }
        .ok { color:var(--ok); font-weight:600; }
        .bad { color:var(--bad); font-weight:600; }
        tr.flag td { background:#fef3f2; }
        .alert { background:#fef3f2; border:1px solid #fda29b; color:var(--bad); border-radius:10px; padding:12px 14px; margin-bottom:16px; }
        .note { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
        .month { display:flex; gap:8px; align-items:center; }
        input, select { background:#fff; color:#111827; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font:inherit; }
        input:focus, select:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px #ff791826; }
        @media (max-width:820px) {
            body { padding-left:0; }
            .rail { position:static; width:auto; flex-direction:row; flex-wrap:wrap; align-items:center; gap:6px 14px; padding:10px 14px; }
            .rail-brand { padding:0 6px 0 0; }
            .rail nav { display:flex; flex-wrap:wrap; gap:4px; }
            .rail-link { padding:8px 10px; }
            .rail-link svg { display:none; }
            .rail-footer { margin:0 0 0 auto; padding:0; display:flex; gap:12px; align-items:center; }
            main { padding:18px 14px 50px; }
        }
        @media print { .rail, .actions { display:none; } body { background:#fff; padding-left:0; } .card, .kpi { border-color:#ccc; box-shadow:none; } main { padding:0; } }
    </style>
</head>
<body>
@php $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.'); @endphp
@php
    $nav = [
        ['contador.index', 'Resumen', 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z'],
        ['contador.ventas', 'Libro de ventas', 'M12 2v20 M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        ['contador.pagos', 'Libro de cobros', 'M3 5h18v16H3z M3 9h18 M15 13h6v4h-6z'],
        ['contador.conciliacion', 'Conciliación MP', 'M2 6h20v12H2z M2 10h20 M6 15h4 M15 15h3'],
    ];
@endphp
<aside class="rail" aria-label="Secciones">
    <a class="rail-brand" href="{{ route('contador.index') }}"><b>HH</b><span>MOTEL<small>Panel del contador<br>solo lectura</small></span></a>
    <nav>
        @foreach ($nav as [$r, $label, $path])
            <a href="{{ route($r, ['mes' => request('mes')]) }}" class="rail-link {{ request()->routeIs($r) ? 'is-active' : '' }}" title="{{ $label }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    <div class="rail-footer">
        @unless (auth()->user()->isContador())
            <a href="{{ route('rooms.board') }}" style="color:#ffab6d;font-weight:700;">← Volver al sistema</a>
        @endunless
        <span>{{ auth()->user()->name }}</span>
        <a href="{{ route('account.password') }}">Cambiar clave</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Salir</button></form>
    </div>
</aside>
<main>
    @if (session('status'))<div class="note">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
