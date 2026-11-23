@extends('contador.layout')
@section('title', 'Reserva '.$booking->code)
@section('content')
    @php
        $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.');
        $tz = 'America/Santiago';
        $approved = $booking->payments->where('status', 'aprobado');
        $paid = (int) $approved->sum('amount') - (int) $approved->sum(fn ($p) => $p->refunds->where('status', 'procesado')->sum('amount'));
        $total = (int) $booking->price_final + $extras;
    @endphp
    <p><a href="javascript:history.back()" style="color:var(--accent);text-decoration:none;font-weight:700;">← Volver</a></p>
    <h1 style="margin:0 0 4px;">Reserva {{ $booking->code }}</h1>
    <p class="muted" style="margin:0 0 14px;">Ficha de solo lectura · estado {{ $booking->booking_status }} · pago {{ $booking->payment_status }}</p>

    <div class="kpis">
        <div class="kpi"><div class="k">Total de la estadía</div><div class="v">{{ $money($total) }}</div><div class="n">alojamiento {{ $money($booking->price_final) }} + extras {{ $money($extras) }}</div></div>
        <div class="kpi"><div class="k">Pagado</div><div class="v">{{ $money($paid) }}</div></div>
        <div class="kpi"><div class="k">Saldo</div><div class="v">{{ $money($total - $paid) }}</div></div>
    </div>

    <div class="card">
        <h2>Estadía</h2>
        <table><tbody>
            <tr><th>Cliente</th><td>{{ $booking->customer?->name ?: '—' }}</td></tr>
            <tr><th>RUT / documento</th><td>{{ $booking->customer?->documentNumber() ?: '—' }}</td></tr>
            <tr><th>Playroom</th><td>{{ $booking->room?->name }} <span class="muted">· {{ $booking->room?->category?->name }}</span></td></tr>
            <tr><th>Ingreso</th><td>{{ $booking->starts_at->copy()->timezone($tz)->format('d/m/Y H:i') }}</td></tr>
            <tr><th>Salida contratada</th><td>{{ $booking->ends_at?->copy()->timezone($tz)->format('d/m/Y H:i') }} <span class="muted">({{ $booking->duration_minutes / 60 }} h)</span></td></tr>
            <tr><th>Check-in real</th><td>{{ $booking->checked_in_at?->copy()->timezone($tz)->format('d/m/Y H:i') ?: '—' }}</td></tr>
            <tr><th>Check-out real</th><td>{{ $booking->checked_out_at?->copy()->timezone($tz)->format('d/m/Y H:i') ?: '—' }}</td></tr>
            <tr><th>Tarifa</th><td>{{ $booking->rate_rule_name_snapshot ?: '—' }}</td></tr>
            <tr><th>Precio original</th><td>{{ $money($booking->price_original) }}</td></tr>
            <tr><th>Descuento</th><td>{{ $booking->discount_amount ? $money($booking->discount_amount).' · '.$booking->coupon_code_snapshot : '—' }}</td></tr>
            <tr><th>Precio final</th><td><b>{{ $money($booking->price_final) }}</b></td></tr>
        </tbody></table>
    </div>

    <div class="card">
        <h2>Consumos y extras</h2>
        <table>
            <thead><tr><th>Detalle</th><th class="num">Cant.</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @forelse ($booking->addons as $a)
                    <tr><td>{{ $a->description }}</td><td class="num">{{ $a->quantity }}</td><td class="num">{{ $money($a->amount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">Sin consumos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Pagos</h2>
        <table>
            <thead><tr><th>Fecha</th><th>Medio</th><th>N° voucher</th><th>N° boleta</th><th>Referencia</th><th>Estado</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @forelse ($booking->payments->sortBy('created_at') as $p)
                    <tr>
                        <td>{{ $p->created_at->copy()->timezone($tz)->format('d/m/Y H:i') }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td>{{ $p->voucher_number ?: '—' }}</td>
                        <td>{{ $p->receipt_number ?: '—' }}</td>
                        <td class="muted">{{ $p->external_id }}</td>
                        <td>{{ $p->status }}@foreach ($p->refunds->where('status', 'procesado') as $r) · devuelto {{ $money($r->amount) }}@endforeach</td>
                        <td class="num">{{ $money($p->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin pagos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
