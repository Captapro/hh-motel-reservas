@extends('admin.layout')
@section('title', 'Extras por mes')
@section('content')
    @include('admin.analytics._styles')
    @php
        $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $label = fn ($m) => ucfirst($m->copy()->locale('es')->isoFormat('MMM YY'));
        $full = fn ($m) => ucfirst($m->copy()->locale('es')->isoFormat('MMMM YYYY'));
        $delta = function (?int $d) {
            if ($d === null) {
                return '<span style="color:#9ca3af;">—</span>';
            }
            if ($d === 0) {
                return '<span style="color:#6b7280;">= 0%</span>';
            }

            return '<span style="color:'.($d > 0 ? '#15803d' : '#b91c1c').';">'.($d > 0 ? '▲ ' : '▼ ').abs($d).'%</span>';
        };
    @endphp

    @include('admin.analytics._nav', ['on' => 'extras'])
    <h1>Extras por mes</h1>

    @if ($rows->isEmpty())
        <p class="sub">Todavía no hay planillas cargadas. Súbelas en Ventas históricas y vuelve aquí.</p>
    @else
        @php
            $maxExtras = max(1, $rows->max('extras'));
            $maxPerStay = max(1, $rows->max('per_stay'));
            $complete = $rows->reject(fn ($r) => $r['partial'])->values();
            $best = $rows->sortByDesc('extras')->first();
            $firstC = $complete->first();
            $lastC = $complete->last();
        @endphp
        <p class="sub">Venta de extras mes a mes, según las planillas cargadas, para ver cómo va creciendo. <b>Extras</b> son los consumos (bebidas, preservativos, etc.); <b>extras core</b> son hora extra, persona adicional y otros servicios. Como los meses tienen distinta cantidad de estadías, mira también los <b>extras por estadía</b>: es la cifra más justa para comparar.</p>

        <div class="kpis kpis-all">
            <div class="kpi" tabindex="0" data-tip="El mes con más venta de extras (consumos) entre los cargados."><div class="k-label">Mejor mes en extras</div><div class="k-value">{{ $money($best['extras']) }}</div><div class="k-delta flat">{{ $full($best['month']) }}</div></div>
            @if ($firstC && $lastC && $firstC['month']->ne($lastC['month']) && $firstC['per_stay'] > 0)
                @php $growth = (int) round(($lastC['per_stay'] - $firstC['per_stay']) / $firstC['per_stay'] * 100); @endphp
                <div class="kpi" tabindex="0" data-tip="Extras por estadía del último mes completo frente al primer mes completo cargado. Extras por estadía = venta de extras ÷ estadías de alojamiento del mes."><div class="k-label">Extras por estadía: primero → último mes</div><div class="k-value">{{ $money($firstC['per_stay']) }} → {{ $money($lastC['per_stay']) }}</div><div class="k-delta {{ $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'flat') }}">{{ $growth > 0 ? '▲' : ($growth < 0 ? '▼' : '=') }} {{ abs($growth) }}% · {{ $label($firstC['month']) }} a {{ $label($lastC['month']) }}</div></div>
            @endif
            <div class="kpi" tabindex="0" data-tip="Promedio mensual de la venta de extras (consumos), contando solo los meses completos."><div class="k-label">Promedio mensual de extras</div><div class="k-value">{{ $money($complete->isEmpty() ? 0 : $complete->avg('extras')) }}</div><div class="k-delta flat">{{ $complete->count() }} {{ $complete->count() === 1 ? 'mes completo' : 'meses completos' }}</div></div>
        </div>

        <div class="an-grid" style="margin-top:14px;">
            <div class="card t-orange" tabindex="0" data-tip="Venta de extras (consumos) de cada mes. El mes en curso, si está incompleto, queda más bajo solo porque faltan días.">
                <h3>Extras vendidos por mes</h3>
                @foreach ($rows as $r)
                    <div class="bar-row">
                        <div class="name">{{ $label($r['month']) }}{{ $r['partial'] ? '*' : '' }}</div>
                        <div class="bar-track"><div class="bar-fill" style="width:{{ round($r['extras'] / $maxExtras * 100) }}%"></div></div>
                        <div class="val">{{ $money($r['extras']) }}</div>
                    </div>
                @endforeach
                @if ($rows->contains('partial', true))<p class="legend" style="margin-top:8px;">* mes incompleto (cargado hasta el {{ $rows->last()['last_day']->format('d/m') }}).</p>@endif
            </div>
            <div class="card t-green" tabindex="0" data-tip="Extras por estadía = venta de extras del mes ÷ estadías de alojamiento del mes. Permite comparar meses con distinta cantidad de estadías, también el incompleto.">
                <h3>Extras por estadía</h3>
                @foreach ($rows as $r)
                    <div class="bar-row">
                        <div class="name">{{ $label($r['month']) }}{{ $r['partial'] ? '*' : '' }}</div>
                        <div class="bar-track"><div class="bar-fill alt" style="width:{{ round($r['per_stay'] / $maxPerStay * 100) }}%"></div></div>
                        <div class="val">{{ $money($r['per_stay']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card" style="margin-top:14px; overflow-x:auto;">
            <h3>Mes a mes</h3>
            <table class="chart-table" style="min-width:880px;">
                <thead><tr>
                    <th style="text-align:left;">Mes</th>
                    <th style="text-align:right;">Estadías</th>
                    <th style="text-align:right;" data-tip="Venta de extras (consumos) del mes." tabindex="0">Extras</th>
                    <th style="text-align:right;" data-tip="Cambio de la venta de extras frente al mes anterior. No se calcula si el mes está incompleto." tabindex="0">vs mes anterior</th>
                    <th style="text-align:right;" data-tip="Extras ÷ estadías de alojamiento del mes." tabindex="0">Extras por estadía</th>
                    <th style="text-align:right;" data-tip="Cambio de los extras por estadía frente al mes anterior." tabindex="0">vs mes anterior</th>
                    <th style="text-align:right;" data-tip="Extras ÷ venta total del mes (alojamiento + todo lo demás)." tabindex="0">% de la venta</th>
                    <th style="text-align:right;" data-tip="Hora extra + persona adicional + otros servicios (lavandería, toalla, etc.)." tabindex="0">Extras core</th>
                    <th style="text-align:right;" data-tip="Cambio de los extras core frente al mes anterior. No se calcula si el mes está incompleto." tabindex="0">vs mes anterior</th>
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ $full($r['month']) }}{{ $r['partial'] ? ' (hasta el '.$r['last_day']->format('d/m').')' : '' }}</td>
                        <td class="v">{{ $r['stays'] }}</td>
                        <td class="v">{{ $money($r['extras']) }}</td>
                        <td class="v">{!! $delta($r['change_extras']) !!}</td>
                        <td class="v">{{ $money($r['per_stay']) }}</td>
                        <td class="v">{!! $delta($r['change_per_stay']) !!}</td>
                        <td class="v">{{ str_replace('.', ',', $r['share']) }}%</td>
                        <td class="v">{{ $money($r['core']) }}</td>
                        <td class="v">{!! $delta($r['change_core']) !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="legend" style="margin-top:8px;">Son ventas de las planillas de recepción (anteriores al sistema). Los pagos fraccionados no se cuentan como extras.</p>
        </div>

        @if ($system->isNotEmpty())
            <div class="card" style="margin-top:14px;">
                <h3>Extras vendidos por el sistema (aparte de las planillas)</h3>
                <table class="chart-table">
                    <thead><tr><th style="text-align:left;">Mes</th><th style="text-align:right;">Estadías</th><th style="text-align:right;">Extras</th><th style="text-align:right;">Extras por estadía</th></tr></thead>
                    <tbody>
                    @foreach ($system as $m)
                        <tr>
                            <td>{{ ucfirst(\Carbon\Carbon::parse($m['month'].'-01')->locale('es')->isoFormat('MMMM YYYY')) }}</td>
                            <td class="v">{{ $m['stays'] }}</td>
                            <td class="v">{{ $money($m['extras']) }}</td>
                            <td class="v">{{ $money($m['stays'] ? round($m['extras'] / $m['stays']) : 0) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="legend" style="margin-top:8px;">Reservas del sistema con su pago completo. No se suman a las planillas de la tabla de arriba porque un mismo mes puede tener ventas en ambos lados.</p>
            </div>
        @endif
    @endif
@endsection
