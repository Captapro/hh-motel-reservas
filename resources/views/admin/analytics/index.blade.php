@extends('admin.layout')
@section('title', 'Analytics')
@section('content')
    <style>
        /* La grilla usa todo el ancho disponible (antes quedaba fija en
           1500px pegada a la izquierda, dejando un hueco enorme en
           monitores anchos). auto-fit reparte 2, 3 o más columnas según
           el espacio real en vez de un 50%/50% fijo. */
        .wrap { max-width:1900px !important; margin:0 auto !important; display:grid; grid-template-columns:repeat(auto-fit, minmax(440px, 1fr)); gap:16px; align-items:start; }
        .wrap > h1, .wrap > .sub, .wrap > .errors { grid-column:1 / -1; }
        .wrap > .card { margin-bottom:0; }
        .wrap > .card:nth-of-type(odd) { border-top:3px solid #ff7918; }
        .wrap > .card:nth-of-type(even) { border-top:3px solid #6fd39a; }
        .wrap > .flt, .wrap > .proj, .wrap > .kpis { grid-column:1 / -1; }
        @media(max-width:850px){ .wrap { grid-template-columns:1fr; } }
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
    </style>

    @php
        $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
        $metricVal = fn ($n) => $metric === 'revenue' ? $money($n) : number_format($n, 0, ',', '.');
        $qs = fn ($over) => array_merge(['preset' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null, 'metric' => $metric, 'category' => $categoryId], $over);
    @endphp

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px;">
        <a href="{{ route('sales.daily') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Venta de hoy</a>
        <a href="{{ route('sales.monthly') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Resumen mensual</a>
        <a href="{{ route('admin.analytics.index') }}" style="padding:7px 14px;border-radius:7px;background:#ff7918;color:#111;font-weight:600;font-size:13px;text-decoration:none;">Analytics</a>
    </div>
    <h1>Analytics</h1>
    <p class="sub">{{ $rangeLabel }} · viendo por <b>{{ $metricLabel }}</b>{{ $categoryId ? ' · '.$categories->firstWhere('id', $categoryId)?->name : '' }}. No cuenta reservas canceladas.</p>

    <form class="flt" method="GET" action="{{ route('admin.analytics.index') }}">
        <div class="flt-row">
            @foreach (['today' => 'Hoy', 'week' => 'Semana', 'this_month' => 'Mes', '7d' => '7 días', '30d' => '30 días', '90d' => '90 días', 'last_month' => 'Mes pasado', 'this_year' => 'Este año', 'all' => 'Todo'] as $k => $lbl)
                <a class="chip {{ $preset === $k ? 'active' : '' }}" href="{{ route('admin.analytics.index', $qs(['preset' => $k, 'from' => null, 'to' => null])) }}">{{ $lbl }}</a>
            @endforeach
        </div>
        <div class="flt-row">
            <label>Desde</label>
            <input type="date" name="from" value="{{ $preset === 'custom' ? $from : '' }}">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ $preset === 'custom' ? $to : '' }}">
            <input type="hidden" name="metric" value="{{ $metric }}">
            <input type="hidden" name="category" value="{{ $categoryId }}">
            <button class="go" type="submit">Aplicar</button>

            <span style="flex:1"></span>

            <span class="metric-toggle">
                <a class="{{ $metric === 'revenue' ? 'on' : '' }}" href="{{ route('admin.analytics.index', $qs(['metric' => 'revenue'])) }}">Venta</a>
                <a class="{{ $metric === 'count' ? 'on' : '' }}" href="{{ route('admin.analytics.index', $qs(['metric' => 'count'])) }}">Reservas</a>
            </span>

            <select onchange="location.href=this.value">
                <option value="{{ route('admin.analytics.index', $qs(['category' => null])) }}" @selected(! $categoryId)>Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ route('admin.analytics.index', $qs(['category' => $cat->id])) }}" @selected($categoryId === $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>

            <a class="export-btn" href="{{ route('admin.analytics.export', ['preset' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null, 'category' => $categoryId]) }}">Exportar a Excel/CSV ↓</a>
        </div>
    </form>

    <div class="kpis">
        @foreach (['reservas' => 'Reservas', 'venta' => 'Venta total', 'ticket' => 'Ticket promedio', 'descuentos' => 'Descuentos dados'] as $k => $lbl)
            @php $kp = $kpis[$k]; @endphp
            <div class="kpi">
                <div class="k-label">{{ $lbl }}</div>
                <div class="k-value">{{ $kp['is_money'] ? $money($kp['value']) : number_format($kp['value'], 0, ',', '.') }}</div>
                <div class="k-delta {{ $kp['delta'] === null ? 'flat' : ($kp['delta'] > 0 ? 'up' : ($kp['delta'] < 0 ? 'down' : 'flat')) }}">
                    @if ($kp['delta'] === null) sin período previo
                    @elseif ($kp['delta'] > 0) ▲ {{ $kp['delta'] }}% vs período anterior
                    @elseif ($kp['delta'] < 0) ▼ {{ abs($kp['delta']) }}% vs período anterior
                    @else = vs período anterior
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="proj">
        <h3>Proyección a fin de mes — {{ $projection['label'] }}</h3>
        <div class="proj-grid">
            <div><span>Venta hasta hoy</span><b>{{ $money($projection['so_far']) }}</b></div>
            <div><span>Ya agendado (resto del mes)</span><b>{{ $money($projection['booked_rest']) }}</b></div>
            <div class="big"><span>Proyección fin de mes</span><b>{{ $money($projection['projected']) }}</b></div>
        </div>
        <p class="hint">Ritmo actual: {{ $money((int) round($projection['so_far'] / $projection['days_elapsed'])) }}/día · día {{ $projection['days_elapsed'] }} de {{ $projection['days_in_month'] }}.</p>
    </div>

    <div class="kpis">
        @foreach (['collected' => 'Cobrado', 'balance' => 'Saldo pendiente', 'addons' => 'Venta de extras'] as $key => $label)
            <div class="kpi"><div class="k-label">{{ $label }}</div><div class="k-value">{{ $money($operations[$key]) }}</div><div class="k-delta flat">Periodo seleccionado</div></div>
        @endforeach
        <div class="kpi"><div class="k-label">Clientes por recurrencia</div><div class="k-value">{{ $segments['frecuente'] }}</div><div class="k-delta flat">Alta recurrencia</div></div>
    </div>

    <div class="card">
        <h3>Estado de las reservas</h3>
        @foreach ($bookingStatuses as $status => $count)<div class="bar-row"><div class="name">{{ str_replace('_',' ', $status) }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ $kpis['reservas']['value'] ? round($count/$kpis['reservas']['value']*100) : 0 }}%"></div></div><div class="val">{{ $count }}</div></div>@endforeach
    </div>

    <div class="card">
        <h3>Medios de pago cobrados</h3>
        @forelse ($paymentMethods as $method => $amount)<div class="bar-row"><div class="name">{{ $method }}</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $paymentMethods->max() ? round($amount/$paymentMethods->max()*100) : 0 }}%"></div></div><div class="val">{{ $money($amount) }}</div></div>@empty<p class="empty">Sin pagos aprobados en el rango.</p>@endforelse
    </div>

    <div class="card">
        <h3>Extras vendidos</h3>
        @forelse ($extras as $description => $extra)
            <div class="bar-row"><div class="name">{{ $description }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ $extras->max('value') ? round($extra['value']/$extras->max('value')*100) : 0 }}%"></div></div><div class="val">{{ $money($extra['value']) }} <small>· {{ $extra['quantity'] }} u.</small></div></div>
        @empty
            <p class="empty">Sin extras vendidos en el periodo.</p>
        @endforelse
    </div>

    <div class="card">
        <h3>Por mes — últimos 12 ({{ $metricLabel }})</h3>
        @if ($monthly['rows']->isNotEmpty())
            @php
                $mW = 720; $mH = 200; $mPadL = 46; $mPadR = 10; $mPadT = 14; $mPadB = 24;
                $mPlotW = $mW - $mPadL - $mPadR; $mPlotH = $mH - $mPadT - $mPadB;
                $monthlyArr = $monthly['rows']->values();
                $mCount = max(1, $monthlyArr->count() - 1);
                $mAxisMax = max(1, $monthly['max'] * 1.15);
                $mX = fn ($i) => $mPadL + ($i / $mCount) * $mPlotW;
                $mY = fn ($v) => $mPadT + $mPlotH - ($v / $mAxisMax) * $mPlotH;
                $mColWidth = $mPlotW / max(1, $monthlyArr->count());
                $mPoints = $monthlyArr->map(fn ($row, $i) => ['x' => $mX($i), 'y' => $mY($row['value']), 'row' => $row]);
                $mLinePath = $mPoints->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').round($p['x'], 1).','.round($p['y'], 1))->implode(' ');
                $mAreaPath = $mLinePath.' L'.round($mPoints->last()['x'], 1).','.($mPadT + $mPlotH).' L'.round($mPoints->first()['x'], 1).','.($mPadT + $mPlotH).' Z';
                $mTicks = collect([1, 0.66, 0.33, 0])->map(fn ($f) => ['v' => $mAxisMax * $f, 'y' => $mPadT + $mPlotH - $f * $mPlotH]);
            @endphp
            <div class="svg-chart-wrap">
                <svg viewBox="0 0 {{ $mW }} {{ $mH }}" preserveAspectRatio="none" role="img" aria-label="Evolución mensual de {{ $metricLabel }}">
                    <g class="chart-grid">
                        @foreach ($mTicks as $tick)
                            <line x1="{{ $mPadL }}" y1="{{ $tick['y'] }}" x2="{{ $mW - $mPadR }}" y2="{{ $tick['y'] }}" />
                            <text class="chart-axis-label" x="{{ $mPadL - 6 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $metricVal((int) $tick['v']) }}</text>
                        @endforeach
                    </g>
                    <path class="chart-area" d="{{ $mAreaPath }}" fill="#ff7918"></path>
                    <path class="chart-line" d="{{ $mLinePath }}" stroke="#ff7918"></path>
                    <line class="chart-crosshair" x1="0" y1="{{ $mPadT }}" x2="0" y2="{{ $mPadT + $mPlotH }}"></line>
                    @foreach ($mPoints as $i => $p)
                        <circle class="chart-point" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.2" stroke="#ff7918"></circle>
                        <text class="chart-xlabel" x="{{ $p['x'] }}" y="{{ $mH - 6 }}">{{ explode(' ', $p['row']['label'])[0] }}</text>
                        <rect class="chart-hit" tabindex="0"
                              data-title="{{ $p['row']['label'] }}"
                              data-rows="{{ $metricLabel }}:{{ $metricVal($p['row']['value']) }}|Reservas:{{ $p['row']['count'] }}{{ $p['row']['delta_pct'] !== null ? '|vs mes anterior:'.($p['row']['delta_pct'] > 0 ? '+' : '').$p['row']['delta_pct'].'%' : '' }}"
                              data-cx="{{ round($p['x'] / $mW * 100, 2) }}" data-cy="{{ round($p['y'] / $mH * 100, 2) }}" data-crosshair-x="{{ round($p['x'], 1) }}"
                              x="{{ round($p['x'] - $mColWidth / 2, 1) }}" y="{{ $mPadT }}" width="{{ round($mColWidth, 1) }}" height="{{ $mPlotH }}"></rect>
                    @endforeach
                </svg>
                <div class="chart-tooltip"></div>
            </div>
            <details class="chart-detail">
                <summary>Ver detalle mes a mes</summary>
                <table class="chart-table">
                    @foreach ($monthlyArr as $m)
                        <tr>
                            <td>{{ $m['label'] }} <span style="color:#555;">· {{ $m['count'] }} {{ Str::plural('reserva', $m['count']) }}</span></td>
                            <td class="v">{{ $metricVal($m['value']) }}</td>
                            <td class="v" style="width:70px; color:{{ $m['delta_pct'] === null ? '#777' : ($m['delta_pct'] > 0 ? '#6fd39a' : ($m['delta_pct'] < 0 ? '#e88a9a' : '#777')) }};">
                                @if ($m['delta_pct'] === null) —
                                @elseif ($m['delta_pct'] > 0) ▲{{ $m['delta_pct'] }}%
                                @elseif ($m['delta_pct'] < 0) ▼{{ abs($m['delta_pct']) }}%
                                @else 0%
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </details>
        @else
            <p class="empty">Sin datos.</p>
        @endif
    </div>

    <div class="card">
        <h3>Días de la semana ({{ $metricLabel }})</h3>
        @php
            $maxWd = max(1, $byWeekday->max(fn ($e) => max($e['value'], $e['prev'])));
            $hasPrevWd = $byWeekday->sum('prev') > 0;
        @endphp
        <div class="chart-legend-row">
            <span><span class="sw"></span>Período actual</span>
            @if ($hasPrevWd)<span><span class="sw prev"></span>Período anterior</span>@endif
        </div>
        @php
            $wW = 720; $wH = 200; $wPadL = 46; $wPadR = 10; $wPadT = 14; $wPadB = 24;
            $wPlotW = $wW - $wPadL - $wPadR; $wPlotH = $wH - $wPadT - $wPadB;
            $weekdayArr = $byWeekday->values();
            $wCount = max(1, $weekdayArr->count() - 1);
            $wAxisMax = max(1, $maxWd * 1.15);
            $wX = fn ($i) => $wPadL + ($i / $wCount) * $wPlotW;
            $wY = fn ($v) => $wPadT + $wPlotH - ($v / $wAxisMax) * $wPlotH;
            $wColWidth = $wPlotW / max(1, $weekdayArr->count());
            $wPointsCur = $weekdayArr->map(fn ($e, $i) => ['x' => $wX($i), 'y' => $wY($e['value']), 'e' => $e]);
            $wLineCur = $wPointsCur->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').round($p['x'], 1).','.round($p['y'], 1))->implode(' ');
            $wLinePrev = $weekdayArr->map(fn ($e, $i) => ($i === 0 ? 'M' : 'L').round($wX($i), 1).','.round($wY($e['prev']), 1))->implode(' ');
            $wTicks = collect([1, 0.66, 0.33, 0])->map(fn ($f) => ['v' => $wAxisMax * $f, 'y' => $wPadT + $wPlotH - $f * $wPlotH]);
        @endphp
        <div class="svg-chart-wrap">
            <svg viewBox="0 0 {{ $wW }} {{ $wH }}" preserveAspectRatio="none" role="img" aria-label="Ventas por día de la semana, {{ $metricLabel }}">
                <g class="chart-grid">
                    @foreach ($wTicks as $tick)
                        <line x1="{{ $wPadL }}" y1="{{ $tick['y'] }}" x2="{{ $wW - $wPadR }}" y2="{{ $tick['y'] }}" />
                        <text class="chart-axis-label" x="{{ $wPadL - 6 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $metricVal((int) $tick['v']) }}</text>
                    @endforeach
                </g>
                @if ($hasPrevWd)
                    <path class="chart-line prev" d="{{ $wLinePrev }}" stroke="#888"></path>
                @endif
                <path class="chart-line" d="{{ $wLineCur }}" stroke="#ff7918"></path>
                <line class="chart-crosshair" x1="0" y1="{{ $wPadT }}" x2="0" y2="{{ $wPadT + $wPlotH }}"></line>
                @foreach ($wPointsCur as $i => $p)
                    @if ($hasPrevWd)
                        <circle class="chart-point" cx="{{ $wX($i) }}" cy="{{ $wY($p['e']['prev']) }}" r="2.6" stroke="#888"></circle>
                    @endif
                    <circle class="chart-point" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.2" stroke="#ff7918"></circle>
                    <text class="chart-xlabel" x="{{ $p['x'] }}" y="{{ $wH - 6 }}">{{ mb_substr($p['e']['label'], 0, 3) }}</text>
                    <rect class="chart-hit" tabindex="0"
                          data-title="{{ $p['e']['label'] }}"
                          data-rows="Actual:{{ $metricVal($p['e']['value']) }}{{ $p['e']['prev'] > 0 ? '|Anterior:'.$metricVal($p['e']['prev']) : '' }}"
                          data-cx="{{ round($p['x'] / $wW * 100, 2) }}" data-cy="{{ round(min($p['y'], $wY($p['e']['prev'])) / $wH * 100, 2) }}" data-crosshair-x="{{ round($p['x'], 1) }}"
                          x="{{ round($p['x'] - $wColWidth / 2, 1) }}" y="{{ $wPadT }}" width="{{ round($wColWidth, 1) }}" height="{{ $wPlotH }}"></rect>
                @endforeach
            </svg>
            <div class="chart-tooltip"></div>
        </div>
        <details class="chart-detail">
            <summary>Ver detalle por día</summary>
            <table class="chart-table">
                @foreach ($byWeekday as $e)
                    <tr>
                        <td>{{ $e['label'] }}</td>
                        <td class="v">{{ $metricVal($e['value']) }}</td>
                        <td class="v" style="color:#888;">{{ $e['prev'] > 0 ? 'ant. '.$metricVal($e['prev']) : '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </details>
    </div>

    <div class="card">
        <h3>Mejores días del rango ({{ $metricLabel }})</h3>
        @php $maxTd = max(1, $topDays->max('value')); @endphp
        @forelse ($topDays as $d)
            <div class="bar-row">
                <div class="name">{{ ucfirst($d['date']->locale('es')->isoFormat('ddd D/MM')) }}</div>
                <div class="bar-track"><div class="bar-fill alt" style="width: {{ round($d['value'] / $maxTd * 100) }}%;"></div></div>
                <div class="val">{{ $metricVal($d['value']) }} <small>· {{ $d['count'] }}r</small></div>
            </div>
        @empty
            <p class="empty">Sin reservas en el rango.</p>
        @endforelse
    </div>

    <div class="card">
        <h3>Ventas por hora de inicio ({{ $metricLabel }})</h3>
        @php $maxH = max(1, $byHour->max('value')); @endphp
        @if ($byHour->sum('value') > 0)
            <div class="hour-grid">
                @foreach ($byHour as $e)
                    <div class="hour-cell">
                        <div class="hour-track"><div class="hour-fill" style="height: {{ round($e['value'] / $maxH * 100) }}%;"></div></div>
                        <div class="hour-label">{{ str_pad($e['key'], 2, '0', STR_PAD_LEFT) }}h</div>
                        <div class="hour-count">{{ $metric === 'revenue' ? ($e['value'] > 0 ? '$'.round($e['value']/1000).'k' : '') : $e['value'] }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="empty">Sin reservas en el rango.</p>
        @endif
    </div>

    <div class="card">
        <h3>Playrooms ({{ $metricLabel }})</h3>
        @php $maxR = max(1, $byRoom->max('value')); @endphp
        @forelse ($byRoom as $e)
            <div class="bar-row">
                <div>
                    <div class="name">{{ $e['label'] }}</div>
                    <div class="cat">{{ $e['sub'] }}</div>
                </div>
                <div class="bar-track"><div class="bar-fill" style="width: {{ round($e['value'] / $maxR * 100) }}%;"></div></div>
                <div class="val">{{ $metricVal($e['value']) }} <small>· {{ $e['count'] }}r</small></div>
            </div>
        @empty
            <p class="empty">Sin reservas en el rango.</p>
        @endforelse
    </div>

    <script>
        // Tooltip + crosshair genérico para los gráficos de línea SVG de
        // arriba -- cada rect .chart-hit trae su contenido en data-title/
        // data-rows ("Label:Valor|Label:Valor") para no tener que armar
        // HTML crudo desde Blade.
        document.querySelectorAll('.svg-chart-wrap').forEach(function (wrap) {
            var tooltip = wrap.querySelector('.chart-tooltip');
            var crosshair = wrap.querySelector('.chart-crosshair');
            wrap.querySelectorAll('.chart-hit').forEach(function (hit) {
                var show = function () {
                    var rows = hit.getAttribute('data-rows').split('|').map(function (r) {
                        var parts = r.split(':');
                        return '<div class="tt-row"><span>' + parts[0] + '</span><span>' + parts[1] + '</span></div>';
                    }).join('');
                    tooltip.innerHTML = '<b>' + hit.getAttribute('data-title') + '</b>' + rows;
                    var cx = parseFloat(hit.getAttribute('data-cx'));
                    // Cerca de un borde, ancla el tooltip a ese lado en vez de
                    // centrarlo -- si no, se corta contra el borde de la tarjeta.
                    var anchorX = cx > 82 ? -92 : (cx < 18 ? -8 : -50);
                    tooltip.style.left = cx + '%';
                    tooltip.style.top = hit.getAttribute('data-cy') + '%';
                    tooltip.style.transform = 'translate(' + anchorX + '%, -115%)';
                    tooltip.classList.add('show');
                    if (crosshair) {
                        crosshair.setAttribute('x1', hit.getAttribute('data-crosshair-x'));
                        crosshair.setAttribute('x2', hit.getAttribute('data-crosshair-x'));
                        crosshair.style.opacity = 1;
                    }
                };
                var hide = function () {
                    tooltip.classList.remove('show');
                    if (crosshair) crosshair.style.opacity = 0;
                };
                hit.addEventListener('mouseenter', show);
                hit.addEventListener('mouseleave', hide);
                hit.addEventListener('focus', show);
                hit.addEventListener('blur', hide);
            });
        });
    </script>
@endsection
