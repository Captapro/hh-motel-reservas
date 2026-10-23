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
        .page-inner { max-width: 1100px; margin: 0; padding: 24px 24px 60px; }
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
        @media (max-width: 640px) { .cols { grid-template-columns: 1fr; } }
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
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px;">
        <a href="{{ route('admin.analytics.index') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Analytics</a>
        <a href="{{ route('sales.monthly') }}" style="padding:7px 14px;border-radius:7px;background:#1c1c1c;border:1px solid #333;color:#eee;font-size:13px;text-decoration:none;">Resumen mensual</a>
        <a href="{{ route('sales.daily') }}" style="padding:7px 14px;border-radius:7px;background:#ff7918;color:#111;font-weight:600;font-size:13px;text-decoration:none;">Venta de hoy</a>
    </div>
    <div class="topline">
        <div>
            <h1>Venta del día — {{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }}</h1>
        </div>
        <div class="nav-day">
            <a href="{{ route('sales.daily', $day->copy()->subDay()->toDateString()) }}">← Día anterior</a>
            @unless ($day->isToday())
                <a href="{{ route('sales.daily') }}" class="today">Ir a hoy</a>
            @endunless
            <a href="{{ route('sales.daily', $day->copy()->addDay()->toDateString()) }}">Día siguiente →</a>
        </div>
    </div>

    <div class="sec-label">Cobros de caja — plata que entró hoy, sin importar de qué reserva es</div>
    <div class="cards">
        <div class="card cash-total"><div class="label">Total cobrado hoy</div><div class="value">${{ number_format($cashTotal, 0, ',', '.') }}</div></div>
        <div class="card"><div class="label">De reservas de hoy</div><div class="value">${{ number_format($samedayTotal, 0, ',', '.') }}</div></div>
        <div class="card advance"><div class="label">Anticipos — reservas futuras</div><div class="value">${{ number_format($advanceTotal, 0, ',', '.') }}</div></div>
        <div class="card late"><div class="label">Saldos de días anteriores</div><div class="value">${{ number_format($lateTotal, 0, ',', '.') }}</div></div>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h3>Por método de pago (cobrado hoy)</h3>
        @forelse ($cashByMethod as $method => $amount)
            <div class="row"><span>{{ $method }}</span><span>${{ number_format($amount, 0, ',', '.') }}</span></div>
        @empty
            <div class="row"><span>Sin pagos registrados hoy</span><span>—</span></div>
        @endforelse
        <div class="row total"><span>Total</span><span>${{ number_format($cashTotal, 0, ',', '.') }}</span></div>
    </div>

    @if ($advanceBookings->isNotEmpty())
        <div class="panel" style="margin-bottom:20px;">
            <h3>Anticipos de hoy — dónde queda esa plata registrada</h3>
            @foreach ($advanceBookings as $payment)
                <div class="row">
                    <span>
                        {{ $payment->booking->room->name ?? '—' }} · {{ $payment->booking->customer->name ?? '—' }}
                        · estadía {{ $payment->booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i') }}
                        · <a class="code" href="{{ route('reservations.show', $payment->booking->code) }}">{{ $payment->booking->code }}</a>
                    </span>
                    <span>${{ number_format($payment->amount, 0, ',', '.') }}</span>
                </div>
            @endforeach
            <p class="sec-note" style="margin-top:10px;">Esta plata ya está en la caja de hoy. El día de esa estadía, va a aparecer en su "Habitaciones vendidas" como venta ya cobrada — no hay que volver a contarla ese día.</p>
        </div>
    @endif

    <div class="sec-label">Habitaciones vendidas — estadías que empiezan hoy</div>
    <div class="cards">
        <div class="card count"><div class="label">Reservas</div><div class="value">{{ $bookings->count() }}</div></div>
        <div class="card revenue"><div class="label">Venta total</div><div class="value">${{ number_format($revenue, 0, ',', '.') }}</div></div>
        <div class="card collected"><div class="label">Cobrado</div><div class="value">${{ number_format($collected, 0, ',', '.') }}</div></div>
        <div class="card pending"><div class="label">Pendiente</div><div class="value">${{ number_format($pending, 0, ',', '.') }}</div></div>
    </div>
    @if ($collectedInAdvanceForToday > 0)
        <p class="sec-note">De lo cobrado (${{ number_format($collected, 0, ',', '.') }}), ${{ number_format($collectedInAdvanceForToday, 0, ',', '.') }} ya había entrado a caja en un día anterior como anticipo — no es plata nueva de hoy.</p>
    @endif

    <div class="panel" style="margin-bottom:20px;">
        <h3>Por tarifa</h3>
        @forelse ($byTariff as $name => $data)
            <div class="row"><span>{{ $name ?? 'Sin tarifa' }} ({{ $data['count'] }})</span><span>${{ number_format($data['revenue'], 0, ',', '.') }}</span></div>
        @empty
            <div class="row"><span>Sin reservas este día</span><span>—</span></div>
        @endforelse
    </div>

    <div class="table-wrap">
        @if ($bookings->isEmpty())
            <p class="empty">No hay reservas asignadas a este día.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Hora</th><th>Habitación</th><th>Cliente</th><th>Tarifa</th>
                        <th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        <tr class="{{ $booking->booking_status === 'CANCELADA' ? 'cancelada' : '' }}">
                            <td>{{ $booking->starts_at->timezone('America/Santiago')->format('H:i') }}</td>
                            <td>{{ $booking->room->name }}</td>
                            <td>{{ $booking->customer->name }}<br><a class="code" href="{{ route('reservations.show', $booking->code) }}">{{ $booking->code }}</a></td>
                            <td><span class="pill">{{ $booking->rate_rule_name_snapshot ?? '—' }}</span></td>
                            <td>${{ number_format($booking->price_final + $booking->addonsTotal(), 0, ',', '.') }}</td>
                            <td>${{ number_format($booking->paidAmount(), 0, ',', '.') }}</td>
                            <td>${{ number_format($booking->balanceDue(), 0, ',', '.') }}</td>
                            <td><span class="pill">{{ $booking->booking_status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($cancelledCount > 0)
        <p class="sub">{{ $cancelledCount }} {{ Str::plural('reserva', $cancelledCount) }} cancelada{{ $cancelledCount > 1 ? 's' : '' }} ese día — no se cuenta en los totales, aparece atenuada en la tabla.</p>
    @endif
    </div>
</body>
</html>
