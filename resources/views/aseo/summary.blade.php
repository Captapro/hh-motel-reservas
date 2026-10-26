<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Aseo — {{ $title }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 24px; }
        .nav-day { display:flex; align-items:center; gap:10px; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day .today { color:#999; font-size:12.5px; }

        .period-tabs { display:flex; gap:6px; margin-bottom:20px; }
        .period-tabs a { color:#aaa; text-decoration:none; font-size:12.5px; padding:7px 14px; border-radius:7px; background:#1c1c1c; border:1px solid #333; }
        .period-tabs a.active { background:#2a1420; border-color:#ff7918; color:#ff7918; }
        .period-tabs a:hover { border-color:#ff7918; color:#ff7918; }

        .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:24px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; }
        .card .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        .card .value { font-size:22px; font-family: ui-monospace, monospace; font-weight:600; }
        .card.count .value { color:#7fbcdc; }
        .card.manual .value { color:#e8c76f; }
        .card.auto .value { color:#6fd39a; }
        .card.linen .value { color:#b088e8; }

        .linen-panel { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:16px 18px; margin-bottom:24px; }
        .linen-panel .topline2 { display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .linen-panel h3 { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#999; margin:0; }
        .linen-panel .total { font-family: ui-monospace, monospace; font-size:20px; font-weight:600; color:#7fbcdc; }
        .linen-panel .hint { font-size:11.5px; color:#777; margin-top:10px; }
        .linen-chips { display:flex; gap:10px; flex-wrap:wrap; }
        .linen-chip { background:#111; border:1px solid #333; border-radius:8px; padding:8px 14px; font-size:13px; }
        .linen-chip b { color:#7fbcdc; font-family: ui-monospace, monospace; }

        table { width:100%; border-collapse: collapse; font-size:13.5px; }
        th { text-align:left; color:#888; font-weight:500; font-size:10.5px; text-transform:uppercase; padding:8px 10px; border-bottom:1px solid #333; }
        td { padding:9px 10px; border-bottom:1px solid #242424; }
        tr:last-child td { border-bottom:none; }
        tr.today { background:#1c1420; }
        tr.empty-day td { color:#555; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; text-decoration:none; }
        .table-wrap { overflow-x:auto; background:#1c1c1c; border:1px solid #333; border-radius:10px; }
        .day-link { color:#eee; text-decoration:none; }
        .day-link:hover { color:#ff7918; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    @include('partials.aseo-tabs')
    <div class="period-tabs">
        <a href="{{ route('aseo.daily') }}">Día</a>
        <a href="{{ route('aseo.week') }}" class="{{ $period === 'week' ? 'active' : '' }}">Semana</a>
        <a href="{{ route('aseo.month') }}" class="{{ $period === 'month' ? 'active' : '' }}">Mes</a>
    </div>

    <div class="topline">
        <div>
            <h1>Aseo — {{ $title }}</h1>
            <p class="sub" style="margin:4px 0 0;">Resumen de salidas reales (check-out) y carga de ropa de cama en el rango.</p>
        </div>
        <div class="nav-day">
            <a href="{{ $prevUrl }}">← {{ $period === 'week' ? 'Semana anterior' : 'Mes anterior' }}</a>
            @unless ($isCurrent)
                <a href="{{ $currentUrl }}" class="today">{{ $period === 'week' ? 'Esta semana' : 'Este mes' }}</a>
            @endunless
            <a href="{{ $nextUrl }}">{{ $period === 'week' ? 'Semana siguiente' : 'Mes siguiente' }} →</a>
        </div>
    </div>

    <div class="cards">
        <div class="card count"><div class="label">Salidas (check-out)</div><div class="value">{{ $total }}</div></div>
        <div class="card auto"><div class="label">Aseo cerrado</div><div class="value">{{ $totalClosed }}</div></div>
        <div class="card manual"><div class="label">Aseo pendiente</div><div class="value">{{ $totalPending }}</div></div>
        <div class="card linen"><div class="label">Ropa de cama</div><div class="value">{{ $linenTotal }}</div></div>
    </div>

    <div class="linen-panel">
        <div class="topline2">
            <h3>Carga de ropa de cama por habitación</h3>
            <span class="total">{{ $linenTotal }} {{ Str::plural('juego', $linenTotal) }}</span>
        </div>
        <div class="linen-chips">
            @forelse ($linenByRoom as $entry)
                <div class="linen-chip">{{ $entry['room']->name }} <b>× {{ $entry['count'] }}</b></div>
            @empty
                <div class="linen-chip">Sin salidas con cama en el rango</div>
            @endforelse
        </div>
        <p class="hint">Un juego por cada salida de LITE, PLUS o MAX — las GO (201, 301) son cápsulas sin cama y solo llevan toallas. Detalle de toallas por reserva en la vista diaria.</p>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Día</th><th>Salidas</th><th>Aseo cerrado</th><th>Pendiente</th><th>Ropa de cama</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $d)
                    <tr class="{{ $d['date']->isToday() ? 'today' : '' }} {{ $d['count'] === 0 ? 'empty-day' : '' }}">
                        <td><a class="day-link" href="{{ route('aseo.daily', $d['date']->toDateString()) }}">{{ ucfirst($d['date']->locale('es')->isoFormat('ddd D/M')) }}</a></td>
                        <td>{{ $d['count'] }}</td>
                        <td>{{ $d['closed'] }}</td>
                        <td>{{ $d['pending'] }}</td>
                        <td>{{ $d['linen'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </div>
</body>
</html>
