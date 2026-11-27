@extends('reviews.layout')
@section('title', '¿Cómo fue tu experiencia?')
@section('content')
    @if (session('status'))
        <div class="banner" role="status">{{ session('status') }}</div>
    @endif
    <p class="eyebrow">Después de tu visita</p>
    <h1>¿Qué tal<br>estuvo HH?</h1>
    <p class="intro">La playrooms, la atención, los detalles.<br>¿Con qué nota te quedas?</p>
    <form class="review-form" method="POST" action="{{ route('reviews.store', $booking?->pass_token) }}">
        @csrf
        <fieldset>
            <legend>Tu visita, de 1 a 5 estrellas</legend>
            <div class="stars">
                @foreach ([1 => 'Muy mala', 2 => 'Mala', 3 => 'Regular', 4 => 'Buena', 5 => 'Excelente'] as $value => $label)
                    <label class="rating-option">
                        <input type="radio" name="rating" value="{{ $value }}" required @checked(old('rating') == $value) aria-label="{{ $value }} {{ $value === 1 ? 'estrella' : 'estrellas' }}: {{ $label }}" data-label="{{ $label }}">
                        <span class="rating-tile" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="m16 3 3.8 8 8.7 1.2-6.3 6.2 1.5 8.6-7.7-4.1-7.7 4.1 1.5-8.6-6.3-6.2 8.7-1.2Z"/></svg></span>
                    </label>
                @endforeach
            </div>
            <div class="scale" aria-hidden="true"><span>Muy mala</span><span>Excelente</span></div>
        </fieldset>
        <p class="selection" id="rating-label" aria-live="polite">Selecciona una estrella</p>
        @error('rating')<p class="error" role="alert">{{ $message }}</p>@enderror
        <button class="submit" type="submit">Enviar calificación</button>
    </form>
@endsection
@section('scripts')
<script>
    const ratings = document.querySelectorAll('input[name="rating"]');
    const ratingLabel = document.getElementById('rating-label');
    function describeRating(input) {
        ratingLabel.textContent = `${input.dataset.label} · ${input.value} de 5 estrellas`;
    }
    ratings.forEach(input => {
        input.addEventListener('change', () => describeRating(input));
        if (input.checked) describeRating(input);
    });
</script>
@endsection
