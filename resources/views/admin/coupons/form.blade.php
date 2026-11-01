@extends('admin.layout')
@section('title', $coupon->exists ? 'Editar cupón' : 'Nuevo cupón')
@section('content')
    @php
        $days = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];
        $selectedDays = old('allowed_weekdays', $coupon->allowed_weekdays ?? []);
    @endphp
    <h1>{{ $coupon->exists ? 'Editar cupón' : 'Nuevo cupón' }}</h1>
    <p class="sub">{{ $coupon->exists ? $coupon->code : 'Crear una nueva promoción' }}</p>

    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <div class="row2">
            <div>
                <label>Código</label>
                <input type="text" name="code" value="{{ old('code', $coupon->code) }}" placeholder="MORNING ESCAPE" required>
            </div>
            <div>
                <label>Nombre interno</label>
                <input type="text" name="internal_name" value="{{ old('internal_name', $coupon->internal_name) }}" required>
            </div>
        </div>

        <label>Imagen del banner (opcional, para el carrusel del catálogo)</label>
        <input type="text" name="image_url" value="{{ old('image_url', $coupon->image_url) }}" placeholder="https://...">

        <div class="row2">
            <div>
                <label>Tipo de descuento</label>
                <select name="discount_type">
                    <option value="percentage" @selected(old('discount_type', $coupon->discount_type) === 'percentage')>Porcentaje</option>
                    <option value="fixed" @selected(old('discount_type', $coupon->discount_type) === 'fixed')>Monto fijo (CLP)</option>
                </select>
            </div>
            <div>
                <label>Valor del descuento</label>
                <input type="number" name="discount_value" min="0" value="{{ old('discount_value', $coupon->discount_value) }}" required>
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Monto mínimo de reserva (opcional)</label>
                <input type="number" name="min_amount" min="0" value="{{ old('min_amount', $coupon->min_amount) }}">
            </div>
            <div>
                <label>Descuento máximo (opcional, CLP)</label>
                <input type="number" name="max_discount_amount" min="0" value="{{ old('max_discount_amount', $coupon->max_discount_amount) }}">
            </div>
        </div>

        <label>Días permitidos (vacío = todos)</label>
        <div class="row2" style="flex-wrap:wrap;">
            @foreach ($days as $value => $label)
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; flex:none; margin-right:14px;">
                    <input type="checkbox" name="allowed_weekdays[]" value="{{ $value }}" style="width:auto;" @checked(in_array($value, $selectedDays))>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="row2">
            <div>
                <label>Horario desde (opcional)</label>
                <input type="time" name="allowed_time_start" value="{{ old('allowed_time_start', $coupon->allowed_time_start) }}">
            </div>
            <div>
                <label>Horario hasta (opcional)</label>
                <input type="time" name="allowed_time_end" value="{{ old('allowed_time_end', $coupon->allowed_time_end) }}">
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Vigencia desde (opcional)</label>
                <input type="date" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->toDateString()) }}">
            </div>
            <div>
                <label>Vigencia hasta (opcional)</label>
                <input type="date" name="ends_at" value="{{ old('ends_at', optional($coupon->ends_at)->toDateString()) }}">
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Usos máximos totales (opcional)</label>
                <input type="number" name="max_uses_total" min="0" value="{{ old('max_uses_total', $coupon->max_uses_total) }}">
            </div>
            <div>
                <label>Usos máximos por cliente (opcional)</label>
                <input type="number" name="max_uses_per_customer" min="0" value="{{ old('max_uses_per_customer', $coupon->max_uses_per_customer) }}">
            </div>
        </div>

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
            <input type="checkbox" name="requires_verification" value="1" style="width:auto;" @checked(old('requires_verification', $coupon->requires_verification))>
            Exige verificar un documento en persona
        </label>
        <input type="text" name="verification_note" value="{{ old('verification_note', $coupon->verification_note) }}" placeholder="Ej. Cédula de identidad — 65 años o más" style="margin-top:8px;">

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
            <input type="checkbox" name="is_stackable" value="1" style="width:auto;" @checked(old('is_stackable', $coupon->is_stackable))>
            Acumulable con otras promociones
        </label>

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
            <input type="checkbox" name="internal_only" value="1" style="width:auto;" @checked(old('internal_only', $coupon->exists && ! $coupon->is_public))>
            Uso interno: no mostrar en el catálogo público (solo recepción lo escribe)
        </label>

        <label>Estado</label>
        <select name="is_active">
            <option value="1" @selected(old('is_active', $coupon->is_active ?? true))>Activo</option>
            <option value="0" @selected(!old('is_active', $coupon->is_active ?? true))>Inactivo</option>
        </select>

        <div class="actions" style="margin-top:20px;">
            <button class="btn" type="submit">Guardar</button>
            <a class="link" href="{{ route('admin.coupons.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
