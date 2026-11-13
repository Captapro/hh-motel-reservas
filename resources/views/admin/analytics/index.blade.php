@extends('admin.layout')
@section('title', 'Analytics')
@section('content')
    @include('admin.analytics._styles')

    @php
        $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
        $metricVal = fn ($n) => $metric === 'revenue' ? $money($n) : number_format($n, 0, ',', '.');
        $qs = fn ($over) => array_merge(['preset' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null, 'metric' => $metric, 'category' => $categoryId], $over);
    @endphp

    @include('admin.analytics._nav', ['on' => 'analytics'])
    <h1>Analytics</h1>
    <p class="sub">{{ $rangeLabel }}{{ $categoryId ? ' · '.$categories->firstWhere('id', $categoryId)?->name : '' }}. Solo cuenta estadías con su pago completo (no canceladas, vencidas ni pendientes de pago).</p>

    <form class="flt" method="GET" action="{{ route('admin.analytics.index') }}">
        <div class="flt-row">
            <label>Desde</label>
            <input type="date" name="from" value="{{ $from }}">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ $to }}">
            <input type="hidden" name="category" value="{{ $categoryId }}">
            <button class="go" type="submit">Aplicar</button>
            @foreach (['today' => 'Hoy', '7d' => '7 días', 'this_month' => 'Este mes', 'last_month' => 'Mes pasado', '90d' => '90 días'] as $p => $pl)
                <a class="chip {{ $preset === $p ? 'active' : '' }}" href="{{ route('admin.analytics.index', ['preset' => $p, 'category' => $categoryId]) }}">{{ $pl }}</a>
            @endforeach
            @if ($isLive)
                <span class="live-note" title="Con un rango que incluye hoy, la página se vuelve a cargar sola cada minuto.">● En vivo · {{ $refreshedAt->format('H:i') }}</span>
            @endif

            <span style="flex:1"></span>

            <select onchange="location.href=this.value">
                <option value="{{ route('admin.analytics.index', $qs(['category' => null])) }}" @selected(! $categoryId)>Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ route('admin.analytics.index', $qs(['category' => $cat->id])) }}" @selected($categoryId === $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>

            <a class="export-btn" href="{{ route('admin.analytics.export', ['preset' => $preset, 'from' => $preset === 'custom' ? $from : null, 'to' => $preset === 'custom' ? $to : null, 'category' => $categoryId]) }}">Exportar a Excel/CSV ↓</a>
        </div>
    </form>
    <p class="range-help">Las fechas de arriba cambian todos los cuadros, salvo los marcados <span class="fixed-badge" style="margin-left:2px;">No cambia con las fechas</span></p>

    @if ($planilla)
        <p class="planilla-note">Del {{ $planilla['from']->format('d/m') }} al {{ $planilla['until']->format('d/m/Y') }} los números salen de la <b>planilla de recepción</b> (antes del sistema): suman en reservas, venta, ticket, días de la semana y mejores días. Lo demás (horas, piezas, canales, pagos, descuentos) solo existe en el sistema.</p>
    @endif
    <div class="kpis kpis-all">
        @php
            $kpiTips = [
                'reservas' => 'Estadías del período con su pago completo. No cuenta canceladas, vencidas ni pendientes de pago. El % compara con el período anterior a la misma altura: si el rango sigue en curso, hasta la misma hora (hoy vs ayer a esta hora; este mes vs los mismos días del mes pasado).',
                'venta' => 'Suma, por cada estadía, del precio final (ya con descuentos) más los extras pagados.',
                'ticket' => 'Venta total ÷ Reservas: lo que paga en promedio cada estadía, con extras incluidos.',
                'descuentos' => 'Suma de los descuentos aplicados a las reservas del período (cupones, ofertas, etc.).',
            ];
        @endphp
        @foreach (['reservas' => 'Reservas', 'venta' => 'Venta total', 'ticket' => 'Ticket promedio', 'descuentos' => 'Descuentos dados'] as $k => $lbl)
            @php $kp = $kpis[$k]; @endphp
            <div class="kpi" data-tip="{{ $kpiTips[$k] }}" tabindex="0">
                <div class="k-label">{{ $lbl }}</div>
                <div class="k-value">{{ $kp['is_money'] ? $money($kp['value']) : number_format($kp['value'], 0, ',', '.') }}</div>
                <div class="k-delta {{ $kp['delta'] === null ? 'flat' : ($kp['delta'] > 0 ? 'up' : ($kp['delta'] < 0 ? 'down' : 'flat')) }}">
                    @if ($kp['delta'] === null) sin período previo
                    @elseif ($kp['delta'] > 0) ▲ {{ $kp['delta'] }}% vs {{ $prevLabel }}
                    @elseif ($kp['delta'] < 0) ▼ {{ abs($kp['delta']) }}% vs {{ $prevLabel }}
                    @else = vs {{ $prevLabel }}
                    @endif
                </div>
            </div>
        @endforeach
        @foreach (['collected' => 'Cobrado', 'balance' => 'Saldo pendiente', 'addons' => 'Venta de extras'] as $key => $label)
            <div class="kpi" tabindex="0" data-tip="{{ ['collected' => 'Pagos aprobados que se recibieron por las estadías del período (efectivo, tarjeta, transferencia…).', 'balance' => 'Lo que falta por cobrar de las estadías del período que todavía no están pagadas completas (habitación + consumos − pagos). No cuenta canceladas, vencidas ni no-show. Estas reservas no suman a la venta hasta que se paguen.', 'addons' => 'Extras pagados (consumos y upsells) de las estadías del período; ya van dentro de la Venta total.'][$key] }}"><div class="k-label">{{ $label }}</div><div class="k-value">{{ $money($operations[$key]) }}</div><div class="k-delta flat">@if ($key === 'balance'){{ $operations['balance_count'] ? $operations['balance_count'].' '.($operations['balance_count'] === 1 ? 'reserva' : 'reservas').' con saldo' : 'Sin saldos' }}@else Periodo seleccionado @endif</div></div>
        @endforeach
        <div class="kpi" tabindex="0" data-tip="Clientes que hoy clasifican como «Frecuente» según las reglas de Clientes (visitas mínimas dentro de su ventana de análisis). No depende del rango elegido."><div class="k-label">Clientes por recurrencia</div><div class="k-value">{{ $segments['frecuente'] }}</div><div class="k-delta flat">Hoy, con todo su historial <span class="fixed-badge" title="Este cuadro no cambia con las fechas de arriba.">No cambia con las fechas</span></div></div>
    </div>

    <div class="proj" tabindex="0" data-tip="Proyección = venta de los días ya cerrados ÷ esos días × días del mes (el día de hoy entra cuando termina). Nunca baja de lo vendido más lo ya agendado para el resto del mes. Es la misma cifra del resumen mensual.">
        <h3>Proyección a fin de mes — {{ $projection['label'] }} <span class="fixed-badge" title="Siempre es el mes en curso, elijas las fechas que elijas.">No cambia con las fechas</span></h3>
        <div class="proj-grid">
            <div><span>Venta hasta hoy</span><b>{{ $money($projection['so_far']) }}</b></div>
            <div><span>Ya agendado (resto del mes)</span><b>{{ $money($projection['booked_rest']) }}</b></div>
            <div class="big"><span>Proyección fin de mes</span><b>{{ $money($projection['projected']) }}</b></div>
        </div>
        <p class="hint">@if ($projection['closed_days'] > 0)Ritmo: {{ $money($projection['per_day']) }}/día · {{ $projection['closed_days'] }} de {{ $projection['days_in_month'] }} días cerrados · según día de la semana: {{ $money($projection['by_weekday']) }}.@else Aparece mañana, con el primer día cerrado.@endif</p>
    </div>

    <h2 class="an-title">Cuándo se vende</h2>
    <div class="an-grid">
    <div class="card t-orange">
        <h3>Días de la semana ({{ $metricLabel }})</h3>
        <p class="chart-note">Suma de todos los lunes, martes, etc. del rango ({{ $rangeLabel }}), con el promedio de cada día.</p>
        @php
            $wdMaxAvg = max(1, $byWeekday->max(fn ($e) => $e['value'] / max(1, count($weekdayDates[$e['label']] ?? []))));
        @endphp
        <table class="rank-table wd-table">
            <thead><tr><th>Día</th><th class="n">Total</th><th class="n">Prom./día</th></tr></thead>
            <tbody>
            @foreach ($byWeekday as $e)
                @php $wdDates = $weekdayDates[$e['label']] ?? []; $avg = (int) round($e['value'] / max(1, count($wdDates))); @endphp
                <tr>
                    <td class="nm">{{ $e['label'] }}<span class="sub">@if (! $wdDates)sin fechas en el rango @elseif (count($wdDates) > 5){{ count($wdDates) }} días, del {{ $wdDates[0] }} al {{ end($wdDates) }}@else{{ implode(', ', $wdDates) }}@endif</span></td>
                    <td class="n">{{ $metricVal($e['value']) }}@if ($e['prev'] > 0)<span class="sub">ant. {{ $metricVal($e['prev']) }}</span>@endif</td>
                    <td class="n pct">{{ $metricVal($avg) }}<div class="mini"><i style="width:{{ round($avg / $wdMaxAvg * 100) }}%"></i></div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card t-orange">
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

    <div class="card t-orange">
        <h3>Mejores días del rango ({{ $metricLabel }})</h3>
        @php $maxTd = max(1, $topDays->max('value')); @endphp
        @forelse ($topDays as $d)
            <div class="bar-row">
                <div class="name">{{ ucfirst($d['date']->locale('es')->isoFormat('ddd D/MM')) }}</div>
                <div class="bar-track"><div class="bar-fill alt" style="width: {{ round($d['value'] / $maxTd * 100) }}%;"></div></div>
                <div class="val">{{ $metricVal($d['value']) }} <small>· {{ $d['count'] }} {{ Str::plural('reserva', $d['count']) }}</small></div>
            </div>
        @empty
            <p class="empty">Sin reservas en el rango.</p>
        @endforelse
    </div>
    </div>

    <h2 class="an-title">Qué se vende</h2>
    <div class="an-grid">
    <div class="card t-green">
        <h3>Categorías preferidas (reservas)</h3>
        @php $maxC = max(1, $byCategory->max('count')); @endphp
        @forelse ($byCategory as $c)
            <div class="bar-row">
                <div>
                    <div class="name">{{ $c['label'] }}</div>
                    <div class="cat">{{ round($c['share'] * 100) }}% de las reservas</div>
                </div>
                <div class="bar-track"><div class="bar-fill" style="width: {{ round($c['count'] / $maxC * 100) }}%;"></div></div>
                <div class="val">{{ $c['count'] }} {{ Str::plural('reserva', $c['count']) }}
                    <small>· {{ $money($c['value']) }}@if ($c['prev'] > 0) · <span style="color:{{ $c['count'] >= $c['prev'] ? '#1f9d55' : '#d64545' }};">{{ $c['count'] >= $c['prev'] ? '▲' : '▼' }} antes {{ $c['prev'] }}</span>@endif</small>
                </div>
            </div>
        @empty
            <p class="empty">Sin reservas en el rango.</p>
        @endforelse
    </div>

    @php
        // Tabla de ranking compacta: posición, nombre, cantidad, venta y % del total con barra fina.
        // Las 10 primeras a la vista; el resto en "Ver más" (antes era una lista de barras muy larga).
        $rankRows = function ($rows) {
            $total = max(1, $rows->sum('value'));
            return $rows->values()->map(fn ($r, $i) => $r + ['pos' => $i + 1, 'share' => $r['value'] / $total]);
        };
        $roomRows = $rankRows($byRoom);
        $extraRows = $rankRows($extras->map(fn ($e, $name) => ['label' => $name, 'sub' => null, 'count' => $e['quantity'], 'value' => $e['value']]));
    @endphp
    @foreach ([['Playrooms ('.$metricLabel.')', $roomRows, 'Reservas', 'Sin reservas en el rango.'], ['Extras vendidos', $extraRows, 'Unid.', 'Sin extras vendidos en el periodo.']] as [$title, $rows, $countLabel, $emptyText])
        <div class="card t-green">
            <h3>{{ $title }}</h3>
            @if ($rows->isEmpty())
                <p class="empty">{{ $emptyText }}</p>
            @else
                @php $totalValue = $rows->sum('value'); $totalCount = $rows->sum('count'); @endphp
                @php
                    $top = $rows->take(10); $rest = $rows->slice(10);
                    $firstShare = max(0.0001, $rows->first()['share']);
                    $rowHtml = fn ($r) => '<tr><td class="pos">'.$r['pos'].'</td><td class="nm" title="'.e($r['label']).'">'.e($r['label']).($r['sub'] ? '<span class="sub">'.e($r['sub']).'</span>' : '').'</td><td class="n">'.$r['count'].'</td><td class="n">'.e($metricVal($r['value'])).'</td><td class="n pct">'.number_format($r['share'] * 100, 0).'%<div class="mini"><i style="width:'.round($r['share'] / $firstShare * 100).'%"></i></div></td></tr>';
                @endphp
                <table class="rank-table">
                    <thead><tr><th>#</th><th>Nombre</th><th class="n">{{ $countLabel }}</th><th class="n">Venta</th><th class="n">%</th></tr></thead>
                    <tbody>{!! $top->map($rowHtml)->implode('') !!}</tbody>
                </table>
                @if ($rest->isNotEmpty())
                    <details class="rank-more">
                        <summary>Ver {{ $rest->count() }} más</summary>
                        <table class="rank-table"><tbody>{!! $rest->map($rowHtml)->implode('') !!}</tbody></table>
                    </details>
                @endif
                <table class="rank-table"><tfoot><tr><td class="pos"></td><td>Total</td><td class="n">{{ $totalCount }}</td><td class="n">{{ $metricVal($totalValue) }}</td><td class="n">100%</td></tr></tfoot></table>
            @endif
        </div>
    @endforeach

    <div class="card t-green">
        <h3>Estado de las reservas</h3>
        @foreach ($bookingStatuses as $status => $count)<div class="bar-row"><div class="name">{{ str_replace('_',' ', $status) }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ $kpis['reservas']['value'] ? round($count/$kpis['reservas']['value']*100) : 0 }}%"></div></div><div class="val">{{ $count }}</div></div>@endforeach
    </div>
    </div>

    <h2 class="an-title">Cómo llegan los clientes</h2>
    <p class="range-help" style="margin-top:0;">Ahora tiene su propia sección: <a href="{{ route('admin.analytics.channels', $preset === 'custom' ? ['from' => $from, 'to' => $to] : ['preset' => $preset]) }}">ver Cómo llegan →</a> (en línea, asistida o presencial, y ventas por canal y anuncio).</p>

    <h2 class="an-title">Cobros y evolución</h2>
    <div class="an-grid">
    <div class="card t-blue">
        <h3>Medios de pago cobrados</h3>
        @forelse ($paymentMethods as $method => $amount)<div class="bar-row"><div class="name">{{ $method }}</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $paymentMethods->max() ? round($amount/$paymentMethods->max()*100) : 0 }}%"></div></div><div class="val">{{ $money($amount) }}</div></div>@empty<p class="empty">Sin pagos aprobados en el rango.</p>@endforelse
    </div>

    <div class="card t-blue span2">
        <h3>Por mes — últimos 12 ({{ $metricLabel }}) <span class="fixed-badge" title="Siempre muestra los últimos 12 meses.">No cambia con las fechas</span></h3>
        @if ($monthly['rows']->contains(fn ($m) => ($m['source'] ?? '') === 'planilla'))
            <p class="chart-note">Los meses antes del sistema vienen de la planilla histórica cargada.</p>
        @endif
        @if ($monthly['rows']->isNotEmpty())
            @php
                $mW = 720; $mH = 200; $mPadL = 46; $mPadR = 10; $mPadT = 14; $mPadB = 24;
                $mPlotW = $mW - $mPadL - $mPadR; $mPlotH = $mH - $mPadT - $mPadB;
                $monthlyArr = $monthly['rows']->values();
                $mCount = max(1, $monthlyArr->count() - 1);
                // Eje con cifras redondas ($5 M, $10 M, $15 M) en vez de tercios del máximo.
                $mRaw = max(1, $monthly['max']) / 3;
                $mMag = 10 ** floor(log10($mRaw));
                $mStep = collect([1, 2, 2.5, 5, 10])->map(fn ($k) => $k * $mMag)->first(fn ($s) => $s >= $mRaw);
                $mAxisMax = $mStep * max(1, (int) ceil(max(1, $monthly['max']) * 1.05 / $mStep));
                $mShort = fn ($n) => $n >= 1000000 ? '$'.rtrim(rtrim(number_format($n / 1000000, 1, ',', '.'), '0'), ',').' M' : ($n >= 1000 ? '$'.number_format($n / 1000, 0, ',', '.').' mil' : '$'.number_format($n, 0, ',', '.'));
                $mX = fn ($i) => $mPadL + ($i / $mCount) * $mPlotW;
                $mY = fn ($v) => $mPadT + $mPlotH - ($v / $mAxisMax) * $mPlotH;
                $mColWidth = $mPlotW / max(1, $monthlyArr->count());
                $mPoints = $monthlyArr->map(fn ($row, $i) => ['x' => $mX($i), 'y' => $mY($row['value']), 'row' => $row]);
                $mLinePath = $mPoints->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').round($p['x'], 1).','.round($p['y'], 1))->implode(' ');
                $mAreaPath = $mLinePath.' L'.round($mPoints->last()['x'], 1).','.($mPadT + $mPlotH).' L'.round($mPoints->first()['x'], 1).','.($mPadT + $mPlotH).' Z';
                $mTicks = collect(range(0, (int) round($mAxisMax / $mStep)))->map(fn ($k) => ['v' => $k * $mStep, 'y' => $mPadT + $mPlotH - ($k * $mStep / $mAxisMax) * $mPlotH]);
            @endphp
            <div class="svg-chart-wrap">
                <svg viewBox="0 0 {{ $mW }} {{ $mH }}" preserveAspectRatio="none" role="img" aria-label="Evolución mensual de {{ $metricLabel }}">
                    <g class="chart-grid">
                        @foreach ($mTicks as $tick)
                            <line x1="{{ $mPadL }}" y1="{{ $tick['y'] }}" x2="{{ $mW - $mPadR }}" y2="{{ $tick['y'] }}" />
                            <text class="chart-axis-label" x="{{ $mPadL - 6 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $mShort((int) $tick['v']) }}</text>
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
                              data-rows="{{ $metricLabel }}:{{ $metricVal($p['row']['value']) }}|{{ ($p['row']['source'] ?? '') === 'planilla' ? 'Estadías (planilla)' : 'Reservas' }}:{{ $p['row']['count'] }}{{ $p['row']['delta_pct'] !== null ? '|vs mes anterior:'.($p['row']['delta_pct'] > 0 ? '+' : '').$p['row']['delta_pct'].'%' : '' }}"
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
                            <td>{{ $m['label'] }} <span style="color:#555;">· {{ $m['count'] }} {{ ($m['source'] ?? '') === 'planilla' ? Str::plural('estadía', $m['count']).' · planilla' : Str::plural('reserva', $m['count']) }}</span></td>
                            <td class="v">{{ $metricVal($m['value']) }}</td>
                            <td class="v" style="width:70px; color:{{ $m['delta_pct'] === null ? '#777' : ($m['delta_pct'] > 0 ? '#2563eb' : ($m['delta_pct'] < 0 ? '#dc2626' : '#777')) }};">
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
                    // Que nunca se salga de la tarjeta: si se corta por un lado o por arriba, se corre.
                    var box = tooltip.getBoundingClientRect(), area = wrap.getBoundingClientRect();
                    var dx = box.left < area.left ? area.left - box.left : (box.right > area.right ? area.right - box.right : 0);
                    var dy = box.top < area.top ? area.top - box.top : 0;
                    if (dx || dy) tooltip.style.transform = 'translate(calc(' + anchorX + '% + ' + dx + 'px), calc(-115% + ' + dy + 'px))';
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

        @if ($isLive)
        // Si el rango incluye hoy, recarga sola cada minuto para mostrar las ventas nuevas.
        // Espera si la persona está escribiendo una fecha o la pestaña está escondida.
        setInterval(function () {
            var el = document.activeElement;
            if (document.hidden || (el && (el.tagName === 'INPUT' || el.tagName === 'SELECT'))) return;
            location.reload();
        }, 60000);
        @endif
    </script>
@endsection
