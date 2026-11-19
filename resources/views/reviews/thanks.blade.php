@extends('reviews.layout')
@section('title', 'Gracias por tu opinión')
@section('content')
    <p class="eyebrow">Opinión recibida</p>
    <h1>Gracias por<br>contarnos.</h1>
    <p class="intro">Recibimos tu opinión. El equipo de HH la revisará.</p>
    @if ($googleLink ?? null)
        {{-- Paso principal para las notas altas: llevar la opinión a la ficha de Google. --}}
        <div class="banner" style="text-align:center;margin-top:22px;">
            <div style="font-size:20px;font-weight:800;">¡Nos alegra que te haya gustado! ⭐</div>
            <p style="margin:8px 0 14px;font-size:15px;">Ayúdanos a que más personas nos encuentren: cuéntalo en Google, toma 20 segundos.</p>
            <a class="submit" style="display:block;text-align:center;text-decoration:none;" href="{{ route('reviews.google', $review) }}" target="_blank" rel="noopener">Dejar mi reseña en Google</a>
        </div>
    @endif
    @if ($coupon ?? null)
        {{-- Premio por opinar: cupón personal para la próxima visita. --}}
        <div class="banner" role="status" style="text-align:center;">
            <div style="font-size:15px;">Tu regalo para la próxima visita</div>
            <div style="font-size:30px;font-weight:800;letter-spacing:.06em;margin:8px 0;">{{ $coupon->code }}</div>
            <div style="font-size:15px;">{{ (int) $coupon->discount_value }}% de descuento, cualquier día y tarifa · válido hasta el {{ $coupon->ends_at->locale('es')->isoFormat('D [de] MMMM') }} · un uso, solo para ti</div>
        </div>
        <a class="submit" style="display:block;text-align:center;text-decoration:none;margin-top:16px;" href="{{ route('catalog.reserve', ['cupon' => $coupon->code]) }}">Reservar con mi descuento</a>
    @endif
@endsection
