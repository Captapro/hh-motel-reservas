@php
    $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
    $labels = ['alojamiento' => 'Alojamiento', 'hora_extra' => 'Hora extra', 'persona_adicional' => 'Persona adicional', 'servicios' => 'Otro servicio', 'extras' => 'Extra', 'fraccionado' => 'Pago fraccionado'];
    $duration = $sale->duration_minutes ? intdiv($sale->duration_minutes, 60).':'.str_pad($sale->duration_minutes % 60, 2, '0', STR_PAD_LEFT).' h' : null;
@endphp
<div class="sd-head">
    <div>
        <div class="sd-code">Planilla · N° {{ $sale->source_row }}</div>
        <h2>{{ $labels[$sale->concept()] ?? ucfirst(mb_strtolower($sale->sale_type)) }}@if ($sale->detail) · {{ $sale->detail }}@endif</h2>
    </div>
    <span class="sd-status">Antes del sistema</span>
</div>

<div class="sd-sec">Venta</div>
<dl class="sd-dl">
    <dt>Fecha</dt><dd>{{ $sale->sold_on->locale('es')->isoFormat('dddd D [de] MMMM YYYY') }}</dd>
    @if ($sale->room_label)<dt>Playroom</dt><dd>{{ $sale->room_label }}{{ $sale->category ? ' · '.$sale->category : '' }}</dd>@endif
    @if ($sale->starts_time || $sale->ends_time)<dt>Horario</dt><dd>{{ $sale->starts_time ?: '—' }} — {{ $sale->ends_time ?: '—' }}{{ $duration ? ' ('.$duration.')' : '' }}</dd>@endif
    @if ($sale->hours_paid)<dt>Horas pagadas</dt><dd>{{ $sale->hours_paid }} h</dd>@endif
    @if ($sale->guests_count)<dt>Personas</dt><dd>{{ $sale->guests_count }}</dd>@endif
    @if ($sale->shift)<dt>Turno</dt><dd>{{ ucfirst(mb_strtolower($sale->shift)) }}</dd>@endif
    @if ($sale->reservation_ref)<dt>Reserva</dt><dd>{{ $sale->reservation_ref }}</dd>@endif
</dl>

@if ($sale->guests->isNotEmpty())
    <div class="sd-sec">Personas</div>
    @if ($showNames)
        <ul class="sd-list">
            @foreach ($sale->guests as $g)
                <li>{{ $g->name }}@if ($g->is_holder) <span class="sd-mut">· titular</span>@endif
                    <span class="sd-mut">@if ($g->document)· {{ $g->document }}@endif @if ($g->nationality)· {{ $g->nationality }}@endif @if ($g->age)· {{ $g->age }} años @endif</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="sd-mut">{{ $sale->guests->count() }} {{ $sale->guests->count() === 1 ? 'persona' : 'personas' }}. Los nombres y RUT solo los ve soporte.</p>
    @endif
@endif

<div class="sd-sec">Pago</div>
<dl class="sd-dl">
    <dt>Medio</dt><dd>{{ $sale->payment_method ? ucfirst($sale->payment_method) : '—' }}</dd>
    <dt>Documento</dt><dd>{{ $sale->receipt_number ? 'Boleta '.$sale->receipt_number : ($sale->voucher_number ? 'Voucher '.$sale->voucher_number : '—') }}</dd>
    @if ($sale->discount_note)<dt>Descuento</dt><dd>{{ $sale->discount_note }}</dd>@endif
</dl>

@if ($related->isNotEmpty())
    <div class="sd-sec">Cobrado junto con este documento</div>
    <table class="sd-tbl">
        @foreach ($related as $r)
            <tr><td>{{ $labels[$r->concept()] ?? $r->sale_type }}@if ($r->detail) · {{ $r->detail }}@endif</td><td class="n">{{ $money($r->total()) }}</td></tr>
        @endforeach
    </table>
@endif

@if ($sale->notes)
    <div class="sd-sec">Observaciones</div>
    <p class="sd-notes">{{ $sale->notes }}</p>
@endif

<div class="sd-total">
    <div><span>Total de esta línea</span><b>{{ $money($sale->total()) }}</b></div>
</div>
