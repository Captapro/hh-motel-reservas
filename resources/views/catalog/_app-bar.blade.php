@php
    $appBack = $appBack ?? null;
    $appTitle = $appTitle ?? null;
    $appWa = 'https://wa.me/'.\App\Http\Controllers\CatalogController::WHATSAPP_NUMBER.'?text='.rawurlencode('Hola! Quiero reservar una habitación en HH.');
    $appHasOffers = $appHasOffers ?? false;
@endphp
<header class="app-bar">
    <div class="app-bar-in">
        @if ($appBack)
            <a class="ab-btn" href="{{ $appBack }}" aria-label="Volver"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></a>
        @else
            <span></span>
        @endif
        <a class="ab-center" href="{{ route('catalog.index') }}" aria-label="HH Motel — inicio">
            @if ($appTitle)
                <span class="ab-title">{{ $appTitle }}</span>
            @else
                <img src="{{ asset('images/hh-logo-wide.png').'?v=2' }}" alt="HH Motel Santiago Playrooms" width="720" height="280">
            @endif
        </a>
        <a class="ab-btn wa" href="{{ $appWa }}" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2a9.9 9.9 0 0 0-8.5 14.9L2 22l5.25-1.5A9.9 9.9 0 1 0 12.04 2Zm0 18.1c-1.5 0-2.9-.4-4.1-1.1l-.3-.2-3.1.9.9-3-.2-.3a8.1 8.1 0 1 1 6.8 3.7Zm4.5-6c-.25-.12-1.46-.72-1.69-.8-.23-.09-.39-.12-.56.12-.17.25-.64.8-.78.96-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.24-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.42h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07s.89 2.4 1.01 2.57c.12.17 1.75 2.67 4.24 3.75.59.26 1.06.41 1.42.52.6.19 1.14.16 1.57.1.48-.07 1.46-.6 1.67-1.17.2-.58.2-1.07.14-1.17-.06-.1-.23-.17-.48-.29Z"/></svg></a>
        <nav class="ab-nav" aria-label="Principal">
            <a href="{{ route('catalog.index') }}">Playrooms</a>
            @if ($appHasOffers)<a href="{{ route('catalog.index') }}#ofertas">Ofertas</a>@endif
            <a href="{{ $appWa }}" target="_blank" rel="noopener">WhatsApp</a>
            <a class="primary" href="{{ route('catalog.reserve') }}">Reservar</a>
        </nav>
    </div>
</header>
