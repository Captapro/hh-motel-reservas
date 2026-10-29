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
    <p class="sub">{{ $rangeLabel }}. Solo estadías que ocurrieron o están confirmadas (sin canceladas ni expiradas). Las visitas anteriores al rango se usan para saber si ya habían venido.</p>

    <form class="flt" method="GET" action="{{ route('admin.analytics.customers') }}">
        <div class="flt-row">
            <label>Desde</label>
            <input type="date" name="from" value="{{ $from }}">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ $to }}">
            <button class="go" type="submit">Aplicar</button>
            @if ($isCustom)
                <a class="chip" href="{{ route('admin.analytics.customers') }}">Volver a los últimos 90 días</a>
            @endif
        </div>
    </form>

    <div class="kpis kpis-all">
        <div class="kpi" data-tip="Personas distintas con al menos una estadía en el rango (sin canceladas ni expiradas)." tabindex="0"><div class="k-label">Clientes que vinieron</div><div class="k-value">{{ $kpis['active'] }}</div><div class="k-delta flat">personas distintas</div></div>
        <div class="kpi" data-tip="Personas cuya primera estadía de todo su historial cae en este rango y que vinieron una sola vez en él." tabindex="0"><div class="k-label">Clientes nuevos</div><div class="k-value">{{ $kpis['new'] }}</div><div class="k-delta flat">primera vez en el rango</div></div>
        <div class="kpi" data-tip="Personas que vinieron 2 o más veces en el rango, o que ya habían venido antes de él. El % es: los que repiten ÷ clientes que vinieron." tabindex="0"><div class="k-label">Clientes que repiten</div><div class="k-value">{{ $kpis['repeaters'] }}</div><div class="k-delta flat">{{ $kpis['repeat_rate'] }}% de los que vinieron</div></div>
        <div class="kpi" data-tip="Clientes con 2 o más visitas que llevan sin volver más de 1,5 veces su frecuencia habitual (mínimo 30 días, máximo 180). La lista está más abajo." tabindex="0"><div class="k-label">En riesgo de perderse</div><div class="k-value">{{ $kpis['at_risk'] }}</div><div class="k-delta flat">venían seguido y se atrasaron</div></div>
        <div class="kpi" data-tip="Estadías del rango ÷ clientes que vinieron." tabindex="0"><div class="k-label">Visitas por cliente</div><div class="k-value">{{ str_replace('.', ',', $kpis['visits_per_customer']) }}</div><div class="k-delta flat">promedio en el rango</div></div>
        <div class="kpi" data-tip="Venta del rango (precio final de las estadías + extras) ÷ clientes que vinieron." tabindex="0"><div class="k-label">Gasto por cliente</div><div class="k-value">{{ $money($kpis['spend_per_customer']) }}</div><div class="k-delta flat">Playroom + extras</div></div>
        <div class="kpi" data-tip="Mediana de los días entre una visita y la siguiente, tomando el historial completo de los clientes del rango. La mitad vuelve antes y la otra mitad después." tabindex="0"><div class="k-label">Vuelven cada</div><div class="k-value">{{ $kpis['median_gap'] !== null ? $kpis['median_gap'].' días' : '—' }}</div><div class="k-delta flat">típico entre una visita y otra</div></div>
        <div class="kpi" data-tip="Reservas del rango que compraron al menos un extra ÷ todas las reservas del rango. Abajo, lo vendido en extras." tabindex="0"><div class="k-label">Reservas con extras</div><div class="k-value">{{ $extras['bookings'] ? round($extras['with_extras'] / $extras['bookings'] * 100) : 0 }}%</div><div class="k-delta flat">{{ $money($extras['extra_revenue']) }} en extras</div></div>
    </div>

    <h2 class="an-title">Quiénes son</h2>
    <div class="an-grid">
        <div class="card t-orange">
            <h3>Tipo de cliente</h3>
            @forelse ($segments as $seg)
                <div class="bar-row"><div class="name">{{ $seg['label'] }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ round($seg['count'] / max(1, $segments->max('count')) * 100) }}%"></div></div><div class="val">{{ $seg['count'] }} <small>· {{ $money($seg['revenue']) }}</small></div></div>
            @empty
                <p class="empty">Sin clientes en el rango.</p>
            @endforelse
            <p class="legend" style="margin-top:8px;">Según las reglas de Clientes (nuevo, frecuente, ocasional, esporádico).</p>
        </div>
        <div class="card t-orange span2">
            <h3>Nuevos y que ya habían venido — últimos 6 meses</h3>
            @php $maxM = max(1, collect($monthly)->max(fn ($m) => $m['new'] + $m['returning'])); @endphp
            <div class="stack-chart">
                @foreach ($monthly as $m)
                    <div class="stack-col">
                        <div class="stack-bar" title="{{ $m['label'] }}: {{ $m['new'] }} nuevos, {{ $m['returning'] }} que repiten">
                            <div class="seg-ret" style="height:{{ round($m['returning'] / $maxM * 100) }}%"></div>
                            <div class="seg-new" style="height:{{ round($m['new'] / $maxM * 100) }}%"></div>
                        </div>
                        <div class="stack-label">{{ $m['label'] }}</div>
                        <div class="stack-num">{{ $m['new'] }} + {{ $m['returning'] }}</div>
                    </div>
                @endforeach
            </div>
            <p class="legend"><span class="dot-new"></span> Nuevos &nbsp; <span class="dot-ret"></span> Ya habían venido</p>
        </div>
    </div>

    <h2 class="an-title">Qué prefieren</h2>
    <div class="an-grid">
        <div class="card t-green"><h3>Categoría favorita (reservas)</h3>{!! $bars($categories) !!}</div>
        <div class="card t-green"><h3>Cuánto tiempo se quedan</h3>{!! $bars($durations) !!}</div>
        <div class="card t-green"><h3>Cuántos van</h3>{!! $bars($party) !!}</div>
        <div class="card t-green"><h3>Día de la semana que llegan</h3>{!! $bars($weekdays) !!}</div>
        <div class="card t-green"><h3>Momento del día en que llegan</h3>{!! $bars($dayparts) !!}</div>
        <div class="card t-green">
            <h3>Cupones y extras</h3>
            <div class="bar-row"><div class="name">Usan cupón u oferta</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $pct($coupons['used'], max(1, $coupons['bookings'])) }}%"></div></div><div class="val">{{ $coupons['used'] }} <small>· {{ $pct($coupons['used'], $coupons['bookings']) }}%</small></div></div>
            <div class="bar-row"><div class="name">Piden extras</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ $pct($extras['with_extras'], max(1, $extras['bookings'])) }}%"></div></div><div class="val">{{ $extras['with_extras'] }} <small>· {{ $pct($extras['with_extras'], $extras['bookings']) }}%</small></div></div>
            <h3 style="margin-top:14px;">Cómo pagan</h3>
            @php $payRows = $payments->map(fn ($amount, $method) => ['label' => $method, 'count' => $amount])->values(); @endphp
            {!! $payRows->isEmpty() ? '<p class="empty">Sin pagos en el rango.</p>' : $bars($payRows, 'count', 'alt') !!}
        </div>
    </div>

    <h2 class="an-title">Cómo reservan y cuándo vuelven</h2>
    <div class="an-grid">
        <div class="card t-blue"><h3>Por dónde reservan</h3>{!! $bars($channel) !!}</div>
        <div class="card t-blue"><h3>Con cuánta anticipación</h3>{!! $bars($lead) !!}</div>
        <div class="card t-blue"><h3>Días entre una visita y la siguiente</h3>{!! $bars($gapBuckets) !!}</div>
    </div>

    <h2 class="an-title">A quién cuidar</h2>
    <div class="an-grid">
        <div class="card t-orange span2">
            <h3>Mejores clientes del rango (por gasto)</h3>
            <table class="chart-table">
                <thead><tr><td>Cliente</td><td class="v">Visitas</td><td class="v">Gasto</td><td class="v">Ticket</td><td class="v">Última</td><td>Prefiere</td></tr></thead>
                <tbody>
                    @forelse ($top as $c)
                        <tr>
                            <td><a class="link" href="{{ route('admin.customers.show', $c['id']) }}">{{ $c['name'] }}</a></td>
                            <td class="v">{{ $c['visits'] }}</td>
                            <td class="v">{{ $money($c['total']) }}</td>
                            <td class="v">{{ $money($c['avg']) }}</td>
                            <td class="v">{{ $c['last']->format('d/m') }}</td>
                            <td>{{ $c['favorite'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Sin clientes en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card t-green">
            <h3>En riesgo de perderse</h3>
            <p class="legend">Venían seguido y llevan más tiempo del habitual sin volver (hasta 6 meses).</p>
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

    @if ($historical)
        <h2 class="an-title">Con las planillas históricas</h2>
        <p class="sub">Todo lo cargado de las planillas de recepción ({{ \Carbon\Carbon::parse($historical['coverage']['desde'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($historical['coverage']['hasta'])->format('d/m/Y') }}), sin importar el rango de arriba. Son estadías anteriores al sistema: <b>no se mezclan</b> con las cifras de arriba. Personas contadas por RUT válido; {{ $historical['stays_with_rut'] }} de {{ $historical['stays'] }} estadías traen al menos un RUT válido. Mientras haya pocos meses cargados, «nuevas» está sobreestimado.</p>
        <div class="kpis kpis-all">
            <div class="kpi"><div class="k-label">Personas distintas</div><div class="k-value">{{ $historical['people'] }}</div><div class="k-delta flat">por RUT válido, titulares y acompañantes</div></div>
            <div class="kpi"><div class="k-label">Volvieron</div><div class="k-value">{{ $historical['returned'] }}</div><div class="k-delta flat">{{ $historical['returned_pct'] }}% de las personas</div></div>
            <div class="kpi"><div class="k-label">Estadías por persona</div><div class="k-value">{{ str_replace('.', ',', $historical['stays_per_person']) }}</div><div class="k-delta flat">promedio</div></div>
            <div class="kpi"><div class="k-label">Vuelven cada</div><div class="k-value">{{ $historical['median_gap'] !== null ? $historical['median_gap'].' días' : '—' }}</div><div class="k-delta flat">típico entre una estadía y otra</div></div>
        </div>
        <div class="an-grid">
            <div class="card t-blue"><h3>Cuántas veces vino cada persona</h3>{!! $bars($historical['frequency']) !!}</div>
            <div class="card t-blue"><h3>Días entre una estadía y la siguiente</h3>{!! $bars($historical['gap_buckets']) !!}</div>
            <div class="card t-green"><h3>Categoría (estadías)</h3>{!! $bars($historical['categories']) !!}</div>
            <div class="card t-green"><h3>Horas pagadas</h3>{!! $bars($historical['hours']) !!}</div>
            <div class="card t-green"><h3>Día de la semana</h3>{!! $bars($historical['weekdays']) !!}</div>
            <div class="card t-green"><h3>Turno</h3>{!! $bars($historical['shifts']) !!}</div>
            <div class="card t-orange span2">
                <h3>Por mes: personas nuevas y que ya habían venido</h3>
                <table class="chart-table">
                    <thead><tr><td>Mes</td><td class="v">Personas</td><td class="v">Nuevas</td><td class="v">Ya habían venido</td><td class="v">% que volvió</td></tr></thead>
                    <tbody>
                        @foreach ($historical['months'] as $m)
                            <tr><td style="text-transform:capitalize;">{{ $m['label'] }}</td><td class="v">{{ $m['people'] }}</td><td class="v">{{ $m['new'] }}</td><td class="v">{{ $m['returning'] }}</td><td class="v">{{ $pct($m['returning'], $m['people']) }}%</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        .stack-chart { display:flex; align-items:flex-end; gap:12px; height:150px; margin-top:8px; }
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
