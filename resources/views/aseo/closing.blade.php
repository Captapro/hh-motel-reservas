<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Cierre de Playrooms {{ $report['date']->format('d/m/Y') }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; }
        h2 { font-size: 13px; text-transform:uppercase; letter-spacing:.04em; color:#999; margin: 28px 0 10px; font-weight:600; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 20px; line-height:1.5; }
        .nav-day { display:flex; align-items:center; gap:10px; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day .off { opacity:.35; pointer-events:none; }

        .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:8px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; }
        .card .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        .card .value { font-size:22px; font-family: ui-monospace, monospace; font-weight:600; }
        .card .hint { font-size:11.5px; color:#777; margin-top:4px; }
        .ok { color:#6fd39a; } .warn { color:#e8c76f; } .bad { color:#ef7f7f; } .info { color:#7fbcdc; }

        table { width:100%; border-collapse: collapse; font-size:13.5px; }
        th { text-align:left; color:#888; font-weight:500; font-size:10.5px; text-transform:uppercase; padding:8px 10px; border-bottom:1px solid #333; white-space:nowrap; }
        td { padding:9px 10px; border-bottom:1px solid #242424; }
        tr:last-child td { border-bottom:none; }
        td.n { font-family: ui-monospace, monospace; }
        .table-wrap { overflow-x:auto; background:#1c1c1c; border:1px solid #333; border-radius:10px; }
        .pill { display:inline-block; font-size:10.5px; font-family: ui-monospace, monospace; padding:2px 8px; border-radius:20px; background:#2a2a2a; color:#ccc; white-space:nowrap; }
        .pill.activa { background:#1c3a2a; color:#6fd39a; }
        .pill.aseo { background:#3a331c; color:#e8c76f; }
        .pill.ocupada-real { background:#1c2b3a; color:#7fbcdc; }
        .pill.fuera { background:#3a1c1c; color:#ef7f7f; }
        .muted { color:#777; font-size:12px; }
        a.day-link { color:#eee; text-decoration:none; } a.day-link:hover { color:#ff7918; }
        tr.current td { background:#221a12; }
        .note { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:12px 16px; color:#aaa; font-size:12.5px; line-height:1.55; margin-top:24px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    @php
        $tz = 'America/Santiago';
        $d = $report['date'];
        $s = $report['summary'];
        $hm = fn ($t) => $t ? $t->format('H:i') : '—';
        $dayName = fn ($date) => ucfirst($date->copy()->locale('es')->isoFormat('dddd D/MM'));
        $pillClass = fn (?string $status) => match ($status) { 'activa' => 'activa', 'aseo' => 'aseo', null => '', default => 'fuera' };
        $afterOpen = function ($t, $opens) {
            if (! $t || ! $opens) return null;
            $m = (int) $opens->diffInMinutes($t, false);
            return $m <= 0 ? 'antes de abrir' : \App\Support\TimeFormat::minutes($m).' después de abrir';
        };
    @endphp
    <div class="page-inner">
    @include('partials.aseo-tabs')

    <div class="topline">
        <div>
            <h1>Cierre del {{ $dayName($d) }}</h1>
        </div>
        <div class="nav-day">
            <a href="{{ route('aseo.closing', $d->copy()->subDay()->toDateString()) }}">← Día anterior</a>
            <a href="{{ route('aseo.closing', $d->copy()->addDay()->toDateString()) }}" class="{{ $d->gte($latest) ? 'off' : '' }}">Día siguiente →</a>
        </div>
    </div>
    <p class="sub">
        Cómo quedaron los Playrooms a las {{ \App\Services\Rooms\RoomClosingLog::CUT_TIME }} del {{ $report['cut']->copy()->timezone($tz)->locale('es')->isoFormat('dddd D/MM') }} (fin del día operativo) y a qué hora se fue habilitando cada uno después.
        @if ($report['motel_opens_at'])
            El motel abrió a las <b>{{ $report['motel_opens_at']->format('H:i') }}</b>.
        @else
            A esa hora el motel seguía abierto (tarifa corrida).
        @endif
        @if ($report['source'] === 'registro')
            <br><span class="muted">Este día no tiene foto: se reconstruyó con el registro de cambios del tablero.</span>
        @endif
    </p>

    <div class="cards">
        <div class="card"><div class="label">Listas al cierre</div><div class="value ok">{{ $s['ready'] }}</div><div class="hint">activas y libres</div></div>
        <div class="card"><div class="label">Quedaron en aseo</div><div class="value warn">{{ $s['aseo'] }}</div><div class="hint">{{ $s['pending_left'] ? $s['pending_left'].' sin habilitar después' : 'todas se habilitaron' }}</div></div>
        <div class="card"><div class="label">Ocupadas</div><div class="value info">{{ $s['occupied'] }}</div><div class="hint">con pasajeros adentro</div></div>
        <div class="card"><div class="label">Fuera de servicio</div><div class="value bad">{{ $s['out'] }}</div><div class="hint">mantención / inactiva / marcada ocupada</div></div>
        <div class="card"><div class="label">Primera habilitación</div><div class="value">{{ $hm($s['first_opened']) }}</div><div class="hint">{{ $afterOpen($s['first_opened'], $report['motel_opens_at']) ?? ' ' }}</div></div>
        <div class="card"><div class="label">Última habilitación</div><div class="value">{{ $hm($s['last_opened']) }}</div><div class="hint">{{ $s['opened'] }} Playroom(s) pasaron a lista</div></div>
    </div>

    <h2>Playroom por Playroom</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Playroom</th><th>Categoría</th><th>Al cierre</th><th>Se habilitó</th><th>Quién</th><th>Detalle</th></tr></thead>
        <tbody>
        @foreach ($report['rows'] as $r)
            @php $st = $r['state']; @endphp
            <tr>
                <td><b>{{ $r['room']->name }}</b></td>
                <td>{{ $r['room']->category?->name }}</td>
                <td>
                    @if ($st['occupied'])
                        <span class="pill ocupada-real">Con pasajeros</span>
                    @endif
                    @if ($st['status'])
                        <span class="pill {{ $pillClass($st['status']) }}">{{ $labels[$st['status']] ?? $st['status'] }}</span>
                    @else
                        <span class="muted">sin dato</span>
                    @endif
                    @if ($st['offered'] === false)<span class="pill">no se ofrecía</span>@endif
                </td>
                <td class="n">
                    @if ($r['opened_at'])
                        {{ $r['opened_at']->format('H:i') }}
                        @if ($r['opened_at']->toDateString() !== $report['cut']->copy()->timezone($tz)->toDateString())<span class="muted">({{ $r['opened_at']->format('d/m') }})</span>@endif
                    @elseif ($r['ready'])
                        <span class="muted">ya estaba lista</span>
                    @elseif ($st['occupied'] && $st['status'] === 'activa')
                        <span class="muted">seguía ocupada</span>
                    @else
                        <span class="muted">no se habilitó</span>
                    @endif
                </td>
                <td>{{ $r['opened_by'] ?? ($r['opened_at'] ? '—' : '') }}@if ($r['cleaned_by'])<br><span class="muted">aseo: {{ $r['cleaned_by'] }}</span>@endif</td>
                <td class="muted">
                    @if ($r['on_check_in']) se habilitó al ingresar un cliente @endif
                    @if ($r['opened_at'] && $report['motel_opens_at']) {{ $afterOpen($r['opened_at'], $report['motel_opens_at']) }} @endif
                    @if ($st['note']) {{ $st['note'] }} @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>

    @if ($report['toggles']->isNotEmpty())
        <h2>Pisos y categorías ese día</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Hora</th><th>Qué</th><th>Cambio</th><th>Quién</th></tr></thead>
            <tbody>
            @foreach ($report['toggles'] as $t)
                <tr><td class="n">{{ $t['at']->format('H:i') }}</td><td>{{ $t['label'] }}</td><td><span class="pill {{ $t['enabled'] ? 'activa' : 'fuera' }}">{{ $t['enabled'] ? 'Habilitado' : 'Deshabilitado' }}</span></td><td>{{ $t['by'] ?? '—' }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    @endif

    <h2>Historial diario</h2>
    @if ($history->isEmpty())
        <p class="muted">Todavía no hay días registrados.</p>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Día</th><th class="n">Listas</th><th class="n">En aseo</th><th class="n">Ocupadas</th><th class="n">Fuera</th><th>Abrió el motel</th><th>Primera habilitación</th><th>Última</th><th class="n">Habilitadas</th><th class="n">Aseo sin habilitar</th></tr></thead>
            <tbody>
            @foreach ($history as $h)
                <tr class="{{ $h['date']->isSameDay($d) ? 'current' : '' }}">
                    <td><a class="day-link" href="{{ route('aseo.closing', $h['date']->toDateString()) }}">{{ $dayName($h['date']) }}</a>@if ($h['source'] === 'registro') <span class="muted">*</span>@endif</td>
                    <td class="n ok">{{ $h['ready'] }}</td>
                    <td class="n warn">{{ $h['aseo'] }}</td>
                    <td class="n info">{{ $h['occupied'] }}</td>
                    <td class="n bad">{{ $h['out'] }}</td>
                    <td class="n">{{ $h['motel_opens_at'] ? $h['motel_opens_at']->format('H:i') : 'corrido' }}</td>
                    <td class="n">{{ $hm($h['first_opened']) }}</td>
                    <td class="n">{{ $hm($h['last_opened']) }}</td>
                    <td class="n">{{ $h['opened'] }}</td>
                    <td class="n {{ $h['pending_left'] ? 'bad' : '' }}">{{ $h['pending_left'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="muted">* Día reconstruido con el registro de cambios del tablero (antes de que existiera la foto diaria).</p>
    @endif

    <div class="note">
        El día operativo corta a las {{ \App\Services\Rooms\RoomClosingLog::CUT_TIME }}: lo que pasa en la madrugada cuenta para el día anterior. "Se habilitó" es la primera vez que el Playroom pasó a <b>lista</b> después del cierre (aseo listo desde el tablero, cambio de estado, o al ingresar un cliente). Los pisos y categorías que se prenden o apagan quedan registrados desde hoy.
    </div>
    </div>
</body>
</html>
