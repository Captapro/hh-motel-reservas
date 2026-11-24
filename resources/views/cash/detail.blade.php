@extends('admin.layout')
@section('title', 'Detalle de ventas del día')
@section('content')
    @php $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <style>
        .dd-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:12px; margin-bottom:14px; }
        .dd-kpi .k { color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
        .dd-kpi .v { font-size:22px; font-weight:800; margin-top:4px; }
        .dd-nav { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .dd-note { background:rgba(255,121,24,.10); border:1px solid rgba(255,121,24,.4); border-radius:10px; padding:11px 14px; margin-bottom:14px; font-size:13.5px; line-height:1.5; }
        td.num, th.num { text-align:right; white-space:nowrap; }
        .dd-muted { color:#888; }
        .dd-tag { display:inline-block; background:#2f2f2f; color:#ddd; font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; }
        .dd-tag.pre { background:#ff7918; color:#fff; }
        tr.dd-first > td { border-top:2px solid #333; }
    </style>

    <div class="page-head">
        <div>
            <h1>Detalle de ventas · {{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM YYYY')) }}</h1>
            <p class="sub">Sacado del sistema, por día de la estadía (check-in): lo mismo que va en la hoja «Detalle de Ventas» de la planilla.</p>
        </div>
        <div class="dd-nav">
            <a class="btn secondary" href="{{ route('cash.detail', ['date' => $prev]) }}">← Día anterior</a>
            @unless ($isToday)<a class="btn secondary" href="{{ route('cash.detail', ['date' => $next]) }}">Día siguiente →</a>@endunless
            <a class="btn" href="{{ route('cash.detail', ['date' => $day->format('Y-m-d'), 'export' => 'csv']) }}">⬇ Descargar (CSV)</a>
            <a class="btn secondary" href="{{ route('cash.show', ['date' => $day->format('Y-m-d')]) }}">Ver caja del día</a>
        </div>
    </div>

    <div class="dd-note">
        <strong>Cómo usarlo:</strong> descarga el CSV y pégalo en la planilla (abre en Excel o Google Sheets), o copia las filas desde esta tabla.
        Cada fila es un cobro. Las reservas online aparecen el día de la estadía, aunque se hayan pagado antes
        (etiqueta <span class="dd-tag pre">pagado otro día</span>). Los montos con tarjeta coinciden con la máquina de Mercado Pago:
        el sistema está conectado con ella y se cruza en Admin → Mercado Pago.
    </div>

    <div class="dd-kpis">
        <div class="card dd-kpi"><div class="k">Estadías del día</div><div class="v">{{ $stays }}</div></div>
        <div class="card dd-kpi"><div class="k">Vendido (alojamiento + extras)</div><div class="v">{{ $money($staysTotal) }}</div></div>
        <div class="card dd-kpi"><div class="k">Cobrado por estas estadías</div><div class="v">{{ $money($collectedTotal) }}</div></div>
        @foreach ($byMethod as $g)
            <div class="card dd-kpi"><div class="k">{{ $g['label'] }} · {{ $g['count'] }}</div><div class="v">{{ $money($g['total']) }}</div></div>
        @endforeach
    </div>

    <div class="card" style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Ingreso</th><th>Reserva</th><th>Cliente</th><th>Playroom</th>
                    <th class="num">Alojamiento</th><th class="num">Extras</th><th class="num">Total</th>
                    <th>Medio de pago</th><th class="num">Cobrado</th><th>Voucher</th><th>Boleta</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr class="{{ $r['first'] ? 'dd-first' : '' }}">
                    <td>{{ $r['first'] ? $r['start']->format('H:i') : '' }}</td>
                    <td>{{ $r['first'] ? $r['code'] : '' }}</td>
                    <td>{{ $r['first'] ? $r['customer'] : '' }}</td>
                    <td>{{ $r['first'] ? $r['room'] : '' }}</td>
                    <td class="num">{{ $r['first'] ? $money($r['lodging']) : '' }}</td>
                    <td class="num">{{ $r['first'] ? $money($r['extras']) : '' }}</td>
                    <td class="num">{{ $r['first'] ? $money($r['total']) : '' }}</td>
                    <td>{{ $r['method'] }}@if ($r['prepaid']) <span class="dd-tag pre" title="Cobrado el {{ $r['paid_at']->format('d/m H:i') }}">pagado otro día · {{ $r['paid_at']->format('d/m') }}</span>@endif</td>
                    <td class="num">{{ $r['amount'] ? $money($r['amount']) : '' }}</td>
                    <td>{{ $r['voucher'] }}</td>
                    <td>{{ $r['receipt'] }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="dd-muted">No hay estadías con check-in este día.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($otherDays->isNotEmpty())
        <div class="card" style="overflow-x:auto">
            <h2>Cobrado este día por estadías de otra fecha</h2>
            <p class="sub">Entraron al sistema hoy, pero su estadía no es de hoy (reservas online pagadas antes del check-in, saldos de días anteriores). No suman arriba.</p>
            <table>
                <thead><tr><th>Hora</th><th>Reserva</th><th>Cliente</th><th>Playroom</th><th>Medio de pago</th><th class="num">Monto</th><th>Voucher</th></tr></thead>
                <tbody>
                @foreach ($otherDays as $p)
                    <tr>
                        <td>{{ $p->created_at->timezone('America/Santiago')->format('H:i') }}</td>
                        <td>{{ $p->booking?->code }}</td>
                        <td>{{ $p->booking?->customer?->name }}</td>
                        <td>{{ $p->booking?->room?->name }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td class="num">{{ $money($p->amount) }}</td>
                        <td>{{ $p->voucher_number }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
