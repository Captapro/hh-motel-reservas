@extends('admin.layout')
@section('title', 'Entregas de turno')
@section('content')
    @php
        $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $signed = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).'$'.number_format(abs((int) $n), 0, ',', '.');
    @endphp
    <div class="page-head">
        <div>
            <h1>Entregas de turno</h1>
            <p class="sub">Lo que cada anfitrión contó al recibir y al entregar su turno: caja chica y productos. Lo contado queda como saldo y stock del sistema; las diferencias se registran como ajuste.</p>
        </div>
        <a class="link" href="{{ route('cash.show') }}">← Volver a Caja</a>
    </div>

    <div class="card">
        @forelse ($handovers as $h)
            @php $diffItems = $h->items->filter(fn ($i) => $i->difference() !== 0); @endphp
            <div style="padding:12px 0; border-bottom:1px solid #262626;">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:baseline;">
                    <div>
                        <b>{{ $h->user?->name ?? '—' }}</b>
                        <span class="pill">{{ $h->kind === 'recibir' ? 'Recibió' : 'Entregó' }}</span>
                        <span class="sub" style="margin-left:6px;">{{ $h->created_at->timezone('America/Santiago')->format('d/m H:i') }}</span>
                    </div>
                    <div>
                        @if (! $h->has_differences)
                            <span class="pill" style="background:#14251c; color:#8fe0ad;">Cuadró</span>
                        @elseif ($h->reviewed_at)
                            <span class="pill">Diferencia revisada</span>
                        @else
                            <span class="pill pill-bad">Con diferencias</span>
                            <form class="inline" method="POST" action="{{ route('cash.shifts.review', $h) }}">
                                @csrf
                                <button class="btn secondary" type="submit" style="padding:5px 10px; font-size:12px; margin-left:6px;">Marcar revisada</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div style="font-size:13.5px; margin-top:6px;">
                    Caja: contó <b>{{ $money($h->counted_cash) }}</b> · sistema {{ $money($h->expected_cash) }}
                    @if ($h->cashDifference() !== 0)
                        · <span style="color:{{ $h->cashDifference() < 0 ? '#e88a9a' : '#e8c76f' }}; font-weight:700;">{{ $signed($h->cashDifference()) }}</span>
                    @endif
                </div>
                @if ($diffItems->isNotEmpty())
                    <div style="font-size:13.5px; margin-top:4px;">
                        Productos:
                        @foreach ($diffItems as $i)
                            {{ $i->product_name }} contó {{ $i->counted_stock }} (sistema {{ $i->expected_stock }}, <span style="color:{{ $i->difference() < 0 ? '#e88a9a' : '#e8c76f' }}; font-weight:700;">{{ $i->difference() > 0 ? '+' : '' }}{{ $i->difference() }}</span>){{ $loop->last ? '' : ' · ' }}
                        @endforeach
                    </div>
                @elseif ($h->items->isNotEmpty())
                    <div class="sub" style="margin:4px 0 0;">Productos: {{ $h->items->count() }} contados, todos cuadran.</div>
                @endif
                @if ($h->notes)
                    <div style="font-size:13.5px; margin-top:4px; color:#ccc;">Nota: “{{ $h->notes }}”</div>
                @endif
            </div>
        @empty
            <p class="sub">Todavía no hay entregas de turno.</p>
        @endforelse
    </div>
@endsection
