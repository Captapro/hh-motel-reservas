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
                            <td><a href="{{ route('contador.conciliacion', ['mes' => $d['day']->format('Y-m'), 'dia' => $d['day']->toDateString()]) }}#detalle">{{ ucfirst($d['day']->locale('es')->isoFormat('ddd D/MM')) }}</a></td>
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

        @if ($day)
            @php $dayDate = \Illuminate\Support\Carbon::parse($day, 'America/Santiago'); $ok = $dayRows->where('status', 'approved')->where('channel', '!=', 'Otro'); @endphp
            <div class="card" id="detalle">
                <h2>Detalle del día · {{ ucfirst($dayDate->locale('es')->isoFormat('dddd D [de] MMMM')) }}</h2>
                <p class="muted" style="margin:0 0 10px;">{{ $dayRows->count() }} movimiento(s) · cobrado {{ $money($ok->sum('amount')) }} · comisión {{ $money($ok->sum('fee')) }} · neto {{ $money($ok->sum('net')) }}. Aprieta una fecha del cuadre de arriba para ver otro día.</p>
                <table>
                    <thead><tr><th>N° voucher</th><th>Hora</th><th>Estado</th><th>Canal</th><th>Medio</th><th>Habitación</th><th>Reserva / referencia</th><th class="num">Monto</th><th class="num">Comisión</th><th class="num">Neto</th><th>En el sistema</th><th>N° operación</th><th>Cód. autorización</th></tr></thead>
                    <tbody>
                        @foreach ($dayRows as $r)
                            <tr class="{{ $r['status'] === 'approved' && $r['point'] && ! $r['in_system'] ? 'flag' : '' }}" @if ($r['channel'] === 'Reserva online') style="background:#fff7ed;" @endif>
                                <td>{{ $r['correlative'] ?? '—' }}</td>
                                <td>{{ $r['at']->format('H:i') }}</td>
                                <td>{{ $r['status_label'] }}</td>
                                <td>@if ($r['channel'] === 'Reserva online')<span style="display:inline-block;background:var(--accent);color:#fff;font-weight:800;font-size:12px;padding:3px 10px;border-radius:999px;white-space:nowrap;">Reserva online</span>@else{{ $r['channel'] }}@endif</td>
                                <td>{{ $r['type'] }}</td>
                                <td>{{ $r['rooms'] ?? '—' }}</td>
                                <td>{{ $r['code'] !== '' ? $r['code'] : '—' }}</td>
                                <td class="num">{{ $money($r['amount']) }}</td>
                                <td class="num">{{ $r['status'] === 'approved' ? $money($r['fee']) : '—' }}</td>
                                <td class="num">{{ $r['status'] === 'approved' ? $money($r['net']) : '—' }}</td>
                                <td>@if ($r['status'] !== 'approved')—@elseif ($r['in_system'])<span class="ok">✓ Registrado</span>@elseif ($r['channel'] === 'Otro')<span class="muted">No es del motel</span>@else<span class="bad">No registrado{{ $r['deleted_booking'] ? ' (reserva borrada)' : '' }}</span>@endif</td>
                                <td>{{ $r['id'] }}</td>
                                <td>{{ $r['auth'] !== '' ? $r['auth'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="note">El detalle de cada movimiento de Mercado Pago del mes (N° de operación, comisión, neto, reserva) se descarga con «Movimientos MP (CSV)».</div>
    @endif
@endsection
