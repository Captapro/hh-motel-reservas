<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Aseo del {{ $day->format('d/m/Y') }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 1100px; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 24px; }
        .nav-day { display:flex; align-items:center; gap:10px; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day .today { color:#999; font-size:12.5px; }

        .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:24px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; }
        .card .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        .card .value { font-size:22px; font-family: ui-monospace, monospace; font-weight:600; }
        .card.count .value { color:#7fbcdc; }
        .card.manual .value { color:#e8c76f; }
        .card.auto .value { color:#6fd39a; }

        table { width:100%; border-collapse: collapse; font-size:13.5px; }
        th { text-align:left; color:#888; font-weight:500; font-size:10.5px; text-transform:uppercase; padding:8px 10px; border-bottom:1px solid #333; }
        td { padding:9px 10px; border-bottom:1px solid #242424; }
        tr:last-child td { border-bottom:none; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; text-decoration:none; }
        .pill { display:inline-block; font-size:10px; font-family: ui-monospace, monospace; padding:2px 7px; border-radius:20px; background:#2a2a2a; color:#ccc; }
        .pill.manual { background:#3a331c; color:#e8c76f; }
        .pill.auto { background:#1c3a2a; color:#6fd39a; }
        .empty { color:#666; font-size:14px; padding: 30px 0; text-align:center; }
        .table-wrap { overflow-x:auto; background:#1c1c1c; border:1px solid #333; border-radius:10px; }

        .period-tabs { display:flex; gap:6px; margin-bottom:20px; }
        .period-tabs a { color:#aaa; text-decoration:none; font-size:12.5px; padding:7px 14px; border-radius:7px; background:#1c1c1c; border:1px solid #333; }
        .period-tabs a.active { background:#2a1420; border-color:#ff7918; color:#ff7918; }
        .period-tabs a:hover { border-color:#ff7918; color:#ff7918; }

        .linen-panel { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:16px 18px; margin-bottom:24px; }
        .linen-panel .topline2 { display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .linen-panel h3 { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#999; margin:0; }
        .linen-panel .total { font-family: ui-monospace, monospace; font-size:20px; font-weight:600; color:#7fbcdc; }
        .linen-panel .hint { font-size:11.5px; color:#777; margin-top:10px; }
        .linen-chips { display:flex; gap:10px; flex-wrap:wrap; }
        .linen-chip { background:#111; border:1px solid #333; border-radius:8px; padding:8px 14px; font-size:13px; }
        .linen-chip b { color:#7fbcdc; font-family: ui-monospace, monospace; }
        .pill.no-cama { background:#242424; color:#777; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    @include('partials.aseo-tabs')
    <div class="period-tabs">
        <a href="{{ route('aseo.daily') }}" class="active">Día</a>
        <a href="{{ route('aseo.week') }}">Semana</a>
        <a href="{{ route('aseo.month') }}">Mes</a>
    </div>

    <div class="topline">
        <div>
            <h1>Aseo del día — {{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }}</h1>
            <p class="sub" style="margin:4px 0 0;">Una fila por cada salida real (check-out) registrada ese día.</p>
        </div>
        <div class="nav-day">
            <a href="{{ route('aseo.daily', $day->copy()->subDay()->toDateString()) }}">← Día anterior</a>
            @unless ($day->isToday())
                <a href="{{ route('aseo.daily') }}" class="today">Ir a hoy</a>
            @endunless
            <a href="{{ route('aseo.daily', $day->copy()->addDay()->toDateString()) }}">Día siguiente →</a>
        </div>
    </div>

    <div class="cards">
        <div class="card count"><div class="label">Salidas del día</div><div class="value">{{ $rows->count() }}</div></div>
        <div class="card auto"><div class="label">Aseo cerrado</div><div class="value">{{ $rows->where('pending', false)->count() }}</div></div>
        <div class="card manual"><div class="label">Aseo pendiente</div><div class="value">{{ $rows->where('pending', true)->count() }}</div></div>
    </div>

    <div class="linen-panel">
        <div class="topline2">
            <h3>Carga de ropa de cama del día</h3>
            <span class="total">{{ $linenTotal }} {{ Str::plural('juego', $linenTotal) }}</span>
        </div>
        <div class="linen-chips">
            @forelse ($linenByRoom as $entry)
                <div class="linen-chip">{{ $entry['room']->name }} <b>× {{ $entry['count'] }}</b></div>
            @empty
                <div class="linen-chip">Sin salidas con cama este día</div>
            @endforelse
        </div>
        <p class="hint">Un juego por cada salida de LITE, PLUS o MAX, siempre asociado a la habitación puntual — las GO (201, 301) son cápsulas sin cama y solo llevan toallas.</p>
        <p class="hint">Un juego de sábanas = bajera + sábana encimera + dúvet (cubrecama) + 2 cabeceras. Las frazadas no se cambian en cada salida — la mucama revisa si están ocupadas/sucias y decide si van a lavar. Toallas = 2 grandes + 1 por cada persona adicional sobre 2 (todas las categorías, incluida GO).</p>
    </div>

    <div class="table-wrap">
        @if ($rows->isEmpty())
            <p class="empty">No hubo salidas registradas este día.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Habitación</th><th>Reserva</th><th>Salida (check-out)</th><th>Aseo listo</th><th>Quién limpió</th><th>Estado</th><th>Ropa de cama</th><th>Toallas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['room']->name }}</td>
                            <td><a class="code" href="{{ route('reservations.show', $row['booking']->code) }}">{{ $row['booking']->code }}</a></td>
                            <td>{{ $row['checkout_at']->timezone('America/Santiago')->format('H:i') }}</td>
                            <td>{{ $row['ready_at'] ? $row['ready_at']->timezone('America/Santiago')->format('d/m H:i') : '—' }}</td>
                            <td>{{ $row['cleaned_by'] ?? '—' }}</td>
                            <td>
                                @if ($row['pending'])
                                    <span class="pill manual">Pendiente</span>
                                @else
                                    <span class="pill auto">Cerrado</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['room']->category->name === 'GO')
                                    <span class="pill no-cama">Sin cama</span>
                                @else
                                    <span class="pill auto">1 juego</span>
                                @endif
                            </td>
                            <td>{{ 2 + max(0, $row['booking']->guests_count - 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    </div>
</body>
</html>
