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
        .page-inner { max-width: 1760px; margin: 0; padding: 18px 24px 28px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 14px; }
        h1 { font-size: 22px; margin:0; display:inline-block; }
        .day-label { color:#62656c; font-size:14px; margin-left:12px; }
        .nav-day { display:flex; align-items:center; gap:10px; }
        .nav-day a { color:#eee; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:8px 14px; border-radius:7px; font-size:13px; }
        .nav-day a:hover { border-color:#ff7918; }
        .nav-day .today { color:#999; font-size:12.5px; }

        .banner { background:#14251c; border:1px solid #2e6e45; color:#8fe0ad; padding:10px 16px; border-radius:9px; margin-bottom:14px; font-size:14px; font-weight:600; }

        /* Cifras clave */
        .kpis { display:grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap:14px; margin-bottom:16px; }
        @media (max-width: 1000px) { .kpis { grid-template-columns: 1fr 1fr; } }
        .kpi { background:#1c1c1c; border:1px solid #333; border-radius:12px; padding:16px 20px; }
        .kpi .label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#999; margin-bottom:6px; }
        .kpi .value { font-size:26px; font-family: ui-monospace, monospace; font-weight:700; }
        .kpi .note { font-size:12px; color:#888; margin-top:6px; line-height:1.5; }
        .kpi .note b { color:#ddd; font-family: ui-monospace, monospace; font-weight:600; }
        .kpi.saldo { background:#14211a; border-color:#2e6e45; }
        .kpi.saldo .value { font-size:36px; color:#8fe0ad; }
        .kpi.in .value { color:#8fe0ad; }
        .kpi.out .value { color:#e88a9a; }

        /* Tres columnas: cobros | movimientos | registrar */
        .cols { display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(300px,.8fr); gap:16px; align-items:start; }
        @media (max-width: 1200px) { .cols { grid-template-columns: 1fr 1fr; } .cols > .col-form { grid-column: 1 / -1; } }
        @media (max-width: 760px) { .cols { grid-template-columns: minmax(0, 1fr); } }
        .panel { background:#1c1c1c; border:1px solid #333; border-radius:12px; padding:16px 18px; }
        .panel h3 { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#999; margin:0 0 4px; }
        .panel .hint { font-size:12px; color:#777; margin:0 0 10px; }
        .panel + .panel { margin-top:16px; }

        table.g { width:100%; border-collapse:collapse; font-size:13.5px; margin-top:8px; }
        table.g td { padding:8px 10px; border:1px solid #333; background:#1c1c1c; }
        table.g td.n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        table.g tr.zero td { color:#7d7d7d; }
        table.g tr.total td { font-weight:700; color:#fff; background:#2e2e2e; border-top:2px solid #555; }

        label { display:block; font-size: 12px; color:#bbb; margin: 14px 0 6px; }
        label:first-of-type { margin-top:0; }
        input, select { width:100%; box-sizing:border-box; background:#141414; border:1px solid #333; color:#fff; padding:10px 12px; border-radius:7px; font-size:14px; font-family:inherit; }
        .type-row { display:flex; gap:8px; }
        .type-row button { flex:1; background:#141414; border:1.5px solid #333; color:#999; padding:10px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; }
        .type-row button.active[data-type="ingreso"] { background:#14251c; border-color:#2e6e45; color:#8fe0ad; }
        .type-row button.active[data-type="egreso"] { background:#2a1416; border-color:#7a2d2d; color:#e88a9a; }
        button.submit { width:100%; margin-top:16px; background:#ff7918; color:#fff; border:none; padding:12px; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:10px 12px; border-radius:8px; margin-bottom: 14px; font-size:13px; }

        .row1 { display:flex; justify-content:space-between; align-items:baseline; padding:9px 0; border-top:1px solid #262626; gap:12px; }
        .row1:first-of-type { border-top:none; }
        .row1 .when { font-size:11.5px; color:#888; font-family: ui-monospace, monospace; flex:none; }
        .row1 .desc { flex:1; font-size:13.5px; }
        .row1 .desc .code { color:#ff7918; text-decoration:none; font-size:12px; }
        .row1 .amt { font-family: ui-monospace, monospace; font-weight:700; font-size:14px; white-space:nowrap; }
        .row1.ingreso .amt { color:#8fe0ad; }
        .row1.egreso .amt { color:#e88a9a; }
        .empty { color:#666; font-size:14px; padding: 26px 0; text-align:center; }
        .timeline { max-height: 430px; overflow-y:auto; }
        details.adv { margin-top:12px; font-size:13px; }
        details.adv summary { cursor:pointer; color:#e8c76f; }
        details.adv .adv-row { display:flex; justify-content:space-between; gap:12px; padding:7px 0; border-top:1px solid #262626; }
    </style>
</head>
<body>
    @include('partials.navbar')
    @php $m = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <div class="page-inner">
    <div class="topline">
        <div>
            <h1>Caja</h1><span class="day-label">{{ ucfirst($day->locale('es')->isoFormat('dddd D [de] MMMM')) }}</span>
        </div>
        <div class="nav-day">
            <a href="{{ route('cash.show', $day->copy()->subDay()->toDateString()) }}">← Día anterior</a>
            @unless ($day->isToday())
                <a href="{{ route('cash.show') }}" class="today">Ir a hoy</a>
            @endunless
            <a href="{{ route('cash.show', $day->copy()->addDay()->toDateString()) }}">Día siguiente →</a>
            @if (auth()->user()?->isAdministrador())
                <a href="{{ route('cash.shifts') }}">Entregas de turno</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="banner">{{ session('status') }}</div>
    @endif

    <div class="kpis">
        <div class="kpi saldo">
            <div class="label">Saldo en caja — efectivo que debería haber ahora mismo</div>
            <div class="value">{{ $m($saldoEnCaja) }}</div>
            <div class="note">Efectivo de reservas (histórico) <b>{{ $m($totalCashFromSales) }}</b> · + ingresos manuales <b>{{ $m($totalIngresos) }}</b> · − egresos manuales <b>{{ $m($totalEgresos) }}</b></div>
        </div>
        <div class="kpi">
            <div class="label">Cobrado este día</div>
            <div class="value">{{ $m($collectedTotal) }}</div>
            <div class="note">en todos los medios de pago</div>
        </div>
        <div class="kpi in">
            <div class="label">Entró a la caja física</div>
            <div class="value">{{ $m($dayCashIn) }}</div>
            <div class="note">efectivo de reservas + ingresos manuales</div>
        </div>
        <div class="kpi out">
            <div class="label">Salió de la caja física</div>
            <div class="value">{{ $m($dayCashOut) }}</div>
            <div class="note">egresos manuales del día</div>
        </div>
    </div>

    <div class="cols">
        <div class="col">
            <div class="panel">
                <h3>Cobros del día</h3>
                <p class="hint">Plata que entró este día, sin importar de qué reserva es.</p>
                <table class="g">
                    @foreach ($collectedByGroup as $label => $amount)
                        <tr class="{{ $amount === 0 ? 'zero' : '' }}"><td>{{ $label }}</td><td class="n">{{ $m($amount) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Total cobrado</td><td class="n">{{ $m($collectedTotal) }}</td></tr>
                </table>
            </div>
            <div class="panel">
                <h3>De dónde viene</h3>
                <table class="g">
                    <tr class="{{ $collectedSameDay === 0 ? 'zero' : '' }}"><td>De reservas de este día</td><td class="n">{{ $m($collectedSameDay) }}</td></tr>
                    <tr class="{{ $collectedAdvance === 0 ? 'zero' : '' }}"><td>Anticipos de reservas futuras</td><td class="n">{{ $m($collectedAdvance) }}</td></tr>
                    <tr class="{{ $collectedLate === 0 ? 'zero' : '' }}"><td>Saldos de estadías anteriores</td><td class="n">{{ $m($collectedLate) }}</td></tr>
                    <tr class="total"><td>Total</td><td class="n">{{ $m($collectedTotal) }}</td></tr>
                </table>
                @if ($advancePayments->isNotEmpty())
                    <details class="adv">
                        <summary>Anticipos de este día ({{ $advancePayments->count() }}) — a qué estadía corresponde cada uno</summary>
                        @foreach ($advancePayments as $payment)
                            <div class="adv-row">
                                <span>{{ $payment->booking->room->name ?? '—' }} · {{ $payment->booking->customer->name ?? '—' }} · estadía {{ $payment->booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i') }} · <a class="code" style="color:#ff7918;text-decoration:none;" href="{{ route('reservations.show', $payment->booking->code) }}">{{ $payment->booking->code }}</a></span>
                                <span>{{ $m($payment->amount) }}</span>
                            </div>
                        @endforeach
                    </details>
                @endif
            </div>
        </div>

        <div class="col">
            <div class="panel">
                <h3>Movimientos del día</h3>
                <p class="hint">Efectivo de reservas y movimientos de caja chica, en orden.</p>
                <div class="timeline">
                    @forelse ($timeline as $item)
                        <div class="row1 {{ $item['kind'] }}">
                            <span class="when">{{ $item['time']->format('H:i') }}</span>
                            <span class="desc">
                                {{ $item['label'] }}
                                @if ($item['code'])
                                    · <a class="code" href="{{ route('reservations.show', $item['code']) }}">{{ $item['code'] }}</a>
                                @endif
                            </span>
                            <span class="amt">{{ $item['kind'] === 'egreso' ? '−' : '+' }}{{ $m($item['amount']) }}</span>
                        </div>
                    @empty
                        <p class="empty">Sin movimientos de caja este día.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col col-form">
            <div class="panel">
                <h3>Registrar movimiento</h3>
                <p class="hint">Ingresos y egresos de caja chica que no vienen de una reserva.</p>

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
