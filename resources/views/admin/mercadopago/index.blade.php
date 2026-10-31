@extends('admin.layout')
@section('title', 'Mercado Pago')
@section('content')
    @php $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <style>
        .mp-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:14px; }
        .mp-kpi .k { color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
        .mp-kpi .v { font-size:22px; font-weight:800; margin-top:4px; }
        .mp-kpi .v.bad { color:#e88a9a; }
        .mp-alert { background:#3a1c22; border:1px solid #7a2d3a; color:#f3b8c2; border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:14px; }
        .mp-ok { color:#6fd39a; }
        .mp-bad { color:#e88a9a; font-weight:600; }
        tr.mp-missing td { background:#2a1a1e; }
        td.num, th.num { text-align:right; white-space:nowrap; }
        .mp-muted { color:#888; }
        .mp-nav { display:flex; gap:8px; align-items:center; }
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

        @if ($data['point_days']->isNotEmpty())
            <div class="card">
                <h2>Máquina vs. caja, por día</h2>
                <p class="sub" style="margin-bottom:8px;">Lo que cobró la máquina de Mercado Pago contra lo que recepción registró como débito o crédito ese día.</p>
                <table class="tbl-compact">
                    <thead><tr><th>Día</th><th class="num">Máquina (MP)</th><th class="num">Sistema (tarjeta)</th><th class="num">Diferencia</th></tr></thead>
                    <tbody>
                        @foreach ($data['point_days'] as $d)
                            @php $diff = $d['machine'] - $d['system']; @endphp
                            <tr>
                                <td><a class="link" href="{{ route('cash.show', $d['day']->toDateString()) }}">{{ ucfirst($d['day']->locale('es')->isoFormat('ddd D')) }}</a></td>
                                <td class="num">{{ $money($d['machine']) }}</td>
                                <td class="num">{{ $money($d['system']) }}</td>
                                <td class="num {{ $diff === 0 ? 'mp-ok' : 'mp-bad' }}">{{ $diff === 0 ? 'Cuadra' : ($diff > 0 ? '+' : '−').$money(abs($diff)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card">
            <h2>Pagos en Mercado Pago</h2>
            @if ($data['rows']->isEmpty())
                <p class="sub" style="margin:0;">No hay pagos este mes.</p>
            @else
                <div style="overflow-x:auto;">
                <table class="tbl-compact">
                    <thead><tr><th>Fecha</th><th>Estado</th><th>Canal</th><th>Medio</th><th>Reserva</th><th class="num">Monto</th><th class="num">Comisión</th><th class="num">Neto</th><th>En el sistema</th><th>N° MP</th></tr></thead>
                    <tbody>
                        @foreach ($data['rows'] as $r)
                            <tr class="{{ $r['missing'] ? 'mp-missing' : '' }}">
                                <td style="white-space:nowrap;">{{ $r['at']->format('d/m H:i') }}</td>
                                <td><span class="pill {{ $r['status'] === 'approved' ? '' : ($r['status'] === 'rejected' ? 'pill-bad' : 'pill-warn') }}">{{ $r['status_label'] }}</span></td>
                                <td>{{ $r['channel'] }}</td>
                                <td>{{ $r['type'] }}</td>
                                <td>
                                    @if ($r['booking'])<a class="link" href="{{ route('reservations.show', $r['booking']->code) }}">{{ $r['booking']->code }}</a>
                                    @elseif ($r['code'])<span class="mp-muted">{{ $r['code'] }}</span>
                                    @else<span class="mp-muted">—</span>@endif
                                </td>
                                <td class="num">{{ $money($r['amount']) }}</td>
                                <td class="num mp-muted">{{ $r['fee'] ? $money($r['fee']) : '—' }}</td>
                                <td class="num">{{ $money($r['net']) }}</td>
                                <td>
                                    @if ($r['in_system'])<span class="mp-ok">✓ Registrado</span>
                                    @elseif ($r['missing'])<span class="mp-bad">⚠ Falta</span>
                                    @else<span class="mp-muted">—</span>@endif
                                </td>
                                <td class="mp-muted" style="font-family:ui-monospace,monospace; font-size:12px;">{{ $r['id'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

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
