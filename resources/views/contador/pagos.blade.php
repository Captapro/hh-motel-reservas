@extends('contador.layout')
@section('title', 'Libro de cobros')
@section('content')
    @php $money = fn ($n) => ($n < 0 ? '−$' : '$').number_format(abs((int) $n), 0, ',', '.'); @endphp
    @include('contador._month', ['heading' => 'Libro de cobros', 'lead' => 'Cada pago aprobado del mes, con N° de voucher, boleta y operación de Mercado Pago, más las devoluciones.', 'routeName' => 'contador.pagos', 'exports' => [['Libro de cobros (CSV)', 'csv']]])
    <div class="card">
        <h2>Cobros · {{ $payments->count() }} · {{ $money($payments->sum('amount')) }}</h2>
        <table>
            <thead><tr><th>Fecha</th><th>Reserva</th><th>Medio</th><th class="num">Monto</th><th>N° voucher</th><th>N° boleta</th><th>N° operación MP</th><th>Registrado por</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    @php $noDoc = ! $p->voucher_number && ! $p->receipt_number && $p->paymentMethod?->code !== 'mercado_pago'; @endphp
                    <tr class="{{ $noDoc ? 'flag' : '' }}">
                        <td>{{ $p->created_at->copy()->timezone('America/Santiago')->format('d/m H:i') }}</td>
                        <td>{{ $p->booking?->code }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td class="num">{{ $money($p->amount) }}</td>
                        <td>{{ $p->voucher_number ?: '—' }}</td>
                        <td>{{ $p->receipt_number ?: '—' }}</td>
                        <td class="muted">{{ $p->mercadoPagoOperation() ?: '—' }}</td>
                        <td class="muted">{{ $p->registeredBy?->name ?: 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin cobros este mes.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($payments->contains(fn ($p) => ! $p->voucher_number && ! $p->receipt_number && $p->paymentMethod?->code !== 'mercado_pago'))
            <p class="muted" style="margin:10px 0 0;">Las filas en rojo no tienen N° de voucher ni de boleta.</p>
        @endif
    </div>

    <div class="card">
        <h2>Devoluciones · {{ $refunds->count() }} · {{ $money($refunds->sum('amount')) }}</h2>
        <table>
            <thead><tr><th>Fecha</th><th>Reserva</th><th>Medio</th><th class="num">Monto</th><th>Motivo</th><th>Procesó</th></tr></thead>
            <tbody>
                @forelse ($refunds as $r)
                    <tr>
                        <td>{{ $r->created_at->copy()->timezone('America/Santiago')->format('d/m H:i') }}</td>
                        <td>{{ $r->payment?->booking?->code }}</td>
                        <td>{{ $r->payment?->methodLabel() }}</td>
                        <td class="num">{{ $money(-$r->amount) }}</td>
                        <td>{{ $r->reason }}</td>
                        <td class="muted">{{ $r->processedBy?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Sin devoluciones este mes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
