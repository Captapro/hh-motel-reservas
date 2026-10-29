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
            @if ($preset === 'custom')
                <a class="chip" href="{{ route('admin.analytics.index', ['category' => $categoryId]) }}">Volver al mes actual</a>
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

    <div class="kpis kpis-all">
        @php
            $kpiTips = [
                'reservas' => 'Estadías del período con su pago completo. No cuenta canceladas, vencidas ni pendientes de pago. El % compara con el período anterior de igual largo.',
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
                    @elseif ($kp['delta'] > 0) ▲ {{ $kp['delta'] }}% vs período anterior
                    @elseif ($kp['delta'] < 0) ▼ {{ abs($kp['delta']) }}% vs período anterior
                    @else = vs período anterior
                    @endif
                </div>
            </div>
        @endforeach
        @foreach (['collected' => 'Cobrado', 'balance' => 'Saldo pendiente', 'addons' => 'Venta de extras'] as $key => $label)
            <div class="kpi" tabindex="0" data-tip="{{ ['collected' => 'Pagos aprobados que se recibieron por las estadías del período (efectivo, tarjeta, transferencia…).', 'balance' => 'Venta total − Cobrado (nunca menos de 0): lo que falta por pagar de esas estadías.', 'addons' => 'Extras pagados (consumos y upsells) de las estadías del período; ya van dentro de la Venta total.'][$key] }}"><div class="k-label">{{ $label }}</div><div class="k-value">{{ $money($operations[$key]) }}</div><div class="k-delta flat">Periodo seleccionado</div></div>
        @endforeach
        <div class="kpi" tabindex="0" data-tip="Clientes que hoy clasifican como «Frecuente» según las reglas de Clientes (visitas mínimas dentro de su ventana de análisis). No depende del rango elegido."><div class="k-label">Clientes por recurrencia</div><div class="k-value">{{ $segments['frecuente'] }}</div><div class="k-delta flat">Alta recurrencia</div></div>
    </div>

    <div class="proj" tabindex="0" data-tip="Proyección = lo vendido hasta hoy ÷ días transcurridos del mes × días del mes. Nunca baja de lo vendido más lo ya agendado para el resto del mes.">
        <h3>Proyección a fin de mes — {{ $projection['label'] }}</h3>
        <div class="proj-grid">
            <div><span>Venta hasta hoy</span><b>{{ $money($projection['so_far']) }}</b></div>
            <div><span>Ya agendado (resto del mes)</span><b>{{ $money($projection['booked_rest']) }}</b></div>
            <div class="big"><span>Proyección fin de mes</span><b>{{ $money($projection['projected']) }}</b></div>
        </div>
        <p class="hint">Ritmo actual: {{ $money((int) round($projection['so_far'] / $projection['days_elapsed'])) }}/día · día {{ $projection['days_elapsed'] }} de {{ $projection['days_in_month'] }}.</p>
    </div>

    <h2 class="an-title">Cuándo se vende</h2>
    <div class="an-grid">
    <div class="card t-orange">
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
                <div class="val">{{ $metricVal($d['value']) }} <small>· {{ $d['count'] }}r</small></div>
            </div>
        @empty
            <p class="empty">Sin reservas en el rango.</p>
        @endforelse
    </div>
    </div>

    <h2 class="an-title">Qué se vende</h2>
    <div class="an-grid">
    <div class="card t-green">
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

    <div class="card t-green">
        <h3>Extras vendidos</h3>
        @forelse ($extras as $description => $extra)
            <div class="bar-row"><div class="name">{{ $description }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ $extras->max('value') ? round($extra['value']/$extras->max('value')*100) : 0 }}%"></div></div><div class="val">{{ $money($extra['value']) }} <small>· {{ $extra['quantity'] }} u.</small></div></div>
        @empty
            <p class="empty">Sin extras vendidos en el periodo.</p>
        @endforelse
    </div>

    <div class="card t-green">
        <h3>Estado de las reservas</h3>
        @foreach ($bookingStatuses as $status => $count)<div class="bar-row"><div class="name">{{ str_replace('_',' ', $status) }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ $kpis['reservas']['value'] ? round($count/$kpis['reservas']['value']*100) : 0 }}%"></div></div><div class="val">{{ $count }}</div></div>@endforeach
    </div>
    </div>

    <h2 class="an-title">Cómo llegan los clientes</h2>
    <div class="an-grid">
    <div class="card t-orange span2">
        <h3>Reserva en línea, reserva asistida o presencial</h3>
        @php $oTotal = max(1, $byOrigin['total']); @endphp
        <div class="origin-tiles">
            @foreach ($byOrigin['rows'] as $o)
                <div class="origin-tile origin-{{ $o['key'] ?: 'none' }}">
                    <div class="o-label">{{ $o['label'] }}</div>
                    <div class="o-count">{{ $o['count'] }}</div>
                    <div class="o-sub">{{ round($o['count'] / $oTotal * 100) }}% de las reservas · {{ $money($o['value']) }}</div>
                </div>
            @endforeach
        </div>
        @foreach ($byOrigin['rows'] as $o)
            <div class="bar-row"><div class="name">{{ $o['label'] }}</div><div class="bar-track"><div class="bar-fill {{ $o['key'] === 'presencial' ? '' : 'alt' }}" style="width:{{ round($o['count'] / $oTotal * 100) }}%"></div></div><div class="val">{{ $o['count'] }} <small>({{ round($o['count'] / $oTotal * 100) }}%)</small></div></div>
        @endforeach
        <p class="legend" style="margin-top:8px;"><b>Reserva en línea</b>: la hace solo el cliente en el catálogo y paga. <b>Reserva asistida</b>: la gestiona el anfitrión o recepcionista (por ejemplo, por WhatsApp). <b>Presencial</b>: venta en recepción sin reserva previa. Las reservas anteriores a este cambio no tienen el dato; solo las en línea se pudieron deducir.</p>
    </div>
    </div>

    <h2 class="an-title">Cobros y evolución</h2>
    <div class="an-grid">
    <div class="card t-blue">
        <h3>Medios de pago cobrados</h3>
        @forelse ($paymentMethods as $method => $amount)<div class="bar-row"><div class="name">{{ $method }}</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $paymentMethods->max() ? round($amount/$paymentMethods->max()*100) : 0 }}%"></div></div><div class="val">{{ $money($amount) }}</div></div>@empty<p class="empty">Sin pagos aprobados en el rango.</p>@endforelse
    </div>

    <div class="card t-blue span2">
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
