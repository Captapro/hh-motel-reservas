@extends('contador.layout')
@section('title', 'Libro de ventas')
@section('content')
    @php $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.'); @endphp
    @include('contador._month', ['heading' => 'Libro de ventas', 'lead' => 'Una fila por Playroom vendido y pagado completo, con descuento, extras, documento y medio de pago.', 'routeName' => 'contador.ventas', 'exports' => [['Libro de ventas (CSV)', 'csv']]])
    <div class="card">
        <table>
            <thead><tr><th>Fecha</th><th>Reserva</th><th>Cliente</th><th>RUT / doc.</th><th>Playroom</th><th class="num">Alojamiento</th><th class="num">Desc.</th><th class="num">Extras</th><th class="num">Total</th><th>Medio</th><th>Voucher / boleta</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r['date']->format('d/m H:i') }}</td>
                        <td><a href="{{ route('contador.reserva', $r['code']) }}" style="color:var(--accent);">{{ $r['code'] }}</a></td>
                        <td>{{ $r['customer'] }}</td>
                        <td class="muted">{{ $r['doc'] ?: '—' }}</td>
                        <td>{{ $r['room'] }} <span class="muted">· {{ $r['category'] }}</span></td>
                        <td class="num">{{ $money($r['lodging']) }}</td>
                        <td class="num muted">{{ $r['discount'] ? $money($r['discount']) : '' }}{{ $r['coupon'] ? ' '.$r['coupon'] : '' }}</td>
                        <td class="num">{{ $money($r['extras']) }}</td>
                        <td class="num"><b>{{ $money($r['total']) }}</b></td>
                        <td>{{ $r['methods'] }}</td>
                        <td>{{ trim($r['vouchers'].' '.$r['receipts']) ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">Sin ventas este mes.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot><tr><td colspan="5">Total · {{ $rows->count() }} venta(s)</td><td class="num">{{ $money($rows->sum('lodging')) }}</td><td></td><td class="num">{{ $money($rows->sum('extras')) }}</td><td class="num">{{ $money($rows->sum('total')) }}</td><td colspan="2"></td></tr></tfoot>
            @endif
        </table>
    </div>
@endsection
