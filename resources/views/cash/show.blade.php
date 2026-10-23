<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Caja — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 1100px; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 24px; }
        .nav-day { display:flex; align-items:center; gap:10px; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day .today { color:#999; font-size:12.5px; }

        .banner { background:#14251c; border:1px solid #2e6e45; color:#8fe0ad; padding:12px 16px; border-radius:9px; margin-bottom:18px; font-size:14px; font-weight:600; }

        .saldo-card { background:#171417; border:1px solid #333; border-radius:12px; padding:22px 26px; margin-bottom:24px; }
        .saldo-card .label { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#999; margin-bottom:6px; }
        .saldo-card .value { font-size:34px; font-family: ui-monospace, monospace; font-weight:700; color:#8fe0ad; }
        .saldo-breakdown { display:flex; gap:24px; margin-top:14px; flex-wrap:wrap; font-size:13px; color:#aaa; }
        .saldo-breakdown b { color:#eee; font-family: ui-monospace, monospace; }

        .cols { display:grid; grid-template-columns: 340px 1fr; gap:20px; align-items:start; }
        @media (max-width: 760px) { .cols { grid-template-columns: 1fr; } }
        .panel { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:18px 20px; }
        .panel h3 { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#999; margin:0 0 14px; }

        label { display:block; font-size: 12px; color:#bbb; margin: 14px 0 6px; }
        label:first-of-type { margin-top:0; }
        input, select { width:100%; box-sizing:border-box; background:#141414; border:1px solid #333; color:#fff; padding:10px 12px; border-radius:7px; font-size:14px; font-family:inherit; }
        .type-row { display:flex; gap:8px; }
        .type-row button { flex:1; background:#141414; border:1.5px solid #333; color:#999; padding:10px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; }
        .type-row button.active[data-type="ingreso"] { background:#14251c; border-color:#2e6e45; color:#8fe0ad; }
        .type-row button.active[data-type="egreso"] { background:#2a1416; border-color:#7a2d2d; color:#e88a9a; }
        button.submit { width:100%; margin-top:18px; background:#ff7918; color:#fff; border:none; padding:12px; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:10px 12px; border-radius:8px; margin-bottom: 14px; font-size:13px; }

        .row1 { display:flex; justify-content:space-between; align-items:baseline; padding:10px 0; border-top:1px solid #262626; gap:12px; }
        .row1:first-child { border-top:none; }
        .row1 .when { font-size:11.5px; color:#888; font-family: ui-monospace, monospace; flex:none; }
        .row1 .desc { flex:1; font-size:13.5px; }
        .row1 .desc .code { color:#ff7918; text-decoration:none; font-size:12px; }
        .row1 .amt { font-family: ui-monospace, monospace; font-weight:700; font-size:14px; white-space:nowrap; }
        .row1.ingreso .amt { color:#8fe0ad; }
        .row1.egreso .amt { color:#e88a9a; }
        .empty { color:#666; font-size:14px; padding: 30px 0; text-align:center; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <div class="topline">
        <div>
            <h1>Caja</h1>
        </div>
        <div class="nav-day">
            <a href="{{ route('cash.show', $day->copy()->subDay()->toDateString()) }}">← Día anterior</a>
            @unless ($day->isToday())
                <a href="{{ route('cash.show') }}" class="today">Ir a hoy</a>
            @endunless
            <a href="{{ route('cash.show', $day->copy()->addDay()->toDateString()) }}">Día siguiente →</a>
        </div>
    </div>
    <p class="sub">{{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>

    <style>
        .coll-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin:6px 0 22px; }
        @media (max-width: 720px) { .coll-grid { grid-template-columns: 1fr; } }
        .coll-box { background:#1c1c1c; border:1px solid #333; border-radius:10px; overflow:hidden; }
        .coll-box h3 { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#999; margin:0; padding:14px 16px 8px; }
        .coll-box table { width:100%; border-collapse:collapse; font-size:13.5px; }
        .coll-box td { padding:8px 16px; border-top:1px solid #262626; }
        .coll-box td.n { text-align:right; font-variant-numeric:tabular-nums; }
        .coll-box tr.zero td { color:#555; }
        .coll-box tr.total td { font-weight:700; color:#fff; background:#202020; border-top:1px solid #444; }
        .coll-note { font-size:12.5px; color:#999; margin:0 0 8px; }
    </style>
    @php $m = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <div class="sec-label" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#888;margin:14px 0 6px;">Cobros del día — todos los medios de pago</div>
    <p class="coll-note">Plata que entró este día, sin importar de qué reserva es. El efectivo físico del mesón está más abajo.</p>
    <div class="coll-grid">
        <div class="coll-box">
            <h3>Por tipo de pago</h3>
            <table>
                @foreach ($collectedByGroup as $label => $amount)
                    <tr class="{{ $amount === 0 ? 'zero' : '' }}"><td>{{ $label }}</td><td class="n">{{ $m($amount) }}</td></tr>
                @endforeach
                <tr class="total"><td>Total cobrado</td><td class="n">{{ $m($collectedTotal) }}</td></tr>
            </table>
        </div>
        <div class="coll-box">
            <h3>De dónde viene</h3>
            <table>
                <tr class="{{ $collectedSameDay === 0 ? 'zero' : '' }}"><td>De reservas de este día</td><td class="n">{{ $m($collectedSameDay) }}</td></tr>
                <tr class="{{ $collectedAdvance === 0 ? 'zero' : '' }}"><td>Anticipos de reservas futuras</td><td class="n">{{ $m($collectedAdvance) }}</td></tr>
                <tr class="{{ $collectedLate === 0 ? 'zero' : '' }}"><td>Saldos de estadías de días anteriores</td><td class="n">{{ $m($collectedLate) }}</td></tr>
                <tr class="total"><td>Total</td><td class="n">{{ $m($collectedTotal) }}</td></tr>
            </table>
        </div>
    </div>
    @if ($advancePayments->isNotEmpty())
        <details style="margin:-6px 0 22px;background:#1c1c1c;border:1px solid #333;border-radius:10px;padding:12px 16px;">
            <summary style="cursor:pointer;color:#e8c76f;font-size:13px;">Anticipos de este día ({{ $advancePayments->count() }}) — a qué estadía corresponde cada uno</summary>
            @foreach ($advancePayments as $payment)
                <div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-top:1px solid #262626;font-size:13.5px;">
                    <span>{{ $payment->booking->room->name ?? '—' }} · {{ $payment->booking->customer->name ?? '—' }} · estadía {{ $payment->booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i') }} · <a href="{{ route('reservations.show', $payment->booking->code) }}" style="color:#ff7918;text-decoration:none;">{{ $payment->booking->code }}</a></span>
                    <span>{{ $m($payment->amount) }}</span>
                </div>
            @endforeach
        </details>
    @endif

    @if (session('status'))
        <div class="banner">{{ session('status') }}</div>
    @endif

    <div class="saldo-card">
        <div class="label">Saldo en caja — efectivo que debería haber ahora mismo</div>
        <div class="value">${{ number_format($saldoEnCaja, 0, ',', '.') }}</div>
        <div class="saldo-breakdown">
            <span>Efectivo de reservas (histórico): <b>${{ number_format($totalCashFromSales, 0, ',', '.') }}</b></span>
            <span>+ Ingresos manuales: <b>${{ number_format($totalIngresos, 0, ',', '.') }}</b></span>
            <span>− Egresos manuales: <b>${{ number_format($totalEgresos, 0, ',', '.') }}</b></span>
        </div>
    </div>

    <div class="cols">
        <div class="panel">
            <h3>Registrar movimiento</h3>

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('cash.store') }}" id="cash-form">
                @csrf
                <input type="hidden" name="type" id="cash-type" value="{{ old('type', 'ingreso') }}">
                <label>Tipo</label>
                <div class="type-row">
                    <button type="button" data-type="ingreso" onclick="hhSetCashType('ingreso')">+ Ingreso (caja chica)</button>
                    <button type="button" data-type="egreso" onclick="hhSetCashType('egreso')">− Egreso</button>
                </div>

                <label>Monto (CLP)</label>
                <input type="number" name="amount" min="1" value="{{ old('amount') }}" required>

                <label>Descripción (opcional)</label>
                <input type="text" name="description" placeholder="Ej: apertura de caja, compra insumos, retiro a banco" value="{{ old('description') }}">

                <button class="submit" type="submit">Registrar</button>
            </form>
        </div>

        <div class="panel">
            <h3>Movimientos del día — entró ${{ number_format($dayCashIn, 0, ',', '.') }} · salió ${{ number_format($dayCashOut, 0, ',', '.') }}</h3>
            @forelse ($timeline as $item)
                <div class="row1 {{ $item['kind'] }}">
                    <span class="when">{{ $item['time']->format('H:i') }}</span>
                    <span class="desc">
                        {{ $item['label'] }}
                        @if ($item['code'])
                            · <a class="code" href="{{ route('reservations.show', $item['code']) }}">{{ $item['code'] }}</a>
                        @endif
                    </span>
                    <span class="amt">{{ $item['kind'] === 'egreso' ? '−' : '+' }}${{ number_format($item['amount'], 0, ',', '.') }}</span>
                </div>
            @empty
                <p class="empty">Sin movimientos de caja este día.</p>
            @endforelse
        </div>
    </div>
    </div>
    <script>
        function hhSetCashType(type) {
            document.getElementById('cash-type').value = type;
            document.querySelectorAll('.type-row button').forEach(b => b.classList.toggle('active', b.dataset.type === type));
        }
        hhSetCashType(document.getElementById('cash-type').value);
    </script>
</body>
</html>
