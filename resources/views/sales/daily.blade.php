<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Venta del {{ $day->format('d/m/Y') }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 24px; }
        .sub a { color:#ff7918; text-decoration:none; }
        .nav-day { display:flex; align-items:center; gap:10px; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day .today { color:#999; font-size:12.5px; }

        .sec-label { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#888; margin:30px 0 12px; padding-top:18px; border-top:1px solid #232323; }
        .sec-label:first-of-type { margin-top:0; padding-top:0; border-top:none; }
        .sec-note { font-size:12.5px; color:#e8c76f; margin:-6px 0 16px; }

        .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; }
        .card .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        .card .value { font-size:22px; font-family: ui-monospace, monospace; font-weight:600; }
        .card.revenue .value { color:#eee; }
        .card.collected .value, .card.cash-total .value { color:#6fd39a; }
        .card.pending .value { color:#e88a9a; }
        .card.count .value { color:#7fbcdc; }
        .card.advance .value { color:#e8c76f; }
        .card.late .value { color:#b088e8; }

        .cols { display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px; }
        @media (max-width: 640px) { .cols { grid-template-columns: minmax(0, 1fr); } }
        .panel { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:16px 18px; }
        .panel h3 { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#999; margin:0 0 12px; }
        .row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; border-top:1px solid #262626; }
        .row:first-child { border-top:none; }
        .row.total { font-weight:700; color:#fff; border-top:1px solid #333; margin-top:4px; padding-top:10px; }

        table { width:100%; border-collapse: collapse; font-size:13.5px; }
        th { text-align:left; color:#888; font-weight:500; font-size:10.5px; text-transform:uppercase; padding:8px 10px; border-bottom:1px solid #333; }
        td { padding:9px 10px; border-bottom:1px solid #242424; }
        tr:last-child td { border-bottom:none; }
        tr.cancelada { opacity:.45; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; text-decoration:none; }
        .pill { display:inline-block; font-size:10px; font-family: ui-monospace, monospace; padding:2px 7px; border-radius:20px; background:#2a2a2a; color:#ccc; }
        .pill.advance { background:#2a2410; color:#e8c76f; }
        .empty { color:#666; font-size:14px; padding: 30px 0; text-align:center; }
        .table-wrap { overflow-x:auto; background:#1c1c1c; border:1px solid #333; border-radius:10px; margin-bottom:16px; }
        th.n, td.n { text-align:right; font-variant-numeric: tabular-nums; white-space:nowrap; }
        td { white-space:nowrap; }
        tr.total td { font-weight:700; color:#fff; background:#202020; border-top:1px solid #444; }
        tr.zero td { color:#555; }
        .pill.ok { background:#16301f; color:#6fd39a; } .pill.warn { background:#2a2410; color:#e8c76f; } .pill.bad { background:#3a1c22; color:#e88a9a; }
        .miss { display:inline-block; font-size:10px; color:#e8c76f; background:#2a2410; border:1px solid #6b5a1e; border-radius:20px; padding:1px 7px; }
        details.adv { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:12px 16px; margin-bottom:16px; }
        details.adv summary { cursor:pointer; color:#e8c76f; font-size:13px; }
        /* Cuadrícula tipo Excel */
        table { border-collapse: collapse; }
        th { background:#2a2a2a; color:#d6d6d6; font-size:11px; border:1px solid #4a4a4a; padding:9px 10px; }
        td { border:1px solid #3a3a3a; background:#1c1c1c; }
        tr:last-child td { border-bottom:1px solid #3a3a3a; }
        tr.zero td { color:#7d7d7d; }
        tr.blank td { height:36px; }
        tr.total td { background:#2e2e2e !important; border-top:2px solid #555; color:#fff; }
        tr.today td { background:#33201a !important; }
        tr.cur-month td { background:#33201a !important; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px;">
        <a href="{{ route('sales.daily') }}" style="padding:7px 14px;border-radius:7px;background:#ff7918;color:#111;font-weight:600;font-size:13px;text-decoration:none;">Venta de hoy</a>
        <a href="{{ route('sales.monthly') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Historial de ventas</a>
        <a href="{{ route('admin.analytics.index') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Analytics</a>
    </div>
    @php
        $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $groupOf = fn ($name) => match (true) {
            in_array($name, ['Débito', 'Crédito', 'Mercado Pago'], true) => 'Tarjeta',
            $name === 'Efectivo' => 'Efectivo',
            $name === 'Transferencia' => 'Transferencia',
            default => 'Otro',
        };
        $live = $sold;
        $lodging = $live->sum('price_final');
        $extrasSum = $live->sum(fn ($b) => $b->paidAddonsAmount());
    @endphp

    <div class="topline">
        <div>
            <h1>Venta del día — {{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }}</h1>
            <p class="sub" style="margin-bottom:0;">Estadías que empiezan este día, solo las pagadas completas cuentan como venta · <a href="{{ route('sales.monthly', $day->format('Y-m')) }}">ver el mes completo →</a> · <a href="{{ route('cash.show', $day->toDateString()) }}">ver la caja de este día →</a></p>
        </div>
        <div class="nav-day">
            <a href="{{ route('sales.daily', $day->copy()->subDay()->toDateString()) }}">← Día anterior</a>
            @unless ($day->isToday())
                <a href="{{ route('sales.daily') }}" class="today">Ir a hoy</a>
            @endunless
            <a href="{{ route('sales.daily', $day->copy()->addDay()->toDateString()) }}">Día siguiente →</a>
        </div>
    </div>

    <div class="cards" style="margin-top:14px;">
        <div class="card count"><div class="label">Playrooms vendidos</div><div class="value">{{ $live->count() }}</div></div>
        <div class="card revenue"><div class="label">Venta total</div><div class="value">{{ $money($revenue) }}</div></div>
        <div class="card collected"><div class="label">Cobrado de esas ventas</div><div class="value">{{ $money($collected) }}</div></div>
        <div class="card pending"><div class="label">Pendiente</div><div class="value">{{ $money($pending) }}</div></div>
    </div>

    <div class="sec-label">Registro del día</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Hora</th><th>Reserva</th><th>Hab.</th><th>Categoría</th><th>Cliente</th><th>Tarifa</th>
                    <th class="n">Alojamiento</th><th class="n">Extras</th><th class="n">Total</th><th class="n">Pagado</th><th class="n">Saldo</th>
                    <th>Pago</th><th>Voucher / boleta</th><th>Estado</th><th>Origen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookings as $booking)
                    @php
                        $cancelled = $booking->booking_status === 'CANCELADA';
                        $extras = (int) $booking->addons->sum('amount');
                        $docs = $booking->payments->where('status', 'aprobado')->map(fn ($p) => trim(($p->voucher_number ? 'V '.$p->voucher_number : '').' '.($p->receipt_number ? 'B '.$p->receipt_number : '')))->filter()->implode(' · ');
                        $methods = $booking->payments->where('status', 'aprobado')->map(fn ($p) => $groupOf($p->paymentMethod?->name))->unique()->implode(' + ');
                        $paid = $booking->paidAmount();
                        $balance = $booking->balanceDue();
                    @endphp
                    <tr class="{{ $cancelled ? 'cancelada' : '' }}" data-sale="{{ $booking->code }}" title="Ver el detalle completo">
                        <td>{{ $booking->starts_at->timezone('America/Santiago')->format('H:i') }}</td>
                        <td><a class="code" href="{{ route('reservations.show', $booking->code) }}">{{ $booking->code }}</a></td>
                        <td>{{ $booking->room->name }}</td>
                        <td>{{ $booking->room->category->name }}</td>
                        <td>{{ $booking->customer->name }}</td>
                        <td>{{ $booking->rate_rule_name_snapshot ?? '—' }}</td>
                        <td class="n">{{ $money($booking->price_final) }}</td>
                        <td class="n">{{ $money($extras) }}</td>
                        <td class="n"><b>{{ $money($booking->price_final + $extras) }}</b></td>
                        <td class="n">{{ $money($paid) }}</td>
                        <td class="n">{{ $balance > 0 && ! $cancelled ? $money($balance) : '—' }}</td>
                        <td>{{ $methods ?: '—' }}</td>
                        <td>{!! $docs ?: ($paid > 0 ? '<span class="miss">falta</span>' : '—') !!}</td>
                        <td><span class="pill {{ $cancelled ? 'bad' : ($balance <= 0 ? 'ok' : 'warn') }}">{{ $booking->booking_status }}</span></td>
                        <td>{{ $booking->originLabel() }}</td>
                    </tr>
                @endforeach
                @if ($bookings->isEmpty())
                    <tr class="zero"><td colspan="15" style="text-align:center;padding:12px;">Sin reservas asignadas a este día</td></tr>
                @endif
                {{-- Filas en blanco para que se vea como hoja de cálculo --}}
                @for ($i = max(0, 10 - max($bookings->count(), 1)); $i > 0; $i--)
                    <tr class="blank">@for ($c = 0; $c < 15; $c++)<td>&nbsp;</td>@endfor</tr>
                @endfor
                <tr class="total">
                    <td colspan="6">Total del día ({{ $live->count() }} {{ $live->count() === 1 ? 'Playroom' : 'Playrooms' }})</td>
                    <td class="n">{{ $money($lodging) }}</td><td class="n">{{ $money($extrasSum) }}</td><td class="n">{{ $money($revenue) }}</td>
                    <td class="n">{{ $money($collected) }}</td><td class="n">{{ $money($pending) }}</td><td colspan="4"></td>
                </tr>
            </tbody>
        </table>
    </div>
    @if ($cancelledCount > 0)
        <p class="sub">{{ $cancelledCount }} {{ Str::plural('reserva', $cancelledCount) }} cancelada{{ $cancelledCount > 1 ? 's' : '' }} ese día — no se cuenta en los totales, aparece atenuada en la tabla.</p>
    @endif
    </div>
    @include('sales._drawer')
</body>
</html>
