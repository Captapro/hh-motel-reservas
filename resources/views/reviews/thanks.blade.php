@extends('reviews.layout')
@section('title', 'Gracias por tu opinión')
@section('content')
    <p class="eyebrow">Opinión recibida</p>
    <h1>Gracias por<br>contarnos.</h1>
    <p class="intro">Recibimos tu opinión. El equipo de HH la revisará.</p>
    @if ($coupon ?? null)
        {{-- Premio por opinar: cupón personal para la próxima visita. --}}
        <div class="banner" role="status" style="text-align:center;">
            <div style="font-size:15px;">Tu regalo para la próxima visita</div>
            <div style="font-size:30px;font-weight:800;letter-spacing:.06em;margin:8px 0;">{{ $coupon->code }}</div>
            <div style="font-size:15px;">{{ (int) $coupon->discount_value }}% de descuento, cualquier día y tarifa · válido hasta el {{ $coupon->ends_at->locale('es')->isoFormat('D [de] MMMM') }} · un uso, solo para ti</div>
        </div>
        <a class="submit" style="display:block;text-align:center;text-decoration:none;margin-top:16px;" href="{{ route('catalog.reserve', ['cupon' => $coupon->code]) }}">Reservar con mi descuento</a>
    @endif
    @if ($googleLink ?? null)
        <p class="intro" style="margin-top:22px;">¿Te gustó? Si quieres, también puedes contarlo en Google (opcional).</p>
        <a class="submit" style="display:block;text-align:center;text-decoration:none;background:transparent;border:1px solid currentColor;" href="{{ $googleLink }}" target="_blank" rel="noopener">Opinar en Google</a>
    @endif
@endsection
