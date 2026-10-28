@extends('admin.layout')
@section('title', 'Ventas históricas — detalle')
@section('content')
    @php $money = fn ($n) => '$'.number_format($n, 0, ',', '.'); @endphp
    <div class="page-head">
        <div>
            <h1>Ventas históricas · detalle por día</h1>
            <p class="sub"><a href="{{ route('admin.historical.index') }}" style="color:#ff9a4d;">← Volver a cargar planillas</a> · Solo soporte. Son ventas anteriores al sistema y no cambian la caja ni Analytics.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.historical.sales') }}" style="display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap;">
        <label style="margin:0;">Mes</label>
        <select name="mes" onchange="this.form.submit()" style="max-width:220px;">
            @forelse ($available as $m)
                <option value="{{ $m }}" @selected($m === $month)>{{ \Carbon\Carbon::parse($m.'-01')->locale('es')->isoFormat('MMMM YYYY') }}</option>
            @empty
                <option>Sin planillas cargadas</option>
            @endforelse
        </select>
    </form>

    <div class="card" style="overflow-x:auto;">
        <h2>{{ \Carbon\Carbon::parse($month.'-01')->locale('es')->isoFormat('MMMM YYYY') }}, día por día</h2>
        @if ($days->isEmpty())
            <p class="sub">No hay ventas cargadas en este mes.</p>
        @else
            <table class="table">
                <thead><tr><th>Día</th><th>Estadías</th><th>Alojamiento</th><th>Hora extra</th><th>Persona adicional</th><th>Otros extra core</th><th>Extras</th><th>Pago fraccionado</th><th>Total</th><th>Efectivo</th><th>Tarjeta</th><th>Transferencia</th></tr></thead>
                <tbody>
                @foreach ($days as $d)
                    <tr @if ($d['date'] === $day) style="background:#fff0d9;" @endif>
                        <td><a href="{{ route('admin.historical.sales', ['mes' => $month, 'dia' => $d['date']]) }}#detalle" style="color:#ff9a4d;">{{ \Carbon\Carbon::parse($d['date'])->format('d/m') }} · {{ $d['weekday'] }}</a></td>
                        <td>{{ $d['stays'] }}</td><td>{{ $money($d['lodging']) }}</td><td>{{ $money($d['extra_hour']) }}</td><td>{{ $money($d['extra_person']) }}</td><td>{{ $money($d['services']) }}</td><td>{{ $money($d['extras']) }}</td><td>{{ $money($d['split']) }}</td>
                        <td><b>{{ $money($d['total']) }}</b></td><td>{{ $money($d['cash']) }}</td><td>{{ $money($d['card']) }}</td><td>{{ $money($d['transfer']) }}</td>
                    </tr>
                @endforeach
                <tr style="border-top:2px solid #555; font-weight:700;">
                    <td>Total del mes</td><td>{{ $days->sum('stays') }}</td><td>{{ $money($days->sum('lodging')) }}</td><td>{{ $money($days->sum('extra_hour')) }}</td><td>{{ $money($days->sum('extra_person')) }}</td><td>{{ $money($days->sum('services')) }}</td><td>{{ $money($days->sum('extras')) }}</td><td>{{ $money($days->sum('split')) }}</td>
                    <td>{{ $money($days->sum('total')) }}</td><td>{{ $money($days->sum('cash')) }}</td><td>{{ $money($days->sum('card')) }}</td><td>{{ $money($days->sum('transfer')) }}</td>
                </tr>
                </tbody>
            </table>
        @endif
    </div>

    <div class="cols cols-2" style="margin-top:18px;">
        <div class="card">
            <h2>Por categoría</h2>
            <table class="table"><thead><tr><th>Categoría</th><th>Estadías</th><th>Venta</th></tr></thead><tbody>
                @foreach ($byCategory as $r)<tr><td>{{ $r['label'] }}</td><td>{{ $r['count'] }}</td><td>{{ $money($r['total']) }}</td></tr>@endforeach
            </tbody></table>
        </div>
        <div class="card">
            <h2>Por medio de pago y turno</h2>
            <table class="table"><thead><tr><th>Medio de pago</th><th>Líneas</th><th>Venta</th></tr></thead><tbody>
                @foreach ($byPayment as $r)<tr><td>{{ ucfirst($r['label']) }}</td><td>{{ $r['count'] }}</td><td>{{ $money($r['total']) }}</td></tr>@endforeach
            </tbody></table>
            <table class="table" style="margin-top:12px;"><thead><tr><th>Turno</th><th>Estadías</th><th>Venta</th></tr></thead><tbody>
                @foreach ($byShift as $r)<tr><td>{{ $r['label'] }}</td><td>{{ $r['count'] }}</td><td>{{ $money($r['total']) }}</td></tr>@endforeach
            </tbody></table>
        </div>
    </div>

    @if ($day)
        <div class="card" id="detalle" style="margin-top:18px; overflow-x:auto;">
            <h2>Ventas del {{ \Carbon\Carbon::parse($day)->locale('es')->isoFormat('dddd D [de] MMMM') }} · {{ $detail->count() }} líneas · {{ $money($detail->sum(fn ($s) => $s->total())) }}</h2>
            <table class="table">
                <thead><tr><th>N°</th><th>Tipo</th><th>Detalle</th><th>Playroom</th><th>Horas</th><th>Ingreso</th><th>Salida</th><th>Personas</th><th>Monto</th><th>Pago</th><th>Documento</th><th>Reserva</th><th>Turno</th><th>Obs.</th></tr></thead>
                <tbody>
                @foreach ($detail as $s)
                    <tr>
                        <td>{{ $s->source_row }}</td>
                        <td>{{ ['alojamiento' => 'Alojamiento', 'hora_extra' => 'Hora extra', 'persona_adicional' => 'Persona adicional', 'servicios' => 'Otro extra core', 'extras' => 'Extra', 'fraccionado' => 'Pago fraccionado'][$s->concept()] ?? ucfirst(mb_strtolower($s->sale_type)) }}</td>
                        <td>{{ $s->detail ?: ($s->category ?: '') }}@if ($s->discount_note)<br><span class="sub" style="font-size:12px;">{{ $s->discount_note }}</span>@endif</td>
                        <td>{{ $s->room_label }}{{ $s->category && $s->room_label ? ' · '.$s->category : '' }}</td>
                        <td>{{ $s->hours_paid ? $s->hours_paid.' h' : '' }}</td>
                        <td>{{ $s->starts_time }}</td><td>{{ $s->ends_time }}</td>
                        <td>@foreach ($s->guests as $g){{ $g->name }}@if ($g->is_holder) <span class="pill">titular</span>@endif<br>@endforeach</td>
                        <td>{{ $money($s->total()) }}</td>
                        <td>{{ $s->payment_method }}</td>
                        <td>{{ $s->receipt_number ? 'Boleta '.$s->receipt_number : ($s->voucher_number ? 'Voucher '.$s->voucher_number : '') }}</td>
                        <td>{{ $s->reservation_ref }}</td>
                        <td>{{ $s->shift }}</td>
                        <td style="max-width:220px;">{{ $s->notes }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
