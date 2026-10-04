<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Playrooms — HH Motel</title>
    <style>
        /* Mismos tokens que public/css/hh-theme.css (el tema real del tablero
           interno): lienzo gris claro, tarjetas en superficie oscura, acento
           naranjo. Esta página es pública y no incluye el navbar/rail interno,
           pero usa los mismos colores para sentirse parte de la misma app. */
        :root {
            color-scheme: light;
            --hh-accent: #ff7918;
            --hh-accent-hover: #ee6909;
            --hh-canvas: #f5f5f4;
            --hh-surface: #353638;
            --hh-ink: #202124;
        }
        * { box-sizing: border-box; }
        body { background:var(--hh-canvas); color:var(--hh-ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; line-height: 1.45; }
        a { color: inherit; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 0 20px 60px; }

        header.hero { text-align:center; padding: 12px 12px 16px; background:#111; border-radius:0 0 18px 18px; margin-bottom: 22px; box-shadow:0 5px 18px #0002; overflow:hidden; }
        header.hero .brand { display:flex; justify-content:center; width:100%; margin:0 auto 7px; padding:0; }
        header.hero .brand img { display:block; width:min(220px, 64vw); max-width:100%; height:auto; max-height:58px; object-fit:contain; }
        header.hero h1 { color:#fff; font-size: 24px; margin: 0 0 7px; letter-spacing: -.03em; }
        header.hero p { color:#c9cbd0; font-size: 13.5px; max-width: 520px; margin: 0 auto 16px; }
        a.hero-btn { display:inline-block; color:#555960; text-decoration:none; padding: 8px 2px; font-weight:700; font-size:14px; }
        a.hero-btn:hover { color:#1a1a1a; text-decoration:underline; text-underline-offset:4px; }
        a.hero-btn.primary { background:var(--hh-accent); color:#21170e; padding:14px 30px; border-radius:10px; box-shadow:0 5px 12px #ff79183d; }
        a.hero-btn.primary:hover { background:var(--hh-accent-hover); text-decoration:none; }
        a.hero-btn.secondary::before { content:'◉'; color:#25b95b; margin-right:6px; font-size:11px; }

        /* Mismo lenguaje visual que las tarjetas del tablero interno (rooms/board.blade.php):
           superficie oscura, borde izquierdo de color por categoría, elevación al hover. */
        .cat-card { background:var(--hh-surface); color:#f5f5f5; border:1px solid #454649; border-left-width:4px; border-radius: 12px; overflow:hidden; margin-bottom: 28px; box-shadow: 0 3px 10px #1011120b; transition: transform .13s ease, box-shadow .13s ease, border-color .13s ease; }
        .cat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.25); }
        @media (prefers-reduced-motion: reduce) { .cat-card { transition: none; } .cat-card:hover { transform: none; } }
        .cat-card.cat-go { border-left-color:#5b9dd9; }
        .cat-card.cat-go:hover { box-shadow: 0 6px 20px rgba(91,157,217,.3); }
        .cat-card.cat-lite { border-left-color:#4ecdc4; }
        .cat-card.cat-lite:hover { box-shadow: 0 6px 20px rgba(78,205,196,.3); }
        .cat-card.cat-plus { border-left-color:#b088e8; }
        .cat-card.cat-plus:hover { box-shadow: 0 6px 20px rgba(176,136,232,.3); }
        .cat-card.cat-max { border-left-color:#f2994a; }
        .cat-card.cat-max:hover { box-shadow: 0 6px 20px rgba(242,153,74,.3); }
        .cat-card.cat-new-lite { border-left-width:3px; box-shadow: 0 0 0 1px rgba(232,199,111,.3) inset; }
        .cat-card.cat-new-lite:hover { box-shadow: 0 6px 20px rgba(232,199,111,.35), 0 0 0 1px rgba(232,199,111,.3) inset; }
        .cat-go h2 { color:#7fbfe8; }
        .cat-lite h2 { color:#6fe4db; }
        .cat-plus h2 { color:#c9a6f5; }
        .cat-max h2 { color:#f7b06a; }
        .cat-new-lite h2 { color:#f0d98a; }
        .offer-badge { display:inline-flex; background:#ff7918; color:#21170e; border-radius:999px; padding:6px 11px; font-size:11px; font-weight:800; margin-bottom:10px; }
        .sales-tip { display:flex; gap:8px; align-items:flex-start; background:#242527; border:1px solid #505257; color:#f0d18a; border-radius:9px; padding:9px 11px; margin:10px 0 14px; font-size:12px; line-height:1.35; }
        .sales-tip::before { content:'✦'; color:var(--hh-accent); font-weight:800; }

        /* Carrusel único de "Ofertas y cupones" -- antes eran dos bloques
           apilados (ofertas en filas horizontales, cupones en grilla), acá
           se combinan en una sola fila deslizable (swipe en el celular,
           flechas en desktop) para que se sienta como un solo módulo. */
        .deals-carousel { margin: 0 0 26px; }
        .deals-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin:0 0 10px; }
        .deals-head h2 { font-size:16px; margin:0; color:#242527; }
        .limited-badge { display:inline-flex; align-items:center; gap:6px; background:#3a1c10; border:1px solid #ff7918; color:#ffb078; border-radius:20px; padding:4px 10px; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
        .limited-badge .dot { width:6px; height:6px; border-radius:50%; background:#ff7918; animation: hh-pulse 1.6s ease-in-out infinite; }
        @keyframes hh-pulse { 0%, 100% { opacity:1; } 50% { opacity:.25; } }

        .deals-track-wrap { position:relative; }
        .deals-track { display:flex; gap:12px; overflow-x:auto; scroll-snap-type:x mandatory; padding:2px 2px 12px; margin:0 -2px; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
        .deals-track::-webkit-scrollbar { display:none; }
        .deal-card { scroll-snap-align:start; flex:0 0 78%; max-width:290px; display:flex; flex-direction:column; border-radius:14px; overflow:hidden; text-decoration:none; color:inherit; -webkit-tap-highlight-color:transparent; }
        @media (min-width:600px) { .deal-card { flex-basis:300px; } }
        .deal-card.is-offer { background:#191a1c; border:1px solid #ff7918; }
        .deal-card.is-coupon { background:#111214; border:1px solid #3a5a72; }
        .deal-card.is-coupon:hover, .deal-card.is-coupon:active, .deal-card.is-coupon:focus-visible { border-color:#9edaff; }
        .deal-thumb { width:100%; aspect-ratio:16/10; object-fit:cover; background:#303136; display:block; }
        .deal-thumb-empty { width:100%; aspect-ratio:16/10; background:#242527; display:flex; align-items:center; justify-content:center; color:#8a8c90; font-size:12px; }
        .deal-body { padding:13px 15px 15px; display:flex; flex-direction:column; flex:1; }
        .deal-body strong.deal-title { display:block; font-size:15px; color:#fff; margin-bottom:3px; }
        .deal-sub-link { color:#c9cbd0; font-size:12px; text-decoration:none; display:inline-block; margin-top:2px; }
        .deal-sub-link:hover { text-decoration:underline; }
        .offer-price-row { display:flex; align-items:baseline; gap:7px; margin:2px 0 3px; flex-wrap:wrap; }
        .offer-price-old { color:#8a8d93; text-decoration:line-through; font-size:12.5px; }
        .offer-price-new { color:#6ee7b7; font-weight:800; font-size:16px; }
        .offer-save-badge { background:#123a28; color:#6ee7b7; border-radius:6px; padding:2px 6px; font-size:10.5px; font-weight:800; }
        .offer-viewers { display:block; color:#ffb078; font-size:11px; margin-top:3px; min-height:14px; }
        .deal-coupon-benefit { color:#9edaff; font-size:22px; font-weight:800; }
        .deal-coupon-hint { color:#8a8d93; font-size:11px; margin-top:4px; }
        .deal-cta-btn { display:block; text-align:center; margin-top:auto; padding-top:12px; }
        .deal-cta-btn span { display:block; background:#ff7918; color:#21170e; border-radius:8px; padding:10px 12px; font-size:12.5px; font-weight:800; }
        .deal-card.is-coupon .deal-cta-btn { color:#9edaff; font-size:12px; font-weight:700; padding-top:10px; }
        .deals-nav { display:none; align-items:center; justify-content:center; position:absolute; top:50%; transform:translateY(-50%); width:32px; height:32px; border-radius:50%; background:#fff; border:1px solid #d9dadd; box-shadow:0 2px 8px #0002; cursor:pointer; font-size:14px; z-index:2; padding:0; }
        .deals-nav.prev { left:-8px; }
        .deals-nav.next { right:-8px; }
        @media (hover:hover) and (min-width:760px) { .deals-nav { display:flex; } }

        .cat-gallery { display:grid; gap:3px; background:#202122; padding:3px; }
        .cat-gallery.gallery-4 { grid-template-columns:2fr 1fr 1fr; grid-template-rows:repeat(2, minmax(92px, 1fr)); aspect-ratio:16/7; }
        .cat-gallery.gallery-3 { grid-template-columns:2fr 1fr; grid-template-rows:repeat(2, minmax(92px, 1fr)); aspect-ratio:16/7; }
        .cat-gallery.gallery-2 { grid-template-columns:repeat(2, 1fr); grid-template-rows:minmax(150px, 1fr); }
        .cat-gallery.gallery-1 { grid-template-columns:1fr; grid-template-rows:minmax(220px, 1fr); }
        .cat-gallery a { display:block; aspect-ratio:auto; min-height:92px; overflow:hidden; position:relative; }
        .cat-gallery a:first-child { grid-row:1 / span 2; min-height:190px; }
        .cat-gallery.gallery-4 a:nth-child(4) { grid-column:2 / span 2; }
        .cat-gallery.gallery-3 a:nth-child(3) { grid-column:2; }
        .cat-gallery a::after { content:'Ver foto'; position:absolute; right:8px; bottom:8px; background:#111c; color:#fff; padding:4px 8px; border-radius:12px; font-size:10px; opacity:0; transition:opacity .2s ease; }
        .cat-gallery a:hover::after, .cat-gallery a:focus-visible::after { opacity:1; }
        .cat-gallery img { width:100%; height:100%; object-fit:cover; display:block; transition: transform .2s ease; }
        .cat-gallery a:hover img { transform: scale(1.05); }
        .photo-modal { display:none; position:fixed; inset:0; z-index:20; background:rgba(10,10,10,.92); align-items:center; justify-content:center; padding:24px; }
        .photo-modal.is-open { display:flex; }
        .photo-modal img { max-width:min(920px, 96vw); max-height:86vh; object-fit:contain; border-radius:10px; box-shadow:0 12px 40px #000; }
        .photo-close { position:fixed; top:18px; right:18px; border:1px solid #ffffff66; background:#222; color:#fff; border-radius:24px; padding:10px 16px; font-weight:700; cursor:pointer; z-index:21; }
        .video-modal { display:none; position:fixed; inset:0; z-index:20; background:rgba(10,10,10,.92); align-items:center; justify-content:center; padding:24px; }
        .video-modal.is-open { display:flex; }
        .video-modal video { max-width:min(920px, 96vw); max-height:86vh; border-radius:10px; box-shadow:0 12px 40px #000; }
        .cat-gallery.empty { aspect-ratio: 16/5; display:flex; align-items:center; justify-content:center; color:#8a8c90; font-size:13px; grid-template-columns:none; }

        .cat-body { padding: 22px 24px 26px; }
        .cat-body h2 { font-size: 20px; margin: 0 0 4px; }
        .cat-cap { font-size: 12.5px; color: #9edaff; font-weight:600; margin-bottom: 12px; }
        .cat-desc { font-size: 14px; color: #d5d8de; margin-bottom: 14px; }
        .cat-features { display:flex; flex-wrap:wrap; gap: 7px; margin-bottom: 18px; }
        .cat-features span { display:inline-block; font-family: ui-monospace, monospace; background:#262729; border:1px solid #56585d; color:#e0e2e5; font-size: 11px; font-weight:600; padding: 3px 11px; border-radius: 20px; }

        table.price-table { width:100%; border-collapse:collapse; margin-bottom: 20px; font-size: 13.5px; }
        table.price-table th { text-align:left; color:#bbbfc6; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.03em; padding: 0 10px 8px 0; }
        table.price-table td { padding: 8px 10px 8px 0; border-top: 1px solid #505154; }
        table.price-table td.price { font-family: ui-monospace, monospace; font-weight:700; color:#fff; }
        table.price-table td.dash { color:#8a8c90; }

        .cat-btn-row { display:flex; gap:10px; }
        a.cat-btn { flex:1; display:block; text-align:center; background:#262729; border:1px solid #666; color:#eee; text-decoration:none; padding: 13px; border-radius: 9px; font-weight:700; font-size:13.5px; }
        a.cat-btn:hover { border-color:#25d366; }
        a.cat-btn.primary { background:var(--hh-accent); border-color:var(--hh-accent); color:#21170e; }
        a.cat-btn.primary:hover { background:var(--hh-accent-hover); }
        @media (max-width: 420px) { .cat-btn-row { flex-direction:column; } }

        .video-links { display:flex; flex-wrap:wrap; gap:10px; margin: -6px 0 18px; }
        .video-links a { font-size: 12.5px; color:#9edaff; text-decoration:none; border:1px solid #3a5a72; padding:5px 11px; border-radius:20px; }
        .video-links a:hover { border-color:#9edaff; }

        footer.catalog-footer { text-align:center; color:#8a8d93; font-size:12.5px; padding: 20px 0 10px; }

        @media (max-width: 560px) {
            .cat-gallery.gallery-4, .cat-gallery.gallery-3 { grid-template-rows:repeat(2, minmax(84px, 1fr)); aspect-ratio:4/3; }
            .cat-gallery.gallery-2 { grid-template-rows:minmax(120px, 1fr); }
            .cat-gallery.gallery-1 { grid-template-rows:minmax(180px, 1fr); }
            .cat-gallery a:first-child { min-height:172px; }
            .cat-gallery a { min-height:84px; }
            header.hero { padding: 16px 12px 18px; }
            header.hero h1 { font-size: 21px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="hero">
            <div class="brand"><img src="https://assets.cdn.filesafe.space/ksYYfSiY8nP4YFvkrJFJ/media/6aab44cf9f8b31b6ab530587.png" alt="HH Motel Santiago Playrooms"></div>
            <h1>Nuestras Playrooms</h1>
            <p>Elige la que más te guste — reserva online o escríbenos por WhatsApp.</p>
        </header>

        @php
            $offerPriceRow = function ($offer) {
                if ($offer['originalPrice'] && $offer['offerPrice']) {
                    $savePct = round((1 - $offer['offerPrice'] / $offer['originalPrice']) * 100);
                    return '<div class="offer-price-row">'
                        .'<span class="offer-price-old">$'.number_format($offer['originalPrice'], 0, ',', '.').'</span>'
                        .'<span class="offer-price-new">$'.number_format($offer['offerPrice'], 0, ',', '.').'</span>'
                        .($savePct > 0 ? '<span class="offer-save-badge">-'.$savePct.'%</span>' : '')
                        .'</div>';
                }
                return '<div class="offer-price-row"><span class="offer-price-new">'.e($offer['label']).'</span></div>';
            };
        @endphp
        @php $hasOffers = $categories->contains(fn ($entry) => $entry['offer']); @endphp
        @if ($hasOffers || $coupons->isNotEmpty())
            <section class="deals-carousel">
                <div class="deals-head">
                    <h2>✦ Ofertas y cupones</h2>
                    @if ($hasOffers)
                        <span class="limited-badge"><span class="dot"></span> Por tiempo limitado</span>
                    @endif
                </div>
                <div class="deals-track-wrap">
                    <button type="button" class="deals-nav prev" aria-label="Ver anterior">‹</button>
                    <div class="deals-track" id="deals-track">
                        @foreach ($categories->filter(fn ($entry) => $entry['offer']) as $entry)
                            @if (count($entry['offer']['rooms']))
                                @foreach ($entry['offer']['rooms'] as $offerRoom)
                                    <div class="deal-card is-offer">
                                        <a href="{{ route('catalog.room', $offerRoom['id']) }}">
                                            @if ($offerRoom['photo'])
                                                <img class="deal-thumb" src="{{ $offerRoom['photo'] }}" alt="{{ $offerRoom['name'] }}">
                                            @else
                                                <div class="deal-thumb-empty">Foto próximamente</div>
                                            @endif
                                        </a>
                                        <div class="deal-body">
                                            <strong class="deal-title">{{ $offerRoom['name'] }}{{ $entry['offer']['durationLabel'] ? ' · '.$entry['offer']['durationLabel'] : '' }}</strong>
                                            {!! $offerPriceRow($entry['offer']) !!}
                                            <a class="deal-sub-link" href="{{ route('catalog.room', $offerRoom['id']) }}">{{ $entry['category']->name }} · Ver ficha y fotos →</a>
                                            <span class="offer-viewers" data-hh-viewers></span>
                                            <a class="deal-cta-btn" href="{{ route('catalog.reserve', ['categoria' => $entry['category']->id, 'room_id' => $offerRoom['id']]) }}"><span>Reservar oferta →</span></a>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="deal-card is-offer">
                                    @if ($entry['photos']->isNotEmpty())
                                        <img class="deal-thumb" src="{{ $entry['photos']->first() }}" alt="{{ $entry['category']->name }}">
                                    @else
                                        <div class="deal-thumb-empty">{{ $entry['category']->name }}</div>
                                    @endif
                                    <div class="deal-body">
                                        <strong class="deal-title">{{ $entry['category']->name }}{{ $entry['offer']['durationLabel'] ? ' · '.$entry['offer']['durationLabel'] : '' }}</strong>
                                        {!! $offerPriceRow($entry['offer']) !!}
                                        <span class="offer-viewers" data-hh-viewers></span>
                                        <a class="deal-cta-btn" href="{{ route('catalog.reserve', ['categoria' => $entry['category']->id]) }}"><span>Reservar oferta →</span></a>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @foreach ($coupons as $coupon)
                            <a class="deal-card is-coupon" href="{{ route('catalog.reserve', ['cupon' => $coupon['code']]) }}">
                                @if ($coupon['image'])
                                    <img class="deal-thumb" src="{{ $coupon['image'] }}" alt="{{ $coupon['name'] }}">
                                @endif
                                <div class="deal-body">
                                    <div class="deal-coupon-benefit">{{ $coupon['benefit'] }}</div>
                                    <strong class="deal-title">{{ $coupon['name'] }}</strong>
                                    @if ($coupon['hint'])<div class="deal-coupon-hint">{{ $coupon['hint'] }}</div>@endif
                                    <span class="deal-cta-btn">Usar este cupón →</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <button type="button" class="deals-nav next" aria-label="Ver siguiente">›</button>
                </div>
            </section>
        @endif

        @forelse ($categories as $entry)
            @php [$category, $photos, $videos, $prices, $catWhatsapp] = [$entry['category'], $entry['photos'], $entry['videos'], $entry['prices'], $entry['whatsappUrl']]; @endphp
            <section class="cat-card cat-{{ Str::slug($category->name) }}">
                @if ($photos->isNotEmpty())
                    <div class="cat-gallery gallery-{{ min($photos->count(), 4) }}">
                        @foreach ($photos->take(4) as $url)
                            <a href="{{ $url }}" class="photo-trigger" data-photo="{{ $url }}" aria-label="Ver foto {{ $category->name }}"><img src="{{ $url }}" alt="Foto {{ $category->name }}" loading="lazy"></a>
                        @endforeach
                    </div>
                @else
                    <div class="cat-gallery empty">Fotos próximamente</div>
                @endif

                <div class="cat-body">
                    <h2>{{ $category->name }}</h2>
                    <div class="cat-cap">Hasta {{ $category->max_capacity }} personas{{ $category->extra_guest_from ? ' · desde la '.$category->extra_guest_from.'ª persona, cargo adicional' : '' }}</div>
                    <div class="sales-tip">{{ $entry['salesTip'] }}</div>

                    @if ($category->description)
                        <div class="cat-desc">{{ $category->description }}</div>
                    @endif

                    @if (!empty($category->features))
                        <div class="cat-features">
                            @foreach ($category->features as $feature)
                                <span>{{ $feature }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($videos->isNotEmpty())
                        <div class="video-links">
                            @foreach ($videos as $url)
                                <a href="{{ $url }}" class="video-trigger" data-video="{{ $url }}" target="_blank" rel="noopener">Ver video {{ $loop->index + 1 }} →</a>
                            @endforeach
                        </div>
                    @endif

                    @if ($prices->isNotEmpty())
                        <table class="price-table">
                            <thead>
                                <tr>
                                    <th>Duración</th>
                                    <th>Lun a jue</th>
                                    <th>Vie a dom</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($prices as $row)
                                    <tr>
                                        <td>{{ $row['duration'] >= 60 ? intdiv($row['duration'], 60).' h' : $row['duration'].' min' }}</td>
                                        <td class="{{ $row['hh'] ? 'price' : 'dash' }}">{{ $row['hh'] ? '$'.number_format($row['hh'], 0, ',', '.') : '—' }}</td>
                                        <td class="{{ $row['hot'] ? 'price' : 'dash' }}">{{ $row['hot'] ? '$'.number_format($row['hot'], 0, ',', '.') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <div class="cat-btn-row">
                        <a class="cat-btn primary" href="{{ route('catalog.reserve', ['categoria' => $category->id]) }}">Reservar online →</a>
                    </div>
                </div>
            </section>
        @empty
            <p style="text-align:center; color:#777;">Todavía no hay categorías activas para mostrar.</p>
        @endforelse

        <footer class="catalog-footer">
            <p>¿Tienes dudas sobre cuál elegir?</p>
            <a class="hero-btn secondary" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Escribir por WhatsApp</a>
            <div style="margin-top:18px">HH Motel</div>
        </footer>
    </div>
    <div class="photo-modal" id="photo-modal" role="dialog" aria-modal="true" aria-label="Vista ampliada de la foto">
        <button class="photo-close" type="button" id="photo-close">Cerrar ✕</button>
        <img id="photo-modal-image" src="" alt="Foto ampliada">
    </div>
    <div class="video-modal" id="video-modal" role="dialog" aria-modal="true" aria-label="Reproductor de video">
        <button class="photo-close" type="button" id="video-close">Cerrar ✕</button>
        <video id="video-modal-player" controls playsinline></video>
    </div>
    <script>
        const photoModal = document.getElementById('photo-modal');
        const photoModalImage = document.getElementById('photo-modal-image');
        function closePhoto() { photoModal.classList.remove('is-open'); photoModalImage.src = ''; }
        document.querySelectorAll('.photo-trigger').forEach((link) => link.addEventListener('click', (event) => {
            event.preventDefault();
            photoModalImage.src = link.dataset.photo;
            photoModal.classList.add('is-open');
        }));
        document.getElementById('photo-close').addEventListener('click', closePhoto);
        photoModal.addEventListener('click', (event) => { if (event.target === photoModal) closePhoto(); });

        // Antes el video abría en pestaña nueva (target="_blank") sin ningún
        // botón para volver al catálogo -- ahora se reproduce en un modal
        // con su propio "Cerrar", igual que las fotos.
        const videoModal = document.getElementById('video-modal');
        const videoModalPlayer = document.getElementById('video-modal-player');
        function closeVideo() { videoModal.classList.remove('is-open'); videoModalPlayer.pause(); videoModalPlayer.removeAttribute('src'); videoModalPlayer.load(); }
        document.querySelectorAll('.video-trigger').forEach((link) => link.addEventListener('click', (event) => {
            event.preventDefault();
            videoModalPlayer.src = link.dataset.video;
            videoModal.classList.add('is-open');
            videoModalPlayer.play().catch(() => {});
        }));
        document.getElementById('video-close').addEventListener('click', closeVideo);
        videoModal.addEventListener('click', (event) => { if (event.target === videoModal) closeVideo(); });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            closePhoto();
            closeVideo();
        });

        const dealsTrack = document.getElementById('deals-track');
        if (dealsTrack) {
            const scrollByCard = (dir) => {
                const card = dealsTrack.querySelector('.deal-card');
                const step = (card ? card.getBoundingClientRect().width : 280) + 12;
                dealsTrack.scrollBy({ left: dir * step, behavior: 'smooth' });
            };
            document.querySelector('.deals-nav.prev')?.addEventListener('click', () => scrollByCard(-1));
            document.querySelector('.deals-nav.next')?.addEventListener('click', () => scrollByCard(1));
        }

        // "Viendo esto ahora" en las ofertas -- la mayoría de las veces no
        // muestra nada; cuando aparece es 1 o 2 personas, y cada tarjeta
        // cambia sola en su propio momento (no todas juntas), para que se
        // sienta real y no un contador fijo siempre prendido.
        document.querySelectorAll('[data-hh-viewers]').forEach((el) => {
            function roll() {
                const r = Math.random();
                el.textContent = r < 0.68 ? '' : (r < 0.9 ? '👀 1 persona viendo esto ahora' : '👀 2 personas viendo esto ahora');
                setTimeout(roll, 18000 + Math.random() * 22000);
            }
            setTimeout(roll, Math.random() * 6000);
        });
    </script>
</body>
</html>
