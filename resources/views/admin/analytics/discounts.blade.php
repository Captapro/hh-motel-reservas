@extends('admin.layout')
@section('title', 'Descuentos y upsells')
@section('content')
    @include('admin.analytics._styles')
    @php
        $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
        $pct = fn ($x) => number_format($x * 100, 0).'%';
    @endphp

    @include('admin.analytics._nav', ['on' => 'discounts'])
    <h1>Descuentos y upsells</h1>
    <p class="sub">{{ $rangeLabel }}. Qué cupones y ofertas se usaron, cuánto costaron y cómo se comparan con las estadías sin descuento; y cuántos upsells se vendieron. Solo estadías del sistema con su pago completo.</p>

    <form class="flt" method="GET" action="{{ route('admin.analytics.discounts') }}">
        <div class="flt-row">
            <label>Desde</label>
            <input type="date" name="from" value="{{ $from }}">
            <label>Hasta</label>
            <input type="date" name="to" value="{{ $to }}">
            <button class="go" type="submit">Aplicar</button>
            @foreach (['today' => 'Hoy', '7d' => '7 días', 'this_month' => 'Este mes', 'last_month' => 'Mes pasado', '90d' => '90 días'] as $p => $pl)
                <a class="chip {{ $preset === $p ? 'active' : '' }}" href="{{ route('admin.analytics.discounts', ['preset' => $p]) }}">{{ $pl }}</a>
            @endforeach
            @if ($isLive)
                <span class="live-note" title="Con un rango que incluye hoy, la página se vuelve a cargar sola cada minuto.">● En vivo · {{ $refreshedAt->format('H:i') }}</span>
            @endif
        </div>
    </form>

    <div class="an-grid">
    <div class="card t-orange span2">
        <h3>Descuentos: cuánto costaron y cómo se comparan</h3>
        @if ($all['count'] === 0)
            <p class="empty">Sin ventas en el rango.</p>
        @else
            <p class="legend" style="margin:0 0 10px;">{{ $total['count'] }} de {{ $all['count'] }} estadías ({{ $pct($total['count'] / max(1, $all['count'])) }}) usaron descuento, por {{ $money($total['discount']) }}: el {{ number_format($total['discount'] / max(1, $all['sale']) * 100, 1, ',', '.') }}% de la venta.</p>
            <table class="rank-table ch-table">
                <thead><tr><th>Descuento</th><th class="n">Estadías</th><th class="n">Descuento dado</th><th class="n">Ticket</th><th class="n">Minutos</th><th class="n">Extras por estadía</th><th class="n">% lun–jue</th><th class="n">Hace falta que sean nuevos</th></tr></thead>
                <tbody>
                @foreach ($byCoupon as $r)
                    @php $be = $r['net_room'] > 0 ? $r['discount'] / $r['net_room'] : 0; @endphp
                    <tr>
                        <td class="nm">{{ $r['label'] }}</td>
                        <td class="n">{{ $r['count'] }}</td>
                        <td class="n">{{ $money($r['discount']) }}</td>
                        <td class="n">{{ $money($r['ticket']) }}</td>
                        <td class="n">{{ $r['minutes'] }}</td>
                        <td class="n">{{ $money($r['extras_per_stay']) }}</td>
                        <td class="n">{{ $pct($r['weekday']) }}</td>
                        <td class="n">{{ number_format($be, 1, ',', '.') }} de {{ $r['count'] }} ({{ $pct(min(1, $be / max(1, $r['count']))) }})</td>
                    </tr>
                @endforeach
                <tr style="font-weight:700;">
                    <td class="nm">Todos los descuentos</td>
                    <td class="n">{{ $total['count'] }}</td>
                    <td class="n">{{ $money($total['discount']) }}</td>
                    <td class="n">{{ $money($total['ticket']) }}</td>
                    <td class="n">{{ $total['minutes'] }}</td>
                    <td class="n">{{ $money($total['extras_per_stay']) }}</td>
                    <td class="n">{{ $pct($total['weekday']) }}</td>
                    <td class="n">{{ $breakEven ? number_format($breakEven['stays'], 1, ',', '.').' de '.$total['count'].' ('.$pct($breakEven['share']).')' : '—' }}</td>
                </tr>
                @foreach ([['Sin descuento (todas)', $plain], ['Sin descuento · lun–jue', $plainWeekday]] as [$label, $s])
                    <tr style="opacity:.85;">
                        <td class="nm">{{ $label }}</td>
                        <td class="n">{{ $s['count'] }}</td>
                        <td class="n">—</td>
                        <td class="n">{{ $money($s['ticket']) }}</td>
                        <td class="n">{{ $s['minutes'] }}</td>
                        <td class="n">{{ $money($s['extras_per_stay']) }}</td>
                        <td class="n">{{ $pct($s['weekday']) }}</td>
                        <td class="n">—</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="legend" style="margin-top:8px;"><b>Hace falta que sean nuevos</b>: cuántas de esas estadías tendrían que ser clientes que no habrían venido sin el descuento para que este se pague solo (descuento dado ÷ lo que deja cada estadía). El sistema no sabe quién vino por el descuento: sirve para comparar, no para probar. <b>Sin descuento · lun–jue</b> es la comparación más justa para cupones que se usan entre semana. Con pocas estadías el promedio se mueve mucho.</p>
        @endif
    </div>
    </div>

    <div class="an-grid">
    <div class="card t-orange span2">
        <h3>Upsells vendidos</h3>
        <p class="legend" style="margin:0 0 10px;">{{ $upsellTotal['count'] }} {{ $upsellTotal['count'] === 1 ? 'venta' : 'ventas' }} por {{ $money($upsellTotal['amount']) }} ({{ number_format($upsellTotal['share'] * 100, 1, ',', '.') }}% de la venta del rango).</p>
        <table class="rank-table ch-table">
            <thead><tr><th>Upsell</th><th>Tipo</th><th class="n">Precio</th><th class="n">Vendidos</th><th class="n">Monto</th><th class="n">Estado</th></tr></thead>
            <tbody>
            @foreach ($upsellRows as $r)
                <tr>
                    <td class="nm">{{ $r['name'] }}</td>
                    <td>{{ $r['type'] }}</td>
                    <td class="n">{{ $money($r['price']) }}</td>
                    <td class="n">{{ $r['count'] }}@if ($r['estimated'] > 0) <small title="Antes de marcarlos se cuentan por nombre y precio">({{ $r['estimated'] }} estimados)</small>@endif</td>
                    <td class="n">{{ $money($r['amount']) }}</td>
                    <td class="n">{{ $r['active'] ? 'Activo' : 'Pausado' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="legend" style="margin-top:8px;">
            @if ($upsellMarkedSince)
                Los upsells se marcan exactos desde el {{ \Illuminate\Support\Carbon::parse($upsellMarkedSince)->timezone('America/Santiago')->format('d/m/Y') }}; antes se estiman por nombre y precio (un pack vendido suelto al mismo precio puede contarse como upsell).
            @else
                Desde ahora cada upsell aceptado queda marcado en la reserva; lo anterior se estima por nombre y precio (un pack vendido suelto al mismo precio puede contarse como upsell).
            @endif
            El sistema no registra cuántas veces se ofreció, así que todavía no hay tasa de aceptación.
        </p>
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
