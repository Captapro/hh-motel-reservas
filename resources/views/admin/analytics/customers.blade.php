@extends('admin.layout')
@section('title', 'Comportamiento de clientes')
@section('content')
    @include('admin.analytics._styles')
    @php
        $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
        $pct = fn ($n, $total) => $total ? round($n / $total * 100) : 0;
        // Barra horizontal con etiqueta, valor y porcentaje del total del bloque.
        $bars = function ($rows, $field = 'count', $class = '') use ($pct) {
            $max = max(1, collect($rows)->max($field));
            $total = max(1, collect($rows)->sum($field));
            $html = '';
            foreach ($rows as $r) {
                $html .= '<div class="bar-row"><div class="name">'.e($r['label']).'</div><div class="bar-track"><div class="bar-fill '.$class.'" style="width:'.round($r[$field] / $max * 100).'%"></div></div><div class="val">'.number_format($r[$field], 0, ',', '.').' <small>· '.$pct($r[$field], $total).'%</small></div></div>';
            }

            return $html ?: '<p class="empty">Sin datos en el rango.</p>';
        };
    @endphp

    @include('admin.analytics._nav', ['on' => 'customers'])
    <h1>Comportamiento de clientes</h1>
    <p class="sub">{{ $rangeLabel }}. Planillas de recepción y sistema como <b>un solo historial</b>: cada persona es su RUT (titular o acompañante) y una visita cuenta igual venga de donde venga.@if ($cutoff) Hasta el {{ $cutoff->format('d/m/Y') }} los datos salen de la planilla; desde el día siguiente, del sistema.@endif Sin canceladas ni expiradas.</p>

    <form class="flt" method="GET" action="{{ route('admin.analytics.customers') }}">
        <div class="flt-row">
            <label>Desde</label>
            <input type="date" name="from" value="{{ $from }}">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ $to }}">
            <button class="go" type="submit">Aplicar</button>
            @if ($isCustom)
                <a class="chip" href="{{ route('admin.analytics.customers') }}">Volver a todo lo cargado</a>
            @endif
        </div>
    </form>

    <div class="kpis kpis-all">
        <div class="kpi" data-tip="Personas distintas (por RUT) que vinieron en el rango, como titular o acompañante." tabindex="0"><div class="k-label">Personas que vinieron</div><div class="k-value">{{ number_format($kpis['people'], 0, ',', '.') }}</div><div class="k-delta flat">por RUT, distintas</div></div>
        <div class="kpi" data-tip="Personas cuya primera visita de toda su historia (planilla + sistema) cae en el rango." tabindex="0"><div class="k-label">Nuevas</div><div class="k-value">{{ number_format($kpis['new'], 0, ',', '.') }}</div><div class="k-delta flat">primera vez en el rango</div></div>
        <div class="kpi" data-tip="Personas que ya habían venido antes del rango o que vinieron 2 o más días distintos dentro de él. El % es sobre las personas que vinieron." tabindex="0"><div class="k-label">Recurrentes</div><div class="k-value">{{ number_format($kpis['repeaters'], 0, ',', '.') }}</div><div class="k-delta flat">{{ $kpis['repeat_rate'] }}% de las personas</div></div>
        <div class="kpi" data-tip="Mediana de los días entre una visita y la siguiente de la misma persona (vueltas dentro del rango). La mitad vuelve antes y la otra mitad después." tabindex="0"><div class="k-label">Vuelven cada</div><div class="k-value">{{ $kpis['median_gap'] !== null ? $kpis['median_gap'].' días' : '—' }}</div><div class="k-delta flat">típico entre una visita y otra</div></div>
        <div class="kpi" data-tip="Estadías del rango (planilla + sistema)." tabindex="0"><div class="k-label">Estadías</div><div class="k-value">{{ number_format($kpis['stays'], 0, ',', '.') }}</div><div class="k-delta flat">en el rango</div></div>
        <div class="kpi" data-tip="Visitas del rango ÷ personas que vinieron." tabindex="0"><div class="k-label">Visitas por persona</div><div class="k-value">{{ str_replace('.', ',', $kpis['visits_per_person']) }}</div><div class="k-delta flat">promedio en el rango</div></div>
        <div class="kpi" data-tip="Venta del rango (Playroom + extras) ÷ estadías." tabindex="0"><div class="k-label">Venta por estadía</div><div class="k-value">{{ $money($kpis['ticket']) }}</div><div class="k-delta flat">Playroom + extras</div></div>
        <div class="kpi" data-tip="Clientes con 2 o más visitas que llevan sin volver más de 1,5 veces su frecuencia habitual (mínimo 30 días, máximo 180). La lista está más abajo." tabindex="0"><div class="k-label">En riesgo de perderse</div><div class="k-value">{{ $kpis['at_risk'] }}</div><div class="k-delta flat">venían seguido y se atrasaron</div></div>
    </div>

    <h2 class="an-title">Recurrencia mes a mes</h2>
    <div class="an-grid">
        <div class="card t-orange span2">
            <h3>Nuevas y que ya habían venido, por mes</h3>
            @php $maxM = max(1, $monthly->max('people')); @endphp
            <div class="stack-chart">
                @foreach ($monthly as $m)
                    <div class="stack-col">
                        <div class="stack-rate">{{ $m['comparable'] ? $m['rate'].'%' : '—' }}</div>
                        <div class="stack-bar" title="{{ $m['label'] }}: {{ $m['people'] }} personas · {{ $m['new'] }} nuevas · {{ $m['returning'] }} ya habían venido">
                            <div class="seg-ret" style="height:{{ round($m['returning'] / $maxM * 100) }}%"></div>
                            <div class="seg-new" style="height:{{ round($m['new'] / $maxM * 100) }}%"></div>
                        </div>
                        <div class="stack-label">{{ $m['label'] }}</div>
                        <div class="stack-num">{{ $m['new'] }} + {{ $m['returning'] }}</div>
                    </div>
                @endforeach
            </div>
            <p class="legend"><span class="dot-new"></span> Nuevas &nbsp; <span class="dot-ret"></span> Ya habían venido &nbsp;· arriba, % de recurrencia (ya habían venido ÷ personas del mes). El primer mes cargado no tiene con qué comparar.</p>
        </div>
        <div class="card t-orange">
            <h3>Meses con mayor recurrencia</h3>
            @php $ranked = $monthly->where('comparable', true)->sortByDesc('rate')->values(); @endphp
            @forelse ($ranked as $m)
                <div class="bar-row"><div class="name" style="text-transform:capitalize;">{{ $m['label'] }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ $m['rate'] }}%"></div></div><div class="val">{{ $m['rate'] }}% <small>· {{ $m['returning'] }} de {{ $m['people'] }}</small></div></div>
            @empty
                <p class="empty">Hace falta más de un mes de datos.</p>
            @endforelse
        </div>
    </div>

    <h2 class="an-title">Cuándo vuelven</h2>
    <div class="an-grid">
        <div class="card t-blue"><h3>Cuánto tardan en volver</h3>{!! $bars($gapBuckets) !!}<p class="legend" style="margin-top:8px;">Cada vuelta del rango, según los días desde la visita anterior de esa persona.</p></div>
        <div class="card t-blue">
            <h3>De las personas nuevas, cuántas volvieron</h3>
            <table class="chart-table">
                <thead><tr><td>Dentro de</td><td class="v">Volvieron</td><td class="v">%</td></tr></thead>
                <tbody>
                    @foreach ($retention as $r)
                        <tr><td>{{ $r['days'] }} días</td><td class="v">{{ number_format($r['back'], 0, ',', '.') }} de {{ number_format($r['eligible'], 0, ',', '.') }}</td><td class="v"><b>{{ $r['pct'] }}%</b></td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="legend" style="margin-top:8px;">Personas cuya primera visita fue en el rango. Solo cuenta a quienes ya tuvieron el plazo completo para volver.</p>
        </div>
        <div class="card t-blue"><h3>Cuántas veces vino cada persona</h3>{!! $bars($frequency) !!}<p class="legend" style="margin-top:8px;">Personas del rango, con todas sus visitas hasta el fin del rango.</p></div>
    </div>

    <h2 class="an-title">Qué prefieren</h2>
    <div class="an-grid">
        <div class="card t-green"><h3>Categoría (estadías)</h3>{!! $bars($categories) !!}</div>
        <div class="card t-green"><h3>Cuánto tiempo se quedan</h3>{!! $bars($durations) !!}</div>
        <div class="card t-green"><h3>Cuántos van</h3>{!! $bars($party) !!}</div>
        <div class="card t-green"><h3>Día de la semana que llegan</h3>{!! $bars($weekdays) !!}</div>
        <div class="card t-green"><h3>Momento del día en que llegan</h3>{!! $bars($dayparts) !!}</div>
    </div>

    <h2 class="an-title">A quién cuidar</h2>
    <div class="an-grid">
        <div class="card t-orange span2">
            <h3>Las personas que más vinieron en el rango</h3>
            <table class="chart-table">
                <thead><tr><td>Persona</td><td class="v">Visitas</td><td class="v">Pagó como titular</td><td class="v">Última</td><td>Prefiere</td></tr></thead>
                <tbody>
                    @forelse ($top as $c)
                        <tr>
                            <td>@if ($c['link'])<a class="link" href="{{ route('admin.customers.show', $c['link']) }}">{{ $c['name'] }}</a>@else{{ $c['name'] }}@endif</td>
                            <td class="v">{{ $c['visits'] }}</td>
                            <td class="v">{{ $money($c['total']) }}</td>
                            <td class="v">{{ $c['last']->format('d/m/y') }}</td>
                            <td>{{ $c['favorite'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Sin visitas en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card t-green">
            <h3>En riesgo de perderse</h3>
            <p class="legend">Clientes del sistema que venían seguido (también según la planilla) y llevan más tiempo del habitual sin volver (hasta 6 meses).</p>
            <table class="chart-table">
                <tbody>
                    @forelse ($risk as $r)
                        <tr>
                            <td><a class="link" href="{{ route('admin.customers.show', $r['customer']) }}">{{ $r['customer']->name }}</a><br><span style="font-size:11px;color:#6b7280;">{{ $r['visits'] }} visitas · suele volver cada {{ $r['avg'] }} d</span></td>
                            <td class="v">hace {{ $r['since'] }} d</td>
                            <td class="v">@if ($r['customer']->phone_e164)<a class="link" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', $r['customer']->phone_e164) }}">WhatsApp</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Nadie en riesgo por ahora.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="an-title">Hace cuánto no vienen</h2>
    <p class="sub">Según su última estadía finalizada. En GHL cada cliente lleva la etiqueta de su tramo (se actualiza sola): úsalas para campañas de «te extrañamos» o públicos de Meta.</p>
    <div class="an-grid">
        @foreach ($activity as $tag => $group)
            <div class="card {{ $tag === 'hh_pausa' ? 't-green' : 't-orange' }}">
                <h3>{{ $group['label'] }} — {{ $group['customers']->count() }}</h3>
                <p class="legend">Etiqueta en GHL: <code>{{ $tag }}</code></p>
                <table class="chart-table">
                    <tbody>
                        @forelse ($group['customers']->take(15) as $r)
                            <tr>
                                <td><a class="link" href="{{ route('admin.customers.show', $r['customer']) }}">{{ $r['customer']->name }}</a></td>
                                <td class="v">hace {{ $r['days'] }} d</td>
                                <td class="v">@if ($r['customer']->phone_e164)<a class="link" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', $r['customer']->phone_e164) }}">WhatsApp</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Nadie en este tramo.</td></tr>
                        @endforelse
                        @if ($group['customers']->count() > 15)
                            <tr><td colspan="3" class="empty">y {{ $group['customers']->count() - 15 }} más</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <h2 class="an-title">Lo que solo registra el sistema</h2>
    <p class="sub">La planilla no anotaba el canal, la anticipación, los pagos ni los cupones: esto cuenta solo las reservas del sistema dentro del rango @if ($cutoff) (desde el {{ $cutoff->copy()->addDay()->format('d/m/Y') }})@endif.</p>
    <div class="an-grid">
        <div class="card t-orange">
            <h3>Tipo de cliente</h3>
            @forelse ($segments as $seg)
                <div class="bar-row"><div class="name">{{ $seg['label'] }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ round($seg['count'] / max(1, $segments->max('count')) * 100) }}%"></div></div><div class="val">{{ $seg['count'] }} <small>· {{ $money($seg['revenue']) }}</small></div></div>
            @empty
                <p class="empty">Sin clientes en el rango.</p>
            @endforelse
            <p class="legend" style="margin-top:8px;">Según las reglas de Clientes (cuentan también las visitas de la planilla).</p>
        </div>
        <div class="card t-blue"><h3>Por dónde reservan</h3>{!! $bars($channel) !!}</div>
        <div class="card t-blue"><h3>Con cuánta anticipación</h3>{!! $bars($lead) !!}</div>
        <div class="card t-green">
            <h3>Cupones y extras</h3>
            <div class="bar-row"><div class="name">Usan cupón u oferta</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $pct($coupons['used'], max(1, $coupons['bookings'])) }}%"></div></div><div class="val">{{ $coupons['used'] }} <small>· {{ $pct($coupons['used'], $coupons['bookings']) }}%</small></div></div>
            <div class="bar-row"><div class="name">Piden extras</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $pct($extras['with_extras'], max(1, $extras['bookings'])) }}%"></div></div><div class="val">{{ $extras['with_extras'] }} <small>· {{ $pct($extras['with_extras'], $extras['bookings']) }}%</small></div></div>
            <h3 style="margin-top:14px;">Cómo pagan</h3>
            @php $payRows = $payments->map(fn ($amount, $method) => ['label' => $method, 'count' => $amount])->values(); @endphp
            {!! $payRows->isEmpty() ? '<p class="empty">Sin pagos en el rango.</p>' : $bars($payRows, 'count', 'alt') !!}
        </div>
    </div>

    <style>
        .stack-chart { display:flex; align-items:flex-end; gap:12px; height:170px; margin-top:8px; }
        .stack-rate { font-size:11px; font-weight:700; color:#1d4ed8; font-family:ui-monospace,monospace; }
        .stack-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; min-width:0; }
        .stack-bar { width:100%; max-width:56px; height:110px; display:flex; flex-direction:column-reverse; border-radius:6px; overflow:hidden; background:#eef0f3; }
        .seg-new { background:#ff7918; width:100%; }
        .seg-ret { background:#3b82f6; width:100%; }
        .stack-label { font-size:11px; color:#6b7280; text-transform:capitalize; }
        .stack-num { font-size:10.5px; color:#111827; font-family:ui-monospace,monospace; }
        .dot-new, .dot-ret { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:4px; vertical-align:middle; }
        .dot-new { background:#ff7918; } .dot-ret { background:#3b82f6; }
        .chart-table thead td { font-size:10.5px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
        .wrap .card a.link { color:#c2410c !important; }
    </style>
@endsection
