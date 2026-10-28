@php
    $tabOn = $tabOn ?? 'home';
    $tabHasOffers = $tabHasOffers ?? false;
    $tabWa = 'https://wa.me/'.\App\Http\Controllers\CatalogController::WHATSAPP_NUMBER.'?text='.rawurlencode('Hola! Quiero reservar un Playroom en HH.');
@endphp
<nav class="tab-bar" aria-label="Navegación">
    <div class="tab-bar-in">
        <a class="tab {{ $tabOn === 'home' ? 'on' : '' }}" href="{{ route('catalog.index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>
            Playrooms
        </a>
        @if ($tabHasOffers)
            <a class="tab {{ $tabOn === 'offers' ? 'on' : '' }}" href="{{ route('catalog.index') }}#ofertas">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.2" fill="currentColor"/></svg>
                Ofertas
            </a>
        @endif
        <a class="tab cta" href="{{ route('catalog.reserve') }}"><span>Reservar</span></a>
        <a class="tab" href="{{ $tabWa }}" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.7-5.2A8.5 8.5 0 1 1 21 12Z"/></svg>
            WhatsApp
        </a>
    </div>
</nav>
