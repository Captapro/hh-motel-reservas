@extends('admin.layout')
@section('title', 'Récords de venta')
@section('content')
    @include('admin.analytics._styles')
    @php
        $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
        $date = fn ($d) => ucfirst($d->copy()->locale('es')->isoFormat('D [de] MMMM YYYY'));
        $monthName = fn (string $m) => ucfirst(\Carbon\Carbon::parse($m.'-01')->locale('es')->isoFormat('MMMM YYYY'));
    @endphp

    @include('admin.analytics._nav', ['on' => 'records'])
    <h1>Récords de venta</h1>

    @if ($coverage === null)
        <p class="sub">Todavía no hay planillas cargadas. Súbelas en Ventas históricas y vuelve aquí.</p>
    @else
        <p class="sub">Qué días se ha vendido más dentro de las planillas cargadas ({{ $monthName($first) }} a {{ $monthName($last) }}): en total y por día de la semana. La venta del día es todo lo anotado con esa fecha (alojamiento, hora extra, extras y demás). Son ventas de las planillas, anteriores al sistema.</p>

        <form class="flt" method="GET" action="{{ route('admin.analytics.records') }}">
            <div class="flt-row">
                @foreach ($chips as $label => $range)
                    <a class="chip {{ $range['desde'] === $fromMonth && $range['hasta'] === $toMonth ? 'on' : '' }}" href="{{ route('admin.analytics.records', $range) }}">{{ $label }}</a>
                @endforeach
                <label>Desde</label>
                <input type="month" name="desde" value="{{ $fromMonth }}" min="{{ $first }}" max="{{ $last }}">
                <label>Hasta</label>
                <input type="month" name="hasta" value="{{ $toMonth }}" min="{{ $first }}" max="{{ $last }}">
                <button class="go" type="submit">Aplicar</button>
            </div>
        </form>

        @if ($partialLastMonth && $toMonth === $last)
            <p class="sub" style="font-size:12.5px;">{{ $monthName($last) }} está cargado hasta el {{ $lastDay->format('d/m') }}: sus últimos días aún no cuentan.</p>
        @endif

        @if ($records === null)
            <p class="sub">No hay ventas en ese período.</p>
        @else
            @php
                $maxRecord = max(1, $records['by_weekday']->max(fn ($w) => $w['record']['total'] ?? 0));
                $maxTop = max(1, $records['top']->max('total'));
            @endphp

            <h2 class="an-title">Día récord de cada día de la semana</h2>
            <div class="an-grid">
                <div class="card t-orange span2" tabindex="0" data-tip="Entre todos los lunes (martes, miércoles…) del período, el que más vendió. La venta del día suma todo lo anotado con esa fecha en la planilla. El promedio es la venta media de ese día de la semana.">
                    <h3>Récord por día de la semana</h3>
                    @foreach ($records['by_weekday'] as $w)
                        @if ($w['record'])
                            <div class="bar-row">
                                <div class="name">{{ $w['weekday'] }}</div>
                                <div class="bar-track"><div class="bar-fill" style="width:{{ round($w['record']['total'] / $maxRecord * 100) }}%"></div></div>
                                <div class="val">{{ $money($w['record']['total']) }}</div>
                            </div>
                            <div class="legend" style="margin:-2px 0 6px 140px;">{{ $date($w['record']['date']) }} · {{ $w['record']['stays'] }} estadías · promedio del {{ strtolower($w['weekday']) }}: {{ $money($w['average']) }}</div>
                        @endif
                    @endforeach
                </div>
                <div class="card t-green" tabindex="0" data-tip="Los 10 días con más venta del período, sin importar el día de la semana. «e» son las estadías de alojamiento de ese día.">
                    <h3>Los 10 días de mayor venta</h3>
                    @foreach ($records['top'] as $d)
                        <div class="bar-row">
                            <div class="name">{{ ucfirst($d['date']->copy()->locale('es')->isoFormat('ddd D/MM/YY')) }}</div>
                            <div class="bar-track"><div class="bar-fill alt" style="width:{{ round($d['total'] / $maxTop * 100) }}%"></div></div>
                            <div class="val">{{ $money($d['total']) }} <small>· {{ $d['stays'] }}e</small></div>
                        </div>
                    @endforeach
                    <p class="legend" style="margin-top:8px;">e = estadías de alojamiento ese día.</p>
                </div>
            </div>

            <div class="card" style="margin-top:14px; overflow-x:auto;">
                <h3>Los mejores de cada día de la semana</h3>
                <table class="chart-table" style="min-width:640px;">
                    <thead><tr><th style="text-align:left;">Día</th><th style="text-align:left;">Récord</th><th style="text-align:right;">Venta</th><th style="text-align:left;">2.º y 3.º mejores</th><th style="text-align:right;">Promedio</th><th style="text-align:right;">Veces en el período</th></tr></thead>
                    <tbody>
                    @foreach ($records['by_weekday'] as $w)
                        <tr>
                            <td>{{ $w['weekday'] }}</td>
                            <td>{{ $w['record'] ? $date($w['record']['date']) : '—' }}</td>
                            <td class="v">{{ $w['record'] ? $money($w['record']['total']) : '—' }}</td>
                            <td>{{ $w['next']->map(fn ($d) => ucfirst($d['date']->copy()->locale('es')->isoFormat('D MMM YY')).' '.$money($d['total']))->implode(' · ') ?: '—' }}</td>
                            <td class="v">{{ $money($w['average']) }}</td>
                            <td class="v">{{ $w['days'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="legend" style="margin-top:8px;">{{ $records['days_with_sales'] }} días con ventas, {{ $money($records['total']) }} en total. El día de menor venta fue el {{ strtolower($date($records['lowest']['date'])) }} ({{ $money($records['lowest']['total']) }}). Una noche que cruza la medianoche cuenta en la fecha que anotó recepción.</p>
            </div>
        @endif
    @endif
@endsection
