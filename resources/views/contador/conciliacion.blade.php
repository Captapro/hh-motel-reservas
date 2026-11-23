@extends('contador.layout')
@section('title', 'Conciliación Mercado Pago')
@section('content')
    @php $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.'); @endphp
    @include('contador._month', ['heading' => 'Conciliación Mercado Pago', 'lead' => 'Lo que dice la cuenta de Mercado Pago del motel contra lo registrado en el sistema.', 'routeName' => 'contador.conciliacion', 'exports' => $data ? [['Movimientos MP (CSV)', 'movimientos'], ['Cuadre diario (CSV)', 'dias']] : []])

    @if ($error)
        <div class="alert">{{ $error }}</div>
    @else
        @php $t = $data['totals']; @endphp
        @if ($t['missing'] > 0 || $data['missing_in_mp']->isNotEmpty())
            <div class="alert">
                ⚠ Hay diferencias:
                @if ($t['missing'] > 0) {{ $t['missing'] }} pago(s) aprobados en Mercado Pago que no están en el sistema. @endif
                @if ($data['missing_in_mp']->isNotEmpty()) {{ $data['missing_in_mp']->count() }} pago(s) del sistema que no aparecen en Mercado Pago este mes. @endif
            </div>
        @endif

        <div class="kpis">
            <div class="kpi"><div class="k">Cobrado (bruto)</div><div class="v">{{ $money($t['gross']) }}</div><div class="n">{{ $t['count'] }} pago(s)</div></div>
            <div class="kpi"><div class="k">Comisiones MP</div><div class="v">{{ $money($t['fees']) }}</div></div>
            <div class="kpi"><div class="k">Neto recibido</div><div class="v">{{ $money($t['net']) }}</div></div>
            <div class="kpi"><div class="k">Reservas online</div><div class="v">{{ $money($t['online']) }}</div></div>
            <div class="kpi"><div class="k">Máquina (Point)</div><div class="v">{{ $money($t['point']) }}</div></div>
        </div>

        <div class="card">
            <h2>Cuadre día por día · máquina contra tarjeta anotada en el sistema</h2>
            <table>
                <thead><tr><th>Fecha</th><th class="num">Mov. MP</th><th class="num">Bruto MP</th><th class="num">Neto MP</th><th class="num">Máquina</th><th class="num">Sistema (tarjeta)</th><th class="num">Diferencia</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($data['days'] as $d)
                        @php $diff = $d['machine'] - $d['system']; @endphp
                        <tr class="{{ $diff !== 0 && ! $d['compensated_with'] ? 'flag' : '' }}">
                            <td>{{ ucfirst($d['day']->locale('es')->isoFormat('ddd D/MM')) }}</td>
                            <td class="num">{{ $d['count'] }}</td><td class="num">{{ $money($d['gross']) }}</td><td class="num">{{ $money($d['net']) }}</td>
                            <td class="num">{{ $money($d['machine']) }}</td><td class="num">{{ $money($d['system']) }}</td>
                            <td class="num">{{ $diff === 0 ? '—' : $money($diff) }}</td>
                            <td>@if ($diff === 0)<span class="ok">Cuadra</span>@elseif ($d['compensated_with'])<span class="muted">Compensada con {{ $d['compensated_with']->format('d/m') }}</span>@else<span class="bad">Diferencia</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">Sin movimientos este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($data['missing_in_mp']->isNotEmpty())
            <div class="card">
                <h2>En el sistema pero no en Mercado Pago</h2>
                <table>
                    <thead><tr><th>Fecha</th><th>Reserva</th><th>Referencia</th><th class="num">Monto</th></tr></thead>
                    <tbody>
                        @foreach ($data['missing_in_mp'] as $p)
                            <tr class="flag"><td>{{ $p->created_at->copy()->timezone('America/Santiago')->format('d/m H:i') }}</td><td>{{ $p->booking?->code }}</td><td>{{ $p->external_id }}</td><td class="num">{{ $money($p->amount) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="note">El detalle de cada movimiento de Mercado Pago del mes (N° de operación, comisión, neto, reserva) se descarga con «Movimientos MP (CSV)».</div>
    @endif
@endsection
