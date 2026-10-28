<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Historial de ventas — {{ $first->locale('es')->isoFormat('MMMM YYYY') }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; text-transform:capitalize; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 22px; }
        .sub a, a.lnk { color:#ff7918; text-decoration:none; }
        .nav-day { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day a.primary { background:#ff7918; border-color:#ff7918; color:#111; font-weight:600; }
        .sec-label { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#888; margin:30px 0 8px; padding-top:18px; border-top:1px solid #232323; }
        .sec-note { font-size:12.5px; color:#999; margin:0 0 12px; }
        .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:8px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; }
        .card .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        .card .value { font-size:22px; font-family: ui-monospace, monospace; font-weight:600; }
        .card.green .value { color:#6fd39a; } .card.blue .value { color:#7fbcdc; } .card.gold .value { color:#e8c76f; }
        .table-wrap { overflow-x:auto; background:#1c1c1c; border:1px solid #333; border-radius:10px; margin-bottom:14px; }
        table { width:100%; border-collapse: collapse; font-size:13.5px; }
        th { text-align:left; color:#888; font-weight:500; font-size:10.5px; text-transform:uppercase; padding:9px 10px; border-bottom:1px solid #333; white-space:nowrap; }
        td { padding:8px 10px; border-bottom:1px solid #242424; white-space:nowrap; }
        th.n, td.n { text-align:right; font-variant-numeric: tabular-nums; }
        tr:last-child td { border-bottom:none; }
        tr.zero td { color:#555; }
        tr.total td { font-weight:700; color:#fff; background:#202020; border-top:1px solid #444; }
        tr.today td { background:#241419; }
        tr.today td:first-child { color:#ff7918; font-weight:700; }
        tr.cur-month td { background:#241419; }
        tr.weekend td:first-child { color:#e8c76f; }
        td a { color:#ff7918; text-decoration:none; }
        .bar { display:inline-block; height:7px; background:#ff7918; border-radius:4px; vertical-align:middle; margin-left:6px; opacity:.8; }
        .miss { display:inline-block; font-size:10px; color:#e8c76f; background:#2a2410; border:1px solid #6b5a1e; border-radius:20px; padding:1px 7px; }
        .ok { color:#6fd39a; }
        .filter { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:10px; font-size:13px; color:#bbb; }
        .filter label { cursor:pointer; }
        tr.extra td { color:#9aa; }
        tr.extra td:first-child { padding-left:18px; }
        .scroll-detail { max-height:640px; overflow:auto; }
        .scroll-detail thead th { position:sticky; top:0; background:#1c1c1c; z-index:1; }
        .grid2 { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        @media (max-width: 860px) { .grid2 { grid-template-columns: minmax(0, 1fr); } }
        /* Cuadrícula tipo Excel */
        table { border-collapse: collapse; }
        th { background:#2a2a2a; color:#d6d6d6; font-size:11px; border:1px solid #4a4a4a; padding:9px 10px; }
        td { border:1px solid #3a3a3a; background:#1c1c1c; }
        tr:last-child td { border-bottom:1px solid #3a3a3a; }
        tr.zero td { color:#7d7d7d; }
        tr.total td { background:#2e2e2e !important; border-top:2px solid #555; color:#fff; }
        tr.today td { background:#33201a !important; }
        tr.cur-month td { background:#33201a !important; }
    </style>
</head>
<body>
@include('partials.navbar')
@php
    $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
    $pct = fn ($n) => number_format($n * 100, 1, ',', '.').'%';
    $maxDaily = max(1, $daily->max('total'));
    $dur = fn ($m) => $m >= 60 ? rtrim(rtrim(number_format($m / 60, 1, ',', ''), '0'), ',').' h' : $m.' min';
@endphp
<div class="page-inner">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px;">
        <a href="{{ route('sales.daily') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Venta de hoy</a>
        <a href="{{ route('sales.monthly') }}" style="padding:7px 14px;border-radius:7px;background:#ff7918;color:#111;font-weight:600;font-size:13px;text-decoration:none;">Historial de ventas</a>
        <a href="{{ route('admin.analytics.index') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Analytics</a>
    </div>
    <div class="topline">
        <div>
            <h1>{{ $first->locale('es')->isoFormat('MMMM YYYY') }}</h1>
            <p class="sub" style="margin-bottom:0;">Historial de ventas · <a href="{{ route('sales.daily') }}">Venta de hoy</a></p>
        </div>
        <div class="nav-day">
            <a href="{{ route('sales.monthly', $prev->format('Y-m')) }}">← {{ $prev->locale('es')->isoFormat('MMM YYYY') }}</a>
            @unless ($isCurrent)<a href="{{ route('sales.monthly') }}">Este mes</a>@endunless
            <a href="{{ route('sales.monthly', $next->format('Y-m')) }}">{{ $next->locale('es')->isoFormat('MMM YYYY') }} →</a>
            <a class="primary" href="{{ route('sales.monthly', ['month' => $first->format('Y-m'), 'export' => 'csv']) }}">Exportar a Excel (CSV)</a>
        </div>
    </div>

    <div class="cards" style="margin-top:14px;">
        <div class="card"><div class="label">Venta total del mes</div><div class="value">{{ $money($totals['total']) }}</div></div>
        <div class="card blue"><div class="label">Playrooms vendidos</div><div class="value">{{ $totals['rooms'] }}</div></div>
        <div class="card gold"><div class="label">Precio promedio por hab.</div><div class="value">{{ $money($avgTicket) }}</div></div>
        <div class="card green"><div class="label">Cobrado en caja</div><div class="value">{{ $money($totals['cash_total']) }}</div></div>
        <div class="card"><div class="label">Extras vendidos</div><div class="value">{{ $money($totals['extras']) }}</div></div>
        <div class="card"><div class="label">Canceladas</div><div class="value">{{ $cancelled }}</div></div>
    </div>


    @if ($planilla)
        <div class="sec-label">Planilla de recepción (ventas anotadas antes del sistema)</div>
        <p class="sec-note">Son ventas que recepción anotó a mano. <b>No están incluidas</b> en las cifras de arriba ni en la caja del sistema.@if ($totals['total'] > 0) Sistema + planilla este mes: <b>{{ $money($totals['total'] + $planilla['totals']['total']) }}</b>.@endif</p>
        <div class="cards">
            <div class="card"><div class="label">Venta de la planilla</div><div class="value">{{ $money($planilla['totals']['total']) }}</div></div>
            <div class="card blue"><div class="label">Estadías</div><div class="value">{{ $planilla['totals']['stays'] }}</div></div>
            <div class="card"><div class="label">Alojamiento</div><div class="value">{{ $money($planilla['totals']['lodging']) }}</div></div>
            <div class="card"><div class="label">Hora extra</div><div class="value">{{ $money($planilla['totals']['extra_hour']) }}</div></div>
            <div class="card"><div class="label">Packs y servicios</div><div class="value">{{ $money($planilla['totals']['packs']) }}</div></div>
            <div class="card"><div class="label">Extras (productos)</div><div class="value">{{ $money($planilla['totals']['extras']) }}</div></div>
            @if ($planilla['totals']['split'] > 0)<div class="card"><div class="label">Pago fraccionado</div><div class="value">{{ $money($planilla['totals']['split']) }}</div></div>@endif
            <div class="card green"><div class="label">Ticket promedio</div><div class="value">{{ $money($planilla['totals']['stays'] ? (int) round($planilla['totals']['lodging'] / $planilla['totals']['stays']) : 0) }}</div></div>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>Fecha</th><th class="n">Estadías</th><th class="n">Alojamiento</th><th class="n">Hora extra</th><th class="n">Packs y servicios</th><th class="n">Extras</th><th class="n">Pago fraccionado</th><th class="n">Venta total</th><th class="n">Tarjeta</th><th class="n">Efectivo</th><th class="n">Transferencia</th></tr></thead>
            <tbody>
            @foreach ($planilla['days'] as $r)
                <tr class="{{ $r['total'] === 0 ? 'zero' : '' }} {{ $r['date']->isWeekend() ? 'weekend' : '' }}">
                    <td>@if (auth()->user()?->isSoporte() && $r['total'] > 0)<a href="{{ route('admin.historical.sales', ['mes' => $first->format('Y-m'), 'dia' => $r['date']->toDateString()]) }}#detalle">{{ ucfirst($r['date']->locale('es')->isoFormat('ddd D')) }}</a>@else{{ ucfirst($r['date']->locale('es')->isoFormat('ddd D')) }}@endif</td>
                    <td class="n">{{ $r['stays'] }}</td><td class="n">{{ $money($r['lodging']) }}</td><td class="n">{{ $money($r['extra_hour']) }}</td><td class="n">{{ $money($r['packs']) }}</td><td class="n">{{ $money($r['extras']) }}</td><td class="n">{{ $money($r['split']) }}</td>
                    <td class="n"><b>{{ $money($r['total']) }}</b></td><td class="n">{{ $money($r['card']) }}</td><td class="n">{{ $money($r['cash']) }}</td><td class="n">{{ $money($r['transfer']) }}</td>
                </tr>
            @endforeach
                <tr class="total"><td>Total planilla</td><td class="n">{{ $planilla['totals']['stays'] }}</td><td class="n">{{ $money($planilla['totals']['lodging']) }}</td><td class="n">{{ $money($planilla['totals']['extra_hour']) }}</td><td class="n">{{ $money($planilla['totals']['packs']) }}</td><td class="n">{{ $money($planilla['totals']['extras']) }}</td><td class="n">{{ $money($planilla['totals']['split']) }}</td><td class="n">{{ $money($planilla['totals']['total']) }}</td><td class="n">{{ $money($planilla['totals']['card']) }}</td><td class="n">{{ $money($planilla['totals']['cash']) }}</td><td class="n">{{ $money($planilla['totals']['transfer']) }}</td></tr>
            </tbody>
        </table></div>
    @endif

    <div class="sec-label" style="margin-top:22px;border-top:none;padding-top:0;">Historial por mes</div>
    <p class="sec-note">Todos los meses con ventas. Toca un mes para ver su registro diario completo.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>Mes</th><th class="n">Hab. vendidas</th><th class="n">Alojamiento</th><th class="n">Extras</th><th class="n">Venta total</th><th class="n">Cobrado</th></tr></thead>
        <tbody>
        @forelse ($history as $h)
            <tr class="{{ $h['month']->isSameMonth($first) ? 'cur-month' : '' }}">
                <td><a href="{{ route('sales.monthly', $h['month']->format('Y-m')) }}" style="text-transform:capitalize;">{{ $h['month']->locale('es')->isoFormat('MMMM YYYY') }}</a>@if (($h['source'] ?? '') === 'planilla') <span class="miss" title="Ventas anotadas en la planilla de recepción, antes del sistema">planilla</span>@endif</td>
                <td class="n">{{ $h['rooms'] }}</td><td class="n">{{ $money($h['lodging']) }}</td><td class="n">{{ $money($h['extras']) }}</td><td class="n"><b>{{ $money($h['total']) }}</b></td><td class="n">{{ $money($h['cash']) }}</td>
            </tr>
        @empty
            <tr class="zero"><td colspan="6">Todavía no hay ventas registradas.</td></tr>
        @endforelse
        @if ($history->count() > 1)
            <tr class="total"><td>Total histórico (sistema + planilla)</td><td class="n">{{ $history->sum('rooms') }}</td><td class="n">{{ $money($history->sum('lodging')) }}</td><td class="n">{{ $money($history->sum('extras')) }}</td><td class="n">{{ $money($history->sum('total')) }}</td><td class="n">{{ $money($history->sum('cash')) }}</td></tr>
        @endif
        </tbody>
    </table></div>
    <div class="sec-label">Registro diario</div>
    <p class="sec-note">La <b>venta</b> cuenta por el día de la estadía y solo si está pagada completa (las pendientes de pago o por llegar sin pagar no suman). Lo <b>cobrado</b> cuenta por el día en que entró el pago (puede ser un anticipo o el saldo de otro día). Toca una fecha para ver su detalle.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>Fecha</th><th class="n">Hab. vendidas</th><th class="n">Alojamiento</th><th class="n">Extras</th><th class="n">Venta total</th><th class="n">Tarjeta</th><th class="n">Efectivo</th><th class="n">Transferencia</th><th class="n">Otro</th><th class="n">Cobrado</th></tr></thead>
        <tbody>
        @foreach ($daily as $r)
            <tr class="{{ $r['rooms'] === 0 && $r['cash_total'] === 0 ? 'zero' : '' }} {{ $r['date']->isWeekend() ? 'weekend' : '' }} {{ $r['date']->isToday() ? 'today' : '' }}">
                <td><a href="{{ route('sales.daily', $r['date']->toDateString()) }}">{{ ucfirst($r['date']->locale('es')->isoFormat('ddd D')) }}</a></td>
                <td class="n">{{ $r['rooms'] }}</td>
                <td class="n">{{ $money($r['lodging']) }}</td>
                <td class="n">{{ $money($r['extras']) }}</td>
                <td class="n"><b>{{ $money($r['total']) }}</b>@if($r['total'] > 0)<span class="bar" style="width:{{ max(2, round($r['total'] / $maxDaily * 60)) }}px"></span>@endif</td>
                @foreach ($groups as $g)<td class="n">{{ $money($r['cash'][$g]) }}</td>@endforeach
                <td class="n">{{ $money($r['cash_total']) }}</td>
            </tr>
        @endforeach
            <tr class="total"><td>Total</td><td class="n">{{ $totals['rooms'] }}</td><td class="n">{{ $money($totals['lodging']) }}</td><td class="n">{{ $money($totals['extras']) }}</td><td class="n">{{ $money($totals['total']) }}</td>@foreach ($groups as $g)<td class="n">{{ $money($totals['cash'][$g]) }}</td>@endforeach<td class="n">{{ $money($totals['cash_total']) }}</td></tr>
        </tbody>
    </table></div>

    <div class="grid2">
        <div>
            <div class="sec-label">Por día de la semana</div>
            <div class="table-wrap"><table>
                <thead><tr><th>Día</th><th class="n">Venta</th><th class="n">%</th><th class="n">Hab.</th><th class="n">Prom. por día</th><th class="n">Precio prom.</th></tr></thead>
                <tbody>
                @php $wkTotal = max(1, $byWeekday->sum('sales')); @endphp
                @foreach ($byWeekday as $w)
                    <tr class="{{ $w['rooms'] === 0 ? 'zero' : '' }}"><td>{{ $w['label'] }}</td><td class="n">{{ $money($w['sales']) }}</td><td class="n">{{ $pct($w['sales'] / $wkTotal) }}</td><td class="n">{{ $w['rooms'] }}</td><td class="n">{{ str_replace('.', ',', $w['avg_per_day']) }}</td><td class="n">{{ $money($w['avg_price']) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <div>
            <div class="sec-label">Por duración</div>
            <div class="table-wrap"><table>
                <thead><tr><th>Duración</th><th class="n">Veces</th><th class="n">Horas</th><th class="n">Monto</th><th class="n">Prom. hab.</th><th class="n">Por hora</th></tr></thead>
                <tbody>
                @forelse ($byDuration as $d)
                    <tr><td>{{ $dur($d['minutes']) }}</td><td class="n">{{ $d['times'] }}</td><td class="n">{{ rtrim(rtrim(number_format($d['hours'], 1, ',', '.'), '0'), ',') }}</td><td class="n">{{ $money($d['amount']) }}</td><td class="n">{{ $money($d['avg_room']) }}</td><td class="n">{{ $money($d['per_hour']) }}</td></tr>
                @empty
                    <tr class="zero"><td colspan="6">Sin ventas este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>

            <div class="sec-label">Por tipo de pago (cobros del mes)</div>
            <div class="table-wrap"><table>
                <thead><tr><th>Tipo de pago</th><th class="n">Pagos</th><th class="n">Monto</th><th class="n">%</th></tr></thead>
                <tbody>
                @forelse ($byMethod as $m)
                    <tr><td>{{ $m['label'] }}</td><td class="n">{{ $m['count'] }}</td><td class="n">{{ $money($m['amount']) }}</td><td class="n">{{ $pct($m['pct']) }}</td></tr>
                @empty
                    <tr class="zero"><td colspan="4">Sin cobros este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="grid2">
        <div>
            <div class="sec-label">Por Playroom</div>
            <div class="table-wrap"><table>
                <thead><tr><th>Playroom</th><th class="n">Veces</th><th class="n">Valor total</th><th class="n">% ventas</th><th class="n">Prom.</th></tr></thead>
                <tbody>
                @forelse ($byRoom as $r)
                    <tr><td>{{ $r['name'] }}</td><td class="n">{{ $r['times'] }}</td><td class="n">{{ $money($r['amount']) }}</td><td class="n">{{ $pct($r['pct']) }}</td><td class="n">{{ $money($r['avg']) }}</td></tr>
                @empty
                    <tr class="zero"><td colspan="5">Sin ventas este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div>
            <div class="sec-label">Extras vendidos</div>
            <div class="table-wrap"><table>
                <thead><tr><th>Detalle</th><th class="n">Unid.</th><th class="n">Venta</th><th class="n">%</th></tr></thead>
                <tbody>
                @forelse ($extras as $e)
                    <tr><td>{{ $e['name'] }}</td><td class="n">{{ $e['units'] }}</td><td class="n">{{ $money($e['amount']) }}</td><td class="n">{{ $pct($e['pct']) }}</td></tr>
                @empty
                    <tr class="zero"><td colspan="4">Sin extras este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="sec-label">Detalle de ventas</div>
    <p class="sec-note">Una fila por Playroom vendido y una por cada extra, en el orden de tu Excel. <b>Toca una fila</b> para ver el detalle completo de la venta; el código de la reserva te lleva a completar lo que falte (documento, RUT, nacionalidad).</p>
    <div class="filter">
        <label><input type="checkbox" id="only-missing"> Mostrar solo reservas con datos faltantes ({{ $missingCount }})</label>
        <a class="lnk" href="{{ route('sales.monthly', ['month' => $first->format('Y-m'), 'export' => 'detalle']) }}">Exportar detalle a Excel (CSV)</a>
    </div>
    <div class="table-wrap scroll-detail"><table id="detail-table">
        <thead><tr><th class="n">N°</th><th>Fecha</th><th>Reserva</th><th>Documento</th><th>N° voucher</th><th>N° boleta</th><th>Tipo</th><th>Detalle</th><th class="n">Cant.</th><th>Cliente</th><th>RUT / documento</th><th>Nacionalidad</th><th class="n">Edad</th><th>Hab.</th><th>Categoría</th><th class="n">Pers.</th><th>Entrada</th><th>Salida</th><th class="n">Horas</th><th>Tarifa</th><th class="n">Precio</th><th>Pago</th><th>Turno</th><th>Registró</th><th>Origen</th><th>Faltan</th></tr></thead>
        <tbody>
        @forelse ($detail as $r)
            <tr class="{{ $r['kind'] === 'EXTRAS' ? 'extra' : '' }}" data-missing="{{ count($r['missing']) ? 1 : 0 }}" data-booking="{{ $r['code'] }}" data-sale="{{ $r['code'] }}" title="Ver el detalle completo">
                <td class="n">{{ $r['n'] }}</td>
                <td>{{ $r['date']->format('d/m') }}</td>
                <td><a href="{{ route('reservations.show', $r['code']) }}">{{ $r['code'] }}</a></td>
                <td>{{ $r['doc_type'] ?: '—' }}{!! $r['repeated'] && $r['kind'] === 'ALOJAMIENTO' ? ' <span class="miss">repetido</span>' : '' !!}</td>
                <td>{{ $r['voucher'] ?: '—' }}</td>
                <td>{{ $r['receipt'] ?: '—' }}</td>
                <td>{{ $r['kind'] }}</td>
                <td>{{ $r['detail'] ?: '' }}</td>
                <td class="n">{{ $r['qty'] }}</td>
                <td>{{ $r['name'] }}</td>
                <td>{{ $r['doc_id'] ?: '—' }}</td>
                <td>{{ $r['nationality'] ?: '—' }}</td>
                <td class="n">{{ $r['age'] ?? '—' }}</td>
                <td>{{ $r['room'] }}</td>
                <td>{{ $r['category'] }}</td>
                <td class="n">{{ $r['guests'] }}</td>
                <td>{{ $r['in']->format('H:i') }}</td>
                <td>{{ $r['out']->format('H:i') }}</td>
                <td class="n">{{ rtrim(rtrim(number_format($r['minutes'] / 60, 1, ',', ''), '0'), ',') }}</td>
                <td>{{ $r['tariff'] }}</td>
                <td class="n">{{ $money($r['price']) }}{!! $r['discount'] ? ' <span class="miss">−'.$money($r['discount']).'</span>' : '' !!}</td>
                <td>{{ $r['method'] ?: '—' }}</td>
                <td>{{ $r['shift'] }}</td>
                <td>{{ $r['by'] ?: '—' }}</td>
                <td>{{ $r['origin'] }}</td>
                <td>@if (count($r['missing']))<span class="miss">{{ implode(', ', $r['missing']) }}</span>@elseif ($r['kind'] === 'ALOJAMIENTO')<span class="ok">completo</span>@endif</td>
            </tr>
        @empty
            <tr class="zero"><td colspan="26">Sin ventas este mes.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <script>
        document.getElementById('only-missing')?.addEventListener('change', function () {
            const rows = document.querySelectorAll('#detail-table tbody tr[data-booking]');
            const keep = new Set();
            if (this.checked) rows.forEach(r => { if (r.dataset.missing === '1') keep.add(r.dataset.booking); });
            rows.forEach(r => { r.style.display = (!this.checked || keep.has(r.dataset.booking)) ? '' : 'none'; });
        });
    </script>
</div>
@include('sales._drawer')
</body>
</html>
