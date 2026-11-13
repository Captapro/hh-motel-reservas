@extends('admin.layout')
@section('title', 'Cómo llegan')
@section('content')
    @include('admin.analytics._styles')
    @php
        $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
    @endphp

    @include('admin.analytics._nav', ['on' => 'channels'])
    <h1>Cómo llegan los clientes</h1>
    <p class="sub">{{ $rangeLabel }}. De dónde salen las ventas: si el cliente reservó solo en línea, si lo ayudó recepción o llegó sin reserva, y por qué canal o anuncio. Solo estadías del sistema con su pago completo.</p>

    <form class="flt" method="GET" action="{{ route('admin.analytics.channels') }}">
        <div class="flt-row">
            <label>Desde</label>
            <input type="date" name="from" value="{{ $from }}">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ $to }}">
            <button class="go" type="submit">Aplicar</button>
            @foreach (['today' => 'Hoy', '7d' => '7 días', 'this_month' => 'Este mes', 'last_month' => 'Mes pasado', '90d' => '90 días'] as $p => $pl)
                <a class="chip {{ $preset === $p ? 'active' : '' }}" href="{{ route('admin.analytics.channels', ['preset' => $p]) }}">{{ $pl }}</a>
            @endforeach
            @if ($isLive)
                <span class="live-note" title="Con un rango que incluye hoy, la página se vuelve a cargar sola cada minuto.">● En vivo · {{ $refreshedAt->format('H:i') }}</span>
            @endif
        </div>
    </form>

    <div class="an-grid">
    <div class="card t-orange span2">
        <h3>Canales: venta, ticket y cambio</h3>
        @if ($table->isEmpty())
            <p class="empty">Sin ventas en el rango.</p>
        @else
            <table class="rank-table ch-table">
                <thead><tr><th>Canal</th><th class="n">Reservas</th><th class="n">Venta</th><th class="n">Ticket</th><th class="n">% venta</th><th class="n">vs {{ $prevLabel }}</th></tr></thead>
                <tbody>
                @foreach ($table as $r)
                    <tr>
                        <td class="nm">{{ $r['label'] }}</td>
                        <td class="n">{{ $r['count'] }}</td>
                        <td class="n">{{ $money($r['value']) }}</td>
                        <td class="n">{{ $money($r['ticket']) }}</td>
                        <td class="n pct">{{ number_format($r['share'] * 100, 0) }}%<div class="mini"><i style="width:{{ round($r['share'] / max(0.0001, $table->max('share')) * 100) }}%"></i></div></td>
                        <td class="n" style="color:{{ $r['delta'] === null ? '#9ca3af' : ($r['delta'] >= 0 ? '#15803d' : '#dc2626') }};">@if ($r['delta'] === null){{ $r['prev'] ? '—' : 'nuevo' }}@else{{ $r['delta'] > 0 ? '▲' : ($r['delta'] < 0 ? '▼' : '=') }} {{ abs($r['delta']) }}%@endif</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td>Total</td><td class="n">{{ $table->sum('count') }}</td><td class="n">{{ $money($table->sum('value')) }}</td><td class="n">{{ $money($table->sum('count') ? (int) round($table->sum('value') / $table->sum('count')) : 0) }}</td><td class="n">100%</td><td></td></tr></tfoot>
            </table>
            <p class="legend" style="margin-top:8px;"><b>Ticket</b> = venta ÷ reservas del canal. <b>vs</b> compara la venta del canal con el período anterior a la misma altura; «nuevo» = ese canal no vendió antes.</p>
        @endif
    </div>
    </div>

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

    <div class="an-grid">
    <div class="card t-orange span2">
        <h3>Ventas por canal y por anuncio</h3>
        @php $cMax = max(1, (int) $byChannel['rows']->max('value')); @endphp
        @forelse ($byChannel['rows'] as $c)
            <div class="bar-row"><div class="name">{{ $c['label'] }}</div><div class="bar-track"><div class="bar-fill" style="width:{{ round($c['value'] / $cMax * 100) }}%"></div></div><div class="val">{{ $money($c['value']) }} <small>({{ $c['count'] }})</small></div></div>
            @foreach ($c['ads'] as $ad)
                <div class="bar-row" style="opacity:.85;"><div class="name" style="padding-left:14px;font-size:12px;">↳ {{ $ad['label'] }}</div><div class="bar-track"><div class="bar-fill alt" style="width:{{ round($ad['value'] / $cMax * 100) }}%"></div></div><div class="val">{{ $money($ad['value']) }} <small>({{ $ad['count'] }})</small></div></div>
            @endforeach
        @empty
            <p class="empty">Sin ventas en el rango.</p>
        @endforelse
        <p class="legend" style="margin-top:8px;"><b>Meta · WhatsApp</b>: el cliente escribió por WhatsApp desde un anuncio (el sistema lo lee de GHL por su teléfono). El resto va por su origen: Google, el sitio, presencial o asistida. Entre paréntesis, cuántas reservas. Una venta puede venir de un anuncio aunque el cliente la haya cerrado en recepción; si reservó con otro teléfono no se puede enlazar.</p>
    </div>
    </div>


    @if ($isLive)
        <script>
            setInterval(function () {
                var el = document.activeElement;
                if (document.hidden || (el && (el.tagName === 'INPUT' || el.tagName === 'SELECT'))) return;
                location.reload();
            }, 60000);
        </script>
    @endif
@endsection
