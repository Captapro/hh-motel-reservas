@extends('admin.layout')
@section('title', 'Mercado Pago')
@section('content')
    @php $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <style>
        .mp-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:14px; }
        .mp-kpi .k { color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
        .mp-kpi .v { font-size:22px; font-weight:800; margin-top:4px; }
        .mp-kpi .v.bad { color:#d0384f; }
        .mp-alert { background:rgba(232,90,110,.12); border:1px solid rgba(232,90,110,.5); color:#c0394f; border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:14px; }
        .mp-ok { color:#1f9d55; }
        .mp-bad { color:#d0384f; font-weight:600; }
        tr.mp-missing td { background:rgba(232,90,110,.12); }
        td.num, th.num { text-align:right; white-space:nowrap; }
        .mp-muted { color:#888; }
        .mp-nav { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .mp-days tbody tr:hover td { background:rgba(127,127,127,.10); }
        tr.mp-current td { background:rgba(255,121,24,.12); font-weight:600; }
    </style>

    <div class="page-head">
        <div>
            <h1>Mercado Pago · {{ ucfirst($first->locale('es')->isoFormat('MMMM YYYY')) }}</h1>
            <p class="sub">Leído directo de la cuenta de Mercado Pago del motel y cruzado con lo registrado en el sistema.</p>
        </div>
        <div class="mp-nav">
            <a class="btn secondary" href="{{ route('admin.mercadopago.index', ['mes' => $prev]) }}">← Mes anterior</a>
            @unless ($isCurrent)<a class="btn secondary" href="{{ route('admin.mercadopago.index', ['mes' => $next]) }}">Mes siguiente →</a>@endunless
        </div>
    </div>

    @if ($error)
        <div class="errors">{{ $error }}</div>
    @else
        @php $t = $data['totals']; @endphp
        @if ($t['missing'] > 0 || $data['missing_in_mp']->isNotEmpty())
            <div class="mp-alert">
                ⚠ Hay diferencias:
                @if ($t['missing'] > 0) {{ $t['missing'] }} pago(s) aprobados en Mercado Pago que no están en el sistema (marcados en rojo abajo).@endif
                @if ($data['missing_in_mp']->isNotEmpty()) {{ $data['missing_in_mp']->count() }} pago(s) del sistema que no aparecen en Mercado Pago este mes.@endif
            </div>
        @endif

        <div class="mp-kpis">
            <div class="card mp-kpi"><div class="k">Cobrado (aprobado)</div><div class="v">{{ $money($t['gross']) }}</div><div class="mp-muted">{{ $t['count'] }} pago(s)</div></div>
            <div class="card mp-kpi"><div class="k">Comisiones MP</div><div class="v">{{ $money($t['fees']) }}</div></div>
            <div class="card mp-kpi"><div class="k">Neto que recibes</div><div class="v">{{ $money($t['net']) }}</div></div>
            <div class="card mp-kpi"><div class="k">Reservas online</div><div class="v">{{ $money($t['online']) }}</div></div>
            <div class="card mp-kpi"><div class="k">Máquina (Point)</div><div class="v">{{ $money($t['point']) }}</div></div>
            <div class="card mp-kpi"><div class="k">Faltan en el sistema</div><div class="v {{ $t['missing'] ? 'bad' : '' }}">{{ $t['missing'] }}</div></div>
        </div>

        <div class="card">
            <div class="cols-h" style="flex-wrap:wrap;">
                <h2>Detalle del día · {{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }}</h2>
                <div class="mp-nav">
                    <a class="link" href="{{ route('admin.mercadopago.index', ['mes' => $day->copy()->subDay()->format('Y-m'), 'dia' => $day->copy()->subDay()->toDateString()]) }}">← Día anterior</a>
                    <input type="date" value="{{ $day->toDateString() }}" max="{{ now('America/Santiago')->toDateString() }}" style="width:auto; padding:6px 9px; font-size:13.5px;"
                        onchange="if (this.value) location.href='{{ route('admin.mercadopago.index') }}?mes=' + this.value.slice(0, 7) + '&dia=' + this.value" aria-label="Elegir día">
                    @if ($day->toDateString() < now('America/Santiago')->toDateString())
                        <a class="link" href="{{ route('admin.mercadopago.index', ['mes' => $day->copy()->addDay()->format('Y-m'), 'dia' => $day->copy()->addDay()->toDateString()]) }}">Día siguiente →</a>
                    @endif
                </div>
            </div>
            @php $approvedRows = $data['rows']->where('status', 'approved'); @endphp
            <p class="sub" style="margin:0 0 8px;">{{ $data['rows']->count() }} movimiento(s) · aprobado {{ $money($approvedRows->sum('amount')) }} · neto {{ $money($approvedRows->sum('net')) }} · <a class="link" href="{{ route('cash.show', $day->toDateString()) }}">ver caja del día</a></p>
            @if ($data['rows']->isEmpty())
                <p class="sub" style="margin:0;">No hay pagos ese día.</p>
            @else
                <div style="overflow-x:auto;">
                <table class="tbl-compact">
                    <thead><tr><th>N° voucher</th><th>Hora</th><th>Estado</th><th>Canal</th><th>Medio</th><th>Habitación</th><th>Reserva / referencia</th><th class="num">Monto</th><th class="num">Comisión</th><th class="num">Neto</th><th>En el sistema</th><th>N° operación</th><th>Cód. autorización</th></tr></thead>
                    <tbody>
                        @foreach ($data['rows'] as $r)
                            <tr class="{{ $r['missing'] ? 'mp-missing' : '' }}">
                                <td style="font-weight:700; font-family:ui-monospace,monospace;">{{ $r['correlative'] ?? '' }}</td>
                                <td style="white-space:nowrap;">{{ $r['at']->format('H:i') }}</td>
                                <td><span class="pill {{ $r['status'] === 'approved' ? '' : ($r['status'] === 'rejected' ? 'pill-bad' : 'pill-warn') }}">{{ $r['status_label'] }}</span></td>
                                <td>{{ $r['channel'] }}</td>
                                <td>{{ $r['type'] }}</td>
                                <td style="font-weight:700; white-space:nowrap;">{{ $r['rooms'] ?? '—' }}</td>
                                <td>
                                    @if ($r['booking'])<a class="link" href="{{ route('reservations.show', $r['booking']->code) }}">{{ $r['booking']->code }}</a>
                                    @elseif ($r['code'])<span class="mp-muted">{{ $r['code'] }}</span>
                                    @else<span class="mp-muted">—</span>@endif
                                </td>
                                <td class="num">{{ $money($r['amount']) }}</td>
                                <td class="num mp-muted">{{ $r['fee'] ? $money($r['fee']) : '—' }}</td>
                                <td class="num">{{ $money($r['net']) }}</td>
                                <td>
                                    @if ($r['in_system'])<span class="mp-ok">✓ Registrado</span>@if ($r['voucher'])<br><span class="mp-muted" style="font-size:12px;">voucher N° {{ $r['voucher'] }}</span>@endif
                                    @elseif ($r['missing'])<span class="mp-bad">⚠ Falta</span>
                                    @elseif ($r['deleted_booking'])<span class="mp-muted">Reserva borrada</span>
                                    @else<span class="mp-muted">—</span>@endif
                                </td>
                                <td class="mp-muted" style="font-family:ui-monospace,monospace; font-size:12px;">{{ $r['id'] }}</td>
                                <td class="mp-muted" style="font-family:ui-monospace,monospace; font-size:12px;">{{ $r['auth'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        @if ($data['days']->isNotEmpty())
            <details class="card mp-days">
                <summary style="cursor:pointer; font-weight:600; font-size:14.5px;">Días del mes ({{ $data['days']->count() }}) — aprieta un día para ver su detalle</summary>
                <p class="sub" style="margin:8px 0;">"Máquina" es lo que cobró la máquina de Mercado Pago; "Caja (tarjeta)" es lo que recepción registró como débito o crédito ese día. Hasta octubre la caja se llevaba en planilla, por eso aparece en $0; desde el 1 de noviembre se cuadra acá.</p>
                <table class="tbl-compact">
                    <thead><tr><th>Día</th><th class="num">Movimientos</th><th class="num">Cobrado</th><th class="num">Neto</th><th class="num">Máquina</th><th class="num">Caja (tarjeta)</th><th class="num">Diferencia</th></tr></thead>
                    <tbody>
                        @foreach ($data['days'] as $d)
                            @php $diff = $d['machine'] - $d['system']; $dayUrl = route('admin.mercadopago.index', ['mes' => $first->format('Y-m'), 'dia' => $d['day']->toDateString()]); @endphp
                            <tr class="{{ $d['day']->isSameDay($day) ? 'mp-current' : '' }}" onclick="location.href='{{ $dayUrl }}'" style="cursor:pointer;">
                                <td><a class="link" href="{{ $dayUrl }}">{{ ucfirst($d['day']->locale('es')->isoFormat('ddd D')) }}</a></td>
                                <td class="num">{{ $d['count'] }}</td>
                                <td class="num">{{ $money($d['gross']) }}</td>
                                <td class="num">{{ $money($d['net']) }}</td>
                                <td class="num">{{ $money($d['machine']) }}</td>
                                <td class="num">{{ $money($d['system']) }}</td>
                                <td class="num {{ $diff === 0 ? 'mp-ok' : 'mp-bad' }}">{{ $diff === 0 ? 'Cuadra' : ($diff > 0 ? '+' : '−').$money(abs($diff)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        @endif

        @if ($data['missing_in_mp']->isNotEmpty())
            <div class="card">
                <h2>Registrados en el sistema pero no en Mercado Pago</h2>
                <table class="tbl-compact">
                    <thead><tr><th>Fecha</th><th>Reserva</th><th class="num">Monto</th><th>Referencia</th></tr></thead>
                    <tbody>
                        @foreach ($data['missing_in_mp'] as $p)
                            <tr>
                                <td>{{ $p->created_at->timezone('America/Santiago')->format('d/m H:i') }}</td>
                                <td>@if ($p->booking)<a class="link" href="{{ route('reservations.show', $p->booking->code) }}">{{ $p->booking->code }}</a>@endif</td>
                                <td class="num">{{ $money($p->amount) }}</td>
                                <td class="mp-muted">{{ $p->external_id }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
