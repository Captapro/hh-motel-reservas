@extends('admin.layout')
@section('title', 'Venta pasada (planilla)')
@section('content')
    <style>
        .hint { color:#888; font-size:12px; margin-top:4px; line-height:1.4; }
        .ps-warn { margin-bottom:14px; padding:12px 14px; border-radius:8px; background:#2a2a1c; border:1px solid #5a5230; color:#ffe7a8; font-size:13px; line-height:1.55; }
    </style>

    <h1>Registrar venta pasada (de planilla)</h1>
    <p class="sub">Para estadías que recepción anotó en la planilla y no en el sistema. Quedan finalizadas y pagadas con su horario real.</p>
    <div class="ps-warn">⚠ <b>No cambia el estado de la pieza</b> (si está en aseo o disponible, sigue igual), no avisa por WhatsApp y los consumos no descuentan stock. Revisa en la planilla antes de guardar: cada venta se carga una sola vez.</div>

    <div class="cols cols-2">
        <div class="card">
            <h2>Estadía</h2>
            <form method="POST" action="{{ route('admin.past_sales.store') }}">
                @csrf
                <div class="row2">
                    <div>
                        <label>Playroom</label>
                        <select name="room_id" required>
                            <option value="">Elegir…</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>{{ $room->name }} · {{ $room->category?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>Fecha de ingreso</label><input type="date" name="date" value="{{ old('date', now('America/Santiago')->toDateString()) }}" required></div>
                </div>
                <div class="row2">
                    <div><label>Hora ingreso</label><input type="time" name="start_time" value="{{ old('start_time') }}" required></div>
                    <div><label>Hora salida</label><input type="time" name="end_time" value="{{ old('end_time') }}" required><div class="hint">Si sale al día siguiente, el sistema lo entiende solo.</div></div>
                    <div><label>Personas</label><input type="number" name="guests_count" min="1" max="10" value="{{ old('guests_count', 2) }}" required></div>
                </div>
                <label>Nombre del cliente</label>
                <input name="customer_name" maxlength="150" value="{{ old('customer_name') }}" required>
                <div class="row2">
                    <div>
                        <label>Documento</label>
                        <select name="document_type">
                            <option value="rut" @selected(old('document_type', 'rut') === 'rut')>RUT</option>
                            <option value="pasaporte" @selected(old('document_type') === 'pasaporte')>Pasaporte</option>
                        </select>
                    </div>
                    <div><label>N° documento</label><input name="document_number" maxlength="30" value="{{ old('document_number') }}" required></div>
                    <div><label>Nacionalidad</label><input name="nationality" maxlength="60" value="{{ old('nationality', 'CHILE') }}"></div>
                </div>
                <label>Acompañante (opcional)</label>
                <input name="companion" maxlength="150" value="{{ old('companion') }}" placeholder="Nombre y RUT">
                <div class="row2">
                    <div><label>Monto pagado</label><input type="number" name="amount" min="0" step="1" value="{{ old('amount') }}" required><div class="hint">El total que dice la planilla, con descuento incluido.</div></div>
                    <div>
                        <label>Medio de pago</label>
                        <select name="payment_method" required>
                            @foreach ($methods as $m)
                                <option value="{{ $m->code }}" @selected(old('payment_method') === $m->code)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row2">
                    <div><label>N° voucher</label><input name="voucher_number" maxlength="30" value="{{ old('voucher_number') }}"></div>
                    <div><label>N° boleta</label><input name="receipt_number" maxlength="30" value="{{ old('receipt_number') }}"></div>
                </div>
                <label>Observaciones</label>
                <input name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Ej. 15% Halloween, reserva R1102">
                <div class="actions" style="margin-top:16px;"><button class="btn" type="submit">Guardar estadía</button></div>
            </form>
        </div>

        <div class="card">
            <h2>Consumo de una estadía ya cargada</h2>
            <form method="POST" action="{{ route('admin.past_sales.addon') }}">
                @csrf
                <label>Código de la reserva</label>
                <input name="booking_code" value="{{ old('booking_code') }}" placeholder="HH-20261101-000XX" required>
                <div class="row2">
                    <div>
                        <label>Producto</label>
                        <select name="product_id">
                            <option value="">Elegir…</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} · ${{ number_format($p->price, 0, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:0 1 90px;"><label>Cantidad</label><input type="number" name="quantity" min="1" max="20" value="{{ old('quantity', 1) }}" required></div>
                </div>
                <div class="hint" style="margin:-4px 0 8px;">O, si no es un producto, un cobro libre (deja el producto en "Elegir…"):</div>
                <div class="row2">
                    <div><label>Descripción</label><input name="custom_description" maxlength="150" value="{{ old('custom_description') }}" placeholder="Ej. Diferencia hora adicional HOT"></div>
                    <div style="flex:0 1 140px;"><label>Monto</label><input type="number" name="custom_amount" min="1" step="1" value="{{ old('custom_amount') }}"></div>
                </div>
                <div class="row2">
                    <div><label>Hora de la venta</label><input type="time" name="sold_at" value="{{ old('sold_at') }}" required></div>
                    <div>
                        <label>Medio de pago</label>
                        <select name="payment_method" required>
                            @foreach ($methods as $m)
                                <option value="{{ $m->code }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row2">
                    <div><label>N° voucher</label><input name="voucher_number" maxlength="30"></div>
                    <div><label>N° boleta</label><input name="receipt_number" maxlength="30"></div>
                </div>
                <label>Nota del pago (opcional)</label>
                <input name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="Por defecto: Cargado desde planilla">
                <div class="actions" style="margin-top:16px;"><button class="btn" type="submit">Agregar consumo</button></div>
            </form>

            @if ($recent->isNotEmpty())
                <h2 style="margin-top:22px;">Cargadas desde planilla</h2>
                <table class="tbl-compact">
                    <thead><tr><th>Reserva</th><th>Pieza</th><th>Cliente</th><th>Horario</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $b)
                            <tr>
                                <td><a class="link" href="{{ route('reservations.show', $b->code) }}">{{ $b->code }}</a></td>
                                <td>{{ $b->room?->name }}</td>
                                <td>{{ $b->customer?->name }}</td>
                                <td>{{ $b->starts_at->timezone('America/Santiago')->format('d/m H:i') }}–{{ $b->ends_at->timezone('America/Santiago')->format('H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
