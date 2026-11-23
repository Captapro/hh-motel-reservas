@extends('contador.layout')
@section('title', 'Resumen')
@section('content')
    @php $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.'); @endphp
    @include('contador._month', ['heading' => 'Resumen', 'lead' => 'Venta y cobros del mes. La venta es por fecha de la estadía; lo cobrado, por fecha de pago.', 'routeName' => 'contador.index', 'exports' => [['Resumen diario (CSV)', 'csv']]])

    @if ($noDoc > 0)
        <div class="alert">⚠ {{ $noDoc }} cobro(s) con tarjeta/efectivo/transferencia no tienen N° de voucher ni de boleta. Están marcados en el <a href="{{ route('contador.pagos', ['mes' => $first->format('Y-m')]) }}">libro de cobros</a>.</div>
    @endif

    <div class="kpis">
        <div class="kpi"><div class="k">Venta total</div><div class="v">{{ $money($sold) }}</div><div class="n">{{ $rooms }} Playroom(s) pagados</div></div>
        <div class="kpi"><div class="k">Alojamiento</div><div class="v">{{ $money($lodging) }}</div><div class="n">descuentos aplicados {{ $money($discounts) }}</div></div>
        <div class="kpi"><div class="k">Extras y consumos</div><div class="v">{{ $money($extras) }}</div></div>
        <div class="kpi"><div class="k">Cobrado en el mes</div><div class="v">{{ $money($collected) }}</div><div class="n">pagos aprobados</div></div>
        <div class="kpi"><div class="k">Devoluciones</div><div class="v">{{ $money($refunded) }}</div><div class="n">{{ $refundCount }} devolución(es)</div></div>
        <div class="kpi"><div class="k">IVA contenido (ref.)</div><div class="v">{{ $money($iva) }}</div><div class="n">neto {{ $money($net) }} · 19 % sobre venta total</div></div>
    </div>

    <div class="note">El IVA es referencial (19 % incluido en el precio). La fuente tributaria son las boletas emitidas. Las planillas se descargan en CSV separado por «;» para abrir directo en Excel.</div>

    <div class="card">
        <h2>Cobros por medio de pago</h2>
        <table>
            <thead><tr><th>Medio</th><th class="num">Cobros</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @forelse ($byMethod as $m)
                    <tr><td>{{ $m['label'] }}</td><td class="num">{{ $m['count'] }}</td><td class="num">{{ $money($m['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">Sin cobros este mes.</td></tr>
                @endforelse
            </tbody>
            @if ($byMethod->isNotEmpty())<tfoot><tr><td>Total</td><td class="num">{{ $byMethod->sum('count') }}</td><td class="num">{{ $money($collected) }}</td></tr></tfoot>@endif
        </table>
    </div>

    <div class="card">
        <h2>Día por día</h2>
        <table>
            <thead><tr><th>Fecha</th><th class="num">Playrooms</th><th class="num">Alojamiento</th><th class="num">Extras</th><th class="num">Venta</th><th class="num">Cobrado</th></tr></thead>
            <tbody>
                @forelse ($daily as $r)
                    <tr>
                        <td>{{ ucfirst($r['date']->locale('es')->isoFormat('ddd D/MM')) }}</td>
                        <td class="num">{{ $r['rooms'] }}</td><td class="num">{{ $money($r['lodging']) }}</td><td class="num">{{ $money($r['extras']) }}</td>
                        <td class="num">{{ $money($r['lodging'] + $r['extras']) }}</td><td class="num">{{ $money($r['collected']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Sin movimientos este mes.</td></tr>
                @endforelse
            </tbody>
            @if ($daily->isNotEmpty())<tfoot><tr><td>Total</td><td class="num">{{ $rooms }}</td><td class="num">{{ $money($lodging) }}</td><td class="num">{{ $money($extras) }}</td><td class="num">{{ $money($sold) }}</td><td class="num">{{ $money($collected) }}</td></tr></tfoot>@endif
        </table>
    </div>
@endsection
