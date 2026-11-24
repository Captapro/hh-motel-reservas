@extends('contador.layout')
@section('title', 'Libro de ventas')
@section('content')
    @php
        $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.');
        $pill = ['boleta' => ['Con boleta', 'ok'], 'voucher' => ['Solo voucher', 'muted'], 'sin' => ['Sin documento', 'bad']];
    @endphp
    @include('contador._month', ['heading' => 'Libro de ventas', 'lead' => 'Una fila por Playroom vendido y pagado completo, con descuento, extras y, por cada pago, su medio, voucher y boleta.', 'routeName' => 'contador.ventas', 'exports' => [['Libro de ventas (CSV)', 'csv'], ['Boletas por pago (CSV)', 'boletas']]])

    <div class="kpis">
        @foreach ($summary as $key => $g)
            <a class="kpi" href="{{ route('contador.ventas', ['mes' => $first->format('Y-m'), 'doc' => $filter === $key ? null : $key]) }}" style="text-decoration:none;color:inherit;{{ $filter === $key ? 'outline:2px solid var(--accent);' : '' }}">
                <div class="k">{{ $g['label'] }}</div>
                <div class="v {{ $key === 'sin' && $g['count'] ? 'bad' : '' }}">{{ $g['count'] }} venta(s)</div>
                <div class="n">{{ $money($g['total']) }}</div>
            </a>
        @endforeach
    </div>
    @if ($filter)
        <div class="note">Mostrando solo: <b>{{ $summary[$filter]['label'] }}</b>. <a href="{{ route('contador.ventas', ['mes' => $first->format('Y-m')]) }}">Ver todas</a></div>
    @endif
    <div class="note">«Con boleta» es el pago que tiene N° de boleta anotado. «Solo voucher» es el cobro con tarjeta que lleva voucher de la máquina pero no boleta; si es válido como boleta depende del modelo de emisión que el motel tiene declarado en el SII. «Sin documento» no tiene ni voucher ni boleta (efectivo o transferencia sin boleta anotada).</div>

    <div class="card">
        <table>
            <thead><tr><th>Fecha</th><th>Reserva</th><th>Cliente</th><th>RUT / doc.</th><th>Playroom</th><th class="num">Alojamiento</th><th class="num">Desc.</th><th class="num">Extras</th><th class="num">Total</th><th>Pagos · voucher · boleta</th><th>Documento</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td style="white-space:nowrap;">{{ $r['date']->format('d/m H:i') }}</td>
                        <td style="white-space:nowrap;"><a href="{{ route('contador.reserva', $r['code']) }}" style="color:var(--accent);">{{ $r['code'] }}</a></td>
                        <td>{{ $r['customer'] }}</td>
                        <td class="muted" style="white-space:nowrap;">{{ $r['doc'] ?: '—' }}</td>
                        <td>{{ $r['room'] }} <span class="muted">· {{ $r['category'] }}</span></td>
                        <td class="num">{{ $money($r['lodging']) }}</td>
                        <td class="num muted">{{ $r['discount'] ? $money($r['discount']) : '' }}{{ $r['coupon'] ? ' '.$r['coupon'] : '' }}</td>
                        <td class="num">{{ $money($r['extras']) }}</td>
                        <td class="num"><b>{{ $money($r['total']) }}</b></td>
                        <td style="min-width:230px;">
                            @foreach ($r['lines'] as $l)
                                <div style="padding:3px 0;{{ ! $loop->last ? 'border-bottom:1px dashed var(--line);' : '' }}">
                                    <div>{{ $l['method'] }} <b>{{ $money($l['amount']) }}</b></div>
                                    <div class="muted" style="font-size:12px;">voucher {{ $l['voucher'] ?: '—' }} · <span class="{{ $l['receipt'] ? 'ok' : 'bad' }}">boleta {{ $l['receipt'] ?: 'falta' }}</span></div>
                                </div>
                            @endforeach
                        </td>
                        <td><span class="{{ $pill[$r['docstate']][1] }}">{{ $pill[$r['docstate']][0] }}</span></td>
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
