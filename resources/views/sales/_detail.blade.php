@php
    $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
    $tz = 'America/Santiago';
    $c = $booking->customer;
    $statusLabel = [
        'PENDIENTE_PAGO' => 'Pendiente de pago', 'CONFIRMADA' => 'Confirmada', 'CHECK_IN' => 'En curso',
        'FINALIZADA' => 'Finalizada', 'CANCELADA' => 'Cancelada', 'EXPIRADA' => 'Expirada', 'NO_SHOW' => 'No show',
    ][$booking->booking_status] ?? $booking->booking_status;
    $dur = $booking->duration_minutes >= 60
        ? rtrim(rtrim(number_format($booking->duration_minutes / 60, 1, ',', ''), '0'), ',').' h'
        : $booking->duration_minutes.' min';
    $docLabel = $c->document_type === 'pasaporte' ? 'Pasaporte' : 'RUT';
@endphp
<div class="sd-head">
    <div>
        <div class="sd-code">{{ $booking->code }}</div>
        <h2>{{ $c->name }}</h2>
    </div>
    <span class="sd-status s-{{ strtolower($booking->booking_status) }}">{{ $statusLabel }}</span>
</div>

<div class="sd-sec">Estadía</div>
<dl class="sd-dl">
    <dt>Playroom</dt><dd>{{ $booking->room->name }} · {{ $booking->room->category->name }}</dd>
    <dt>Fecha</dt><dd>{{ $booking->starts_at->timezone($tz)->locale('es')->isoFormat('ddd D [de] MMM YYYY') }}</dd>
    <dt>Horario</dt><dd>{{ $booking->starts_at->timezone($tz)->format('H:i') }} — {{ $booking->ends_at->timezone($tz)->format('H:i') }} ({{ $dur }})</dd>
    <dt>Origen</dt><dd>{{ $booking->originLabel() }}</dd>
    <dt>Personas</dt><dd>{{ $booking->guests_count }}</dd>
    @if ($booking->checked_in_at)<dt>Check-in</dt><dd>{{ $booking->checked_in_at->timezone($tz)->format('d/m H:i') }}</dd>@endif
    @if ($booking->checked_out_at)<dt>Check-out</dt><dd>{{ $booking->checked_out_at->timezone($tz)->format('d/m H:i') }}</dd>@endif
    <dt>Registrada por</dt><dd>{{ $booking->createdBy?->name ?? 'Catálogo en línea' }} · {{ $booking->created_at->timezone($tz)->format('d/m H:i') }}</dd>
</dl>

<div class="sd-sec">Cliente</div>
<dl class="sd-dl">
    <dt>Teléfono</dt><dd>{{ $c->phone ?: '—' }}</dd>
    <dt>Correo</dt><dd>{{ $c->email ?: '—' }}</dd>
    <dt>{{ $docLabel }}</dt><dd>{{ $c->documentNumber() ?: '—' }}</dd>
    <dt>Nacionalidad</dt><dd>{{ $c->nationality ?: '—' }}</dd>
</dl>
@if ($booking->guests->isNotEmpty())
    <div class="sd-sec">Acompañantes</div>
    <ul class="sd-list">
        @foreach ($booking->guests as $g)
            <li>{{ $g->name }}@if ($g->id_document) <span class="sd-mut">· {{ $g->id_document }}</span>@endif</li>
        @endforeach
    </ul>
@endif

<div class="sd-sec">Precio</div>
<dl class="sd-dl">
    <dt>Tarifa</dt><dd>{{ $booking->rate_rule_name_snapshot ?: '—' }}</dd>
    <dt>Precio base</dt><dd>{{ $money($booking->price_original) }}</dd>
    @if ($booking->extra_guests_fee > 0)<dt>Personas extra</dt><dd>{{ $money($booking->extra_guests_fee) }}</dd>@endif
    @if ($booking->discount_amount > 0)<dt>Descuento{{ $booking->coupon_code_snapshot ? ' ('.$booking->coupon_code_snapshot.')' : '' }}</dt><dd>− {{ $money($booking->discount_amount) }}</dd>@endif
    <dt>Playroom</dt><dd><b>{{ $money($booking->price_final) }}</b></dd>
</dl>

<div class="sd-sec">Consumos</div>
@if ($booking->addons->isEmpty())
    <p class="sd-mut">Sin consumos.</p>
@else
    <table class="sd-tbl">
        @foreach ($booking->addons as $a)
            <tr><td>{{ $a->description }}@if ($a->quantity > 1) <span class="sd-mut">× {{ $a->quantity }}</span>@endif<div class="sd-mut sd-small">{{ $a->addedBy?->name ?? '' }} {{ $a->created_at?->timezone($tz)->format('d/m H:i') }}</div></td><td class="n">{{ $money($a->amount) }}</td></tr>
        @endforeach
        <tr class="sum"><td>Total consumos</td><td class="n">{{ $money($extras) }}</td></tr>
    </table>
@endif

<div class="sd-sec">Pagos</div>
@if ($booking->payments->isEmpty())
    <p class="sd-mut">Todavía no hay pagos registrados.</p>
@else
    <table class="sd-tbl">
        @foreach ($booking->payments as $p)
            <tr>
                <td>
                    {{ $p->paymentMethod?->name ?? '—' }}
                    @if ($p->status !== 'aprobado')<span class="sd-tag">{{ $p->status }}</span>@endif
                    <div class="sd-mut sd-small">
                        {{ $p->created_at->timezone($tz)->format('d/m H:i') }}
                        @if ($p->registeredBy) · {{ $p->registeredBy->name }}@endif
                        @if ($p->voucher_number) · Voucher {{ $p->voucher_number }}@endif
                        @if ($p->receipt_number) · Boleta {{ $p->receipt_number }}@endif
                    </div>
                    @if ($p->notes)<div class="sd-mut sd-small">{{ $p->notes }}</div>@endif
                </td>
                <td class="n">{{ $money($p->amount) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="sd-total">
    <div><span>Total venta</span><b>{{ $money($booking->price_final + $extras) }}</b></div>
    <div><span>Pagado</span><b>{{ $money($paid) }}</b></div>
    <div class="{{ $balance > 0 ? 'owe' : '' }}"><span>Saldo</span><b>{{ $balance > 0 ? $money($balance) : 'Al día' }}</b></div>
</div>

@if ($booking->notes)
    <div class="sd-sec">Notas</div>
    <p class="sd-notes">{{ $booking->notes }}</p>
@endif

<a class="sd-open" href="{{ route('reservations.show', $booking->code) }}">Abrir la reserva completa →</a>
