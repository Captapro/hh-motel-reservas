{{--
    Catálogo público: portada con foto a pantalla completa, ofertas y cupones,
    y una tarjeta por categoría a tope en los extremos (sin márgenes laterales),
    con galería de fotos y videos en la misma página y tarifas desplegables.
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Playrooms — HH Motel</title>
    @include('partials.social-meta')
    @include('catalog._app-head')
    <style>
        :root {
            color-scheme: light;
            --hh-accent: #ff7918;
            --hh-accent-hover: #ee6909;
            --hh-ink: #1b1c1e;
            --hh-bg: #f6f4f0;
            --hh-bg-2: #ffffff;
            --hh-line: #e4e0d8;
            --hh-soft: #6c6f75;
            --hh-warm: #a8713a;
            --display: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --ui: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        /* Sin margen ni relleno heredado del navegador: el marco blanco que se
           veía al costado era el margen que algunos navegadores le dan al body. */
        html, body { margin: 0; padding: 0; width: 100%; max-width: 100%; }
        html { background: var(--hh-bg); }
        body { background: var(--hh-bg); color: var(--hh-ink); font-family: var(--ui); line-height: 1.5; overflow-x: hidden; }
        a { color: inherit; }
        img { max-width: 100%; }
        /* Todo va a tope en los extremos: el respiro es interno, no un margen. */
        .pad { padding-left: 18px; padding-right: 18px; }
        @media (min-width: 900px) { .pad { padding-left: 40px; padding-right: 40px; } }

        /* ===== Portada (oscura porque es la foto, no un fondo negro) ===== */
        .hero { position: relative; min-height: 86svh; display: flex; align-items: flex-end; overflow: hidden; background: #2a2622; color: #fff; }
        .hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .hero-veil { position: absolute; inset: 0; background: linear-gradient(to top, rgba(14,11,9,.9) 4%, rgba(14,11,9,.42) 46%, rgba(14,11,9,.18) 100%); }
        .hero-in { position: relative; width: 100%; padding-top: 70px; padding-bottom: 30px; }
        .kicker { font-size: 12px; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; color: #ffbe8d; margin: 0 0 14px; }
        .hero h1 { font-weight: 800; font-size: clamp(42px, 13vw, 92px); line-height: .96; letter-spacing: -.035em; margin: 0 0 18px; }
        .hero h1 em { display: block; font-style: normal; font-weight: 800; color: #f0ded0; }
        .hero-from { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; margin: 0 0 20px; }
        .hero-from small { font-size: 12px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: #d9d5cf; }
        .hero-from b { font-size: 30px; font-weight: 700; letter-spacing: -.02em; }
        .hero-from span { color: #d9d5cf; font-size: 15px; }
        .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .hero .btn-ghost { border-color: #ffffff5c; color: #fff; }
        .hero-place { margin: 18px 0 0; font-size: 14.5px; color: #d9d5cf; text-decoration: none; display: inline-block; border-bottom: 1px solid #ffffff40; padding-bottom: 3px; }
        .scroll-hint { margin-top: 26px; font-size: 11.5px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: #bbb5ad; }
        @media (min-width: 900px) { .hero-actions { max-width: 540px; } .hero-in { padding-bottom: 52px; } }

        .btn-main { flex: 1 1 220px; text-align: center; background: var(--hh-accent); color: #21170e; text-decoration: none; font-weight: 800; font-size: 17px; padding: 17px 20px; border-radius: 14px; box-shadow: 0 6px 18px #ff791833; }
        .btn-main:active { background: var(--hh-accent-hover); transform: scale(.99); }
        .btn-ghost { flex: 0 1 auto; text-align: center; border: 1px solid #cfcbc2; color: var(--hh-ink); background: transparent; text-decoration: none; font-weight: 700; font-size: 16px; padding: 17px 22px; border-radius: 14px; }

        /* ===== Encabezado de sección numerada ===== */
        .sec-head { padding-top: 52px; padding-bottom: 6px; scroll-margin-top: calc(var(--bar-h) + var(--safe-t) + 56px); }
        section[id] { scroll-margin-top: calc(var(--bar-h) + var(--safe-t) + 56px); }
        .sec-num { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: #8a8d93; margin-bottom: 14px; }
        .sec-num b { color: var(--hh-accent); font-weight: 800; }
        .sec-num i { flex: 1; height: 1px; background: var(--hh-line); font-style: normal; }
        .sec-head h2 { font-weight: 800; font-size: clamp(32px, 8.5vw, 62px); line-height: 1; letter-spacing: -.02em; margin: 0; }
        .sec-head h2 em { font-style: normal; font-weight: 800; color: var(--hh-warm); }
        .sec-head p { color: var(--hh-soft); font-size: 16.5px; margin: 14px 0 0; max-width: 620px; }

        /* ===== Chips de categoría (se quedan fijos al bajar) ===== */
        .chips-wrap { position: sticky; top: calc(var(--bar-h) + var(--safe-t)); z-index: 40; background: rgba(246,244,240,.93); -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px); border-bottom: 1px solid var(--hh-line); padding: 9px 0; }
        .chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; padding: 0 18px; }
        .chips::-webkit-scrollbar { display: none; }
        .chip { flex: none; display: inline-flex; align-items: center; gap: 7px; min-height: 38px; padding: 0 15px; border-radius: 999px; background: #fff; border: 1px solid var(--hh-line); color: #3a3d42; font-size: 14.5px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .chip i { width: 8px; height: 8px; border-radius: 50%; background: var(--c, #9a9ca1); }
        .chip.on { background: var(--hh-ink); border-color: var(--hh-ink); color: #fff; }
        .chip.cat-go { --c: #3f86c4; } .chip.cat-lite { --c: #2bab9f; } .chip.cat-plus { --c: #8f63d0; }
        .chip.cat-max { --c: #e07c20; } .chip.cat-new-lite { --c: #c9a233; }

        /* ===== Ofertas y cupones (carrusel a tope) ===== */
        .deals { padding-top: 34px; }
        .deals-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .deals-head h3 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -.01em; }
        .limited { display: inline-flex; align-items: center; gap: 7px; background: #fff4ea; border: 1px solid #f3cfae; color: #a8541a; border-radius: 999px; padding: 5px 12px; font-size: 11.5px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .limited .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--hh-accent); animation: hh-pulse 1.6s ease-in-out infinite; }
        @keyframes hh-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
        .deals-track { display: flex; gap: 12px; overflow-x: auto; scroll-snap-type: x mandatory; padding: 2px 18px 16px; scroll-padding-left: 18px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        .deals-track::-webkit-scrollbar { display: none; }
        @media (hover: hover) { .deals-track { cursor: grab; } .deals-track.dragging { cursor: grabbing; } }
        /* Aviso de bienvenida (cupón de primera visita) con los colores del catálogo. */
        .hh-welcome-card { background: var(--hh-bg-2) !important; border-color: #e7d3bd !important; color: var(--hh-ink) !important; box-shadow: 0 -10px 40px rgba(30,25,20,.18) !important; }
        .hh-welcome-kicker { color: #a8541a !important; }
        .hh-welcome-text { color: var(--hh-soft) !important; }
        .hh-welcome-close { color: #a9a49d !important; }
        .hh-welcome-fine { color: #a9a49d !important; }
        .hh-welcome-btn { border-radius: 14px !important; }
        .deal { scroll-snap-align: start; flex: 0 0 78%; max-width: 320px; display: flex; flex-direction: column; text-decoration: none; color: inherit; background: var(--hh-bg-2); border: 1px solid var(--hh-line); border-radius: 16px; box-shadow: 0 2px 10px rgba(30,25,20,.05); }
        @media (min-width: 620px) { .deal { flex-basis: 320px; } }
        .deal.is-offer { border-color: #f0cfae; }
        .deal-band { padding: 10px 16px; font-size: 11.5px; font-weight: 800; letter-spacing: .14em; color: #a8541a; background: #fff6ee; border-bottom: 1px solid #f3e3d3; }
        .deal-band.coupon { color: #1c6b96; background: #f0f8fc; border-bottom-color: #dbeaf3; }
        .deal-body { padding: 16px; display: flex; flex-direction: column; flex: 1; gap: 4px; }
        .deal-title { font-size: 18px; font-weight: 800; }
        .price-row { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; margin: 4px 0 2px; }
        .price-old { color: #8a8d93; text-decoration: line-through; font-size: 15px; }
        .price-new { color: var(--hh-ink); font-weight: 800; font-size: 22px; }
        .save-badge { background: #ffe5d0; color: #a8541a; border-radius: 6px; padding: 2px 7px; font-size: 12px; font-weight: 800; }
        .deal-link { color: var(--hh-soft); font-size: 14.5px; text-decoration: none; border-bottom: 1px solid #d8d4cc; align-self: flex-start; }
        .deal-benefit { color: #1c6b96; font-size: 30px; font-weight: 800; letter-spacing: -.02em; }
        .deal-hint { color: #8a8d93; font-size: 13.5px; }
        .deal-cta { margin-top: auto; padding-top: 14px; }
        .deal-cta span { display: block; text-align: center; background: var(--hh-accent); color: #21170e; padding: 13px; font-weight: 800; font-size: 15.5px; border-radius: 14px; }
        .deal.is-coupon .deal-cta span { background: transparent; border: 1px solid #bcd9ea; color: #1c6b96; }

        /* ===== Tarjetas de Playroom, a tope en los extremos ===== */
        .rooms { margin-top: 24px; }
        .room { scroll-margin-top: calc(var(--bar-h) + var(--safe-t) + 56px); }
        .room-media { position: relative; background: #e8e4dc; }
        .slides { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
        .slides::-webkit-scrollbar { display: none; }
        /* Con mouse la foto se arrastra; en celular se desliza con el dedo. */
        @media (hover: hover) { .slides { cursor: grab; } .slides.dragging { cursor: grabbing; } }
        .slide { flex: 0 0 100%; scroll-snap-align: center; display: block; aspect-ratio: 4 / 5; overflow: hidden; }
        .slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .slide video { width: 100%; height: 100%; object-fit: contain; background: #16130f; display: block; }
        @media (min-width: 620px) { .slide { aspect-ratio: 16 / 9; } }
        /* Con un video a la vista se apaga el velo y el título, para no tapar los controles. */
        .room-media.show-video .media-veil, .room-media.show-video .media-cap { opacity: 0; pointer-events: none; transition: opacity .2s ease; }
        .media-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 3; width: 40px; height: 40px; border: 0; border-radius: 50%; background: rgba(255,255,255,.85); color: #1b1c1e; font-size: 22px; line-height: 1; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
        .media-nav.prev { left: 10px; } .media-nav.next { right: 10px; }
        @media (hover: none) { .media-nav { display: none; } }

        /* Tira de miniaturas: las fotos y los videos se revisan aquí mismo. */
        .thumbs { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -18px; padding: 0 18px 2px; }
        @media (min-width: 900px) { .thumbs { margin: 0 -40px; padding: 0 40px 2px; } }
        .thumbs::-webkit-scrollbar { display: none; }
        .thumb { flex: none; width: 62px; height: 62px; padding: 0; border: 2px solid transparent; background: #e8e4dc; cursor: pointer; overflow: hidden; position: relative; opacity: .55; transition: opacity .15s ease, border-color .15s ease; border-radius: 10px; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .thumb.on { opacity: 1; border-color: var(--hh-accent); }
        .thumb-vid { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: #1c6b96; font-size: 20px; }
        .media-empty { aspect-ratio: 4 / 5; display: flex; align-items: center; justify-content: center; color: #8a8d93; font-size: 15px; background: linear-gradient(160deg, #eeeae2, #e2ded6); }
        @media (min-width: 620px) { .media-empty { aspect-ratio: 16 / 9; } }
        .media-veil { position: absolute; inset: 0; background: linear-gradient(to top, rgba(14,11,9,.88) 2%, rgba(14,11,9,.22) 46%, rgba(14,11,9,0) 72%); pointer-events: none; }
        .media-cap { position: absolute; left: 18px; right: 18px; bottom: 20px; pointer-events: none; color: #fff; }
        @media (min-width: 900px) { .media-cap { left: 40px; right: 40px; } }
        .room-n { font-size: 12px; font-weight: 800; letter-spacing: .08em; color: #ffbe8d; margin-bottom: 6px; }
        .media-cap h3 { font-weight: 800; font-size: clamp(34px, 10vw, 58px); line-height: .98; letter-spacing: -.02em; margin: 0; }
        .media-cap .cap-sub { display: inline-flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 14.5px; font-weight: 600; color: #ece9e4; }
        .media-cap .cap-sub::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--c, #d6d2ca); }
        .room.cat-go { --c: #6fb0e8; } .room.cat-lite { --c: #4ecdc4; } .room.cat-plus { --c: #b088e8; }
        .room.cat-max { --c: #f2994a; } .room.cat-new-lite { --c: #e8c76f; }
        .badge-offer { position: absolute; top: 16px; left: 18px; background: var(--hh-accent); color: #21170e; font-size: 12px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; padding: 7px 12px; border-radius: 999px; }
        @media (min-width: 900px) { .badge-offer { left: 40px; } }
        .count { position: absolute; top: 16px; right: 18px; background: rgba(255,255,255,.85); color: #1b1c1e; font-size: 13px; font-weight: 700; padding: 5px 10px; border-radius: 999px; pointer-events: none; }
        @media (min-width: 900px) { .count { right: 40px; } }

        .room-body { background: var(--hh-bg-2); padding-top: 22px; padding-bottom: 30px; display: flex; flex-direction: column; gap: 16px; }
        .from { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
        .from small { font-size: 11.5px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: var(--hh-soft); }
        .from b { font-size: 32px; font-weight: 700; letter-spacing: -.03em; }
        .from em { font-style: normal; color: var(--hh-soft); font-size: 15px; }
        .room-desc { margin: 0; color: #4b4e54; font-size: 17px; max-width: 640px; }
        .feats { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -18px; padding: 0 18px; }
        @media (min-width: 900px) { .feats { margin: 0 -40px; padding: 0 40px; } }
        .feats::-webkit-scrollbar { display: none; }
        .feats span { flex: none; background: var(--hh-bg); border: 1px solid var(--hh-line); color: #3a3d42; font-size: 14px; font-weight: 600; padding: 7px 13px; border-radius: 999px; }
        .tip { display: flex; gap: 9px; align-items: flex-start; color: #8a5620; font-size: 16.5px; margin: -4px 0 0; }
        .tip::before { content: '✦'; color: var(--hh-accent); font-weight: 800; line-height: 1.5; }
        .extra-note { color: #8a8d93; font-size: 14.5px; margin: -6px 0 0; }
        .room-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 2px; }
        .room-actions .btn-main { flex: 1 1 240px; }
        .room-actions .btn-ghost { flex: 1 1 140px; }

        @media (min-width: 1000px) {
            .room { display: grid; grid-template-columns: 1.15fr 1fr; align-items: stretch; }
            .room-media { height: 100%; }
            .room-media .slides, .room-media .slide { height: 100%; }
            .room-media .slide { aspect-ratio: auto; min-height: 74vh; }
            .room-body { justify-content: center; padding: 48px 40px; }
            .room:nth-child(even) .room-media { order: 2; }
        }

        /* ===== Tarifas, dentro de cada Playroom y desplegables como en el catálogo actual ===== */
        details.rates { background: var(--hh-bg); border: 1px solid var(--hh-line); border-radius: 12px; }
        details.rates summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-height: 48px; padding: 0 14px; font-size: 16px; font-weight: 700; color: var(--hh-ink); }
        details.rates summary::-webkit-details-marker { display: none; }
        details.rates summary::after { content: '▾'; color: var(--hh-soft); transition: transform .18s ease; }
        details.rates[open] summary::after { transform: rotate(180deg); }
        table.rates-table { width: calc(100% - 28px); margin: 0 14px 12px; border-collapse: collapse; font-size: 15.5px; table-layout: fixed; }
        table.rates-table th:first-child, table.rates-table td:first-child { width: 32%; }
        table.rates-table th { text-align: left; color: #8a8d93; font-weight: 700; font-size: 11.5px; text-transform: uppercase; letter-spacing: .12em; padding: 0 10px 9px 0; }
        table.rates-table td { padding: 11px 10px 11px 0; border-top: 1px solid var(--hh-line); }
        table.rates-table td.price { font-weight: 700; color: var(--hh-ink); font-variant-numeric: tabular-nums; }
        table.rates-table td.dash { color: #b4b0a8; }

        /* ===== Ubicación y ayuda ===== */
        .place { padding-top: 52px; padding-bottom: 52px; border-top: 1px solid var(--hh-line); }
        .place h3 { font-weight: 800; font-size: clamp(30px, 8vw, 54px); font-weight: 700; line-height: 1; margin: 12px 0 14px; letter-spacing: -.02em; }
        .place p { color: var(--hh-soft); font-size: 16.5px; margin: 0 0 20px; max-width: 560px; }
        .help { background: var(--hh-bg-2); border-top: 1px solid var(--hh-line); padding-top: 52px; padding-bottom: 58px; text-align: center; }
        .help h3 { font-weight: 800; font-size: clamp(28px, 7.5vw, 48px); font-weight: 700; margin: 0 0 10px; line-height: 1.05; }
        .help h3 em { font-style: normal; font-weight: 800; color: var(--hh-warm); }
        .help p { color: var(--hh-soft); margin: 0 0 22px; font-size: 16.5px; }
        .help a { display: inline-block; background: var(--hh-ink); color: #fff; text-decoration: none; font-weight: 700; font-size: 16px; padding: 16px 28px; border-radius: 14px; }
        footer.cat-footer { border-top: 1px solid var(--hh-line); text-align: center; color: #8a8d93; font-size: 13.5px; padding: 22px 0 14px; letter-spacing: .06em; }

    </style>
</head>
@php
    $hasOffers = $categories->contains(fn ($entry) => $entry['offer']);
    $hasDeals = $hasOffers || $coupons->isNotEmpty();

    // Precio más bajo de todo el catálogo, para la portada.
    $lowest = null;
    foreach ($categories as $e) {
        foreach ($e['prices'] as $row) {
            foreach (['hh', 'hot'] as $k) {
                if ($row[$k] && ($lowest === null || $row[$k] < $lowest['price'])) {
                    $lowest = ['price' => $row[$k], 'duration' => $row['duration']];
                }
            }
        }
    }
    $durationLabel = fn ($m) => $m >= 60 ? (($m % 60 === 0) ? intdiv($m, 60).' h' : round($m / 60, 1).' h') : $m.' min';
    // Foto de portada: la de la categoría más cara que tenga fotos.
    $heroPhoto = $categories->reverse()->flatMap(fn ($e) => $e['photos'])->first()
        ?: $categories->flatMap(fn ($e) => $e['photos'])->first();
    $withPhotos = $categories->filter(fn ($e) => $e['photos']->isNotEmpty())->count();
    // En numero y no en palabra: el titulo cabe en menos lineas.
    $countWord = $categories->count();
    // Horario de atención, tal como está cargado en las reglas de tarifa
    // (rate_rule_windows): HH lunes a jueves 10:30-03:00 y HOT desde el
    // viernes 10:30 corrido hasta el domingo 22:30.
    $horario = 'Lunes a jueves de 10:30 a 03:00. Viernes desde las 10:30, sin parar, hasta el domingo a las 22:30.';
@endphp
<body>
    @include('catalog._app-bar', ['appHasOffers' => $hasDeals])

    {{-- ===== Portada ===== --}}
    <section class="hero">
        @if ($heroPhoto)
            <img class="hero-img" src="{{ \App\Support\Img::url($heroPhoto, 1280) }}" alt="Playroom HH Motel" fetchpriority="high" decoding="async">
        @endif
        <div class="hero-veil"></div>
        <div class="hero-in pad">
            <p class="kicker">HH Motel · Santiago Centro</p>
            <h1>Tu momento,<em>sin apuro.</em></h1>
            @if ($lowest)
                <div class="hero-from">
                    <small>Desde</small>
                    <b>${{ number_format($lowest['price'], 0, ',', '.') }}</b>
                    <span>· {{ $durationLabel($lowest['duration']) }}</span>
                </div>
            @endif
            <div class="hero-actions">
                <a class="btn-main" href="{{ route('catalog.reserve') }}">Reservar ahora</a>
                <a class="btn-ghost" href="#playrooms">Ver Playrooms</a>
            </div>
            <a class="hero-place" href="https://www.google.com/maps/search/?api=1&query=Almirante+Barroso+334+Santiago" target="_blank" rel="noopener">Almirante Barroso 334 · Metro Santa Ana</a>
            <p class="scroll-hint">Baja para entrar ↓</p>
        </div>
    </section>

    {{-- ===== Chips de categoría ===== --}}
    @if ($categories->count() > 1)
        <div class="chips-wrap">
            <nav class="chips" id="cat-chips" aria-label="Categorías">
                <a class="chip on" href="#playrooms" data-target="playrooms">Todas</a>
                @foreach ($categories as $entry)
                    <a class="chip cat-{{ Str::slug($entry['category']->name) }}" href="#cat-{{ Str::slug($entry['category']->name) }}" data-target="cat-{{ Str::slug($entry['category']->name) }}"><i></i>{{ $entry['category']->name }}</a>
                @endforeach
            </nav>
        </div>
    @endif

    {{-- ===== Ofertas y cupones ===== --}}
    @php
        $offerPriceRow = function ($offer) {
            if ($offer['originalPrice'] && $offer['offerPrice']) {
                $savePct = round((1 - $offer['offerPrice'] / $offer['originalPrice']) * 100);
                return '<div class="price-row">'
                    .'<span class="price-old">$'.number_format($offer['originalPrice'], 0, ',', '.').'</span>'
                    .'<span class="price-new">$'.number_format($offer['offerPrice'], 0, ',', '.').'</span>'
                    .($savePct > 0 ? '<span class="save-badge">-'.$savePct.'%</span>' : '')
                    .'</div>';
            }
            return '<div class="price-row"><span class="price-new">'.e($offer['label']).'</span></div>';
        };
    @endphp
    @if ($hasDeals)
        <section class="deals" id="ofertas">
            <div class="deals-head pad">
                <h3>Ofertas y cupones</h3>
                @if ($hasOffers)<span class="limited"><span class="dot"></span> Por tiempo limitado</span>@endif
            </div>
            <div class="deals-track" id="deals-track">
                @foreach ($categories->filter(fn ($entry) => $entry['offer']) as $entry)
                    @if (count($entry['offer']['rooms']))
                        @foreach ($entry['offer']['rooms'] as $offerRoom)
                            <div class="deal is-offer">
                                <div class="deal-band">✦ OFERTA · {{ $entry['category']->name }}</div>
                                <div class="deal-body">
                                    <span class="deal-title">{{ $offerRoom['name'] }}{{ $entry['offer']['durationLabel'] ? ' · '.$entry['offer']['durationLabel'] : '' }}</span>
                                    {!! $offerPriceRow($entry['offer']) !!}
                                    <a class="deal-link" href="{{ route('catalog.room', $offerRoom['id']) }}">Ver ficha y fotos →</a>
                                    <a class="deal-cta" href="{{ route('catalog.reserve', ['categoria' => $entry['category']->id, 'room_id' => $offerRoom['id']]) }}"><span>Reservar oferta</span></a>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="deal is-offer">
                            <div class="deal-band">✦ OFERTA · {{ $entry['category']->name }}</div>
                            <div class="deal-body">
                                <span class="deal-title">{{ $entry['category']->name }}{{ $entry['offer']['durationLabel'] ? ' · '.$entry['offer']['durationLabel'] : '' }}</span>
                                {!! $offerPriceRow($entry['offer']) !!}
                                <a class="deal-cta" href="{{ route('catalog.reserve', ['categoria' => $entry['category']->id]) }}"><span>Reservar oferta</span></a>
                            </div>
                        </div>
                    @endif
                @endforeach
                @foreach ($coupons as $coupon)
                    <a class="deal is-coupon" href="{{ route('catalog.reserve', ['cupon' => $coupon['code']]) }}">
                        <div class="deal-band coupon">🎟 CUPÓN</div>
                        <div class="deal-body">
                            <div class="deal-benefit">{{ $coupon['benefit'] }}</div>
                            <span class="deal-title" style="font-size:18px;">{{ $coupon['name'] }}</span>
                            @if ($coupon['hint'])<div class="deal-hint">{{ $coupon['hint'] }}</div>@endif
                            <span class="deal-cta"><span>Usar este cupón</span></span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===== 01 / Playrooms ===== --}}
    <section id="playrooms">
        <div class="sec-head pad">
            <div class="sec-num"><b>01</b> <span>Playrooms</span> <i></i></div>
            <h2>{{ $countWord }} formas <em>de vivir HH.</em></h2>
            <p>Cada Playroom tiene su propio ambiente. Elige el tuyo, el horario y la duración: la reserva se confirma en línea.</p>
        </div>

        <div class="rooms">
        @forelse ($categories as $entry)
            @php
                [$category, $photos, $videos, $prices] = [$entry['category'], $entry['photos'], $entry['videos'], $entry['prices']];
                $slug = Str::slug($category->name);
                // Fotos y videos van en el mismo carrusel, y se revisan en la
                // misma página: nada abre otra ventana.
                $media = $photos->take(8)->map(fn ($u) => ['type' => 'photo', 'url' => $u])
                    ->concat($videos->map(fn ($u) => ['type' => 'video', 'url' => $u]))
                    ->values();
                $from = null;
                foreach ($prices as $row) {
                    foreach (['hh', 'hot'] as $k) {
                        if ($row[$k] && ($from === null || $row[$k] < $from['price'])) {
                            $from = ['price' => $row[$k], 'duration' => $row['duration']];
                        }
                    }
                }
            @endphp
            <section class="room cat-{{ $slug }}" id="cat-{{ $slug }}" data-cat>
                <div class="room-media">
                    @if ($media->isNotEmpty())
                        <div class="slides" data-slides>
                            @foreach ($media as $item)
                                @if ($item['type'] === 'photo')
                                    <div class="slide"><img src="{{ \App\Support\Img::url($item['url'], 960) }}" alt="Foto {{ $category->name }}" width="960" height="1200" loading="lazy" decoding="async"></div>
                                @else
                                    <div class="slide is-video"><video src="{{ $item['url'] }}" controls playsinline preload="metadata"></video></div>
                                @endif
                            @endforeach
                        </div>
                        @if ($media->count() > 1)
                            <button class="media-nav prev" type="button" data-nav="-1" aria-label="Anterior">‹</button>
                            <button class="media-nav next" type="button" data-nav="1" aria-label="Siguiente">›</button>
                            <span class="count" data-count>1/{{ $media->count() }}</span>
                        @endif
                    @else
                        <div class="media-empty">Fotos próximamente</div>
                    @endif
                    @if ($entry['offer'])<span class="badge-offer">✦ Oferta</span>@endif
                    <div class="media-veil"></div>
                    <div class="media-cap">
                        <div class="room-n">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                        <h3>{{ $category->name }}</h3>
                        <span class="cap-sub">Hasta {{ $category->max_capacity }} personas</span>
                    </div>
                </div>

                <div class="room-body pad">
                    @if ($media->count() > 1)
                        <div class="thumbs" data-thumbs>
                            @foreach ($media as $item)
                                <button type="button" class="thumb {{ $loop->first ? 'on' : '' }}" data-i="{{ $loop->index }}" aria-label="Ver {{ $item['type'] === 'video' ? 'video' : 'foto' }} {{ $loop->iteration }} de {{ $category->name }}">
                                    @if ($item['type'] === 'photo')
                                        <img src="{{ \App\Support\Img::url($item['url'], 160) }}" alt="" loading="lazy" decoding="async">
                                    @else
                                        <span class="thumb-vid">▶</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($from)
                        <div class="from"><small>Desde</small><b>${{ number_format($from['price'], 0, ',', '.') }}</b><em>· {{ $durationLabel($from['duration']) }}</em></div>
                    @endif

                    @if ($category->description)
                        <p class="room-desc">{{ $category->description }}</p>
                    @endif

                    @if (!empty($category->features))
                        <div class="feats">
                            @foreach ($category->features as $feature)
                                <span>{{ $feature }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="tip">{{ $entry['salesTip'] }}</div>

                    @if ($category->extra_guest_from)
                        <p class="extra-note">Desde la {{ $category->extra_guest_from }}ª persona se cobra un adicional.</p>
                    @endif

                    @if ($prices->isNotEmpty())
                        <details class="rates">
                            <summary>Ver todas las tarifas</summary>
                            <table class="rates-table">
                            <thead>
                                <tr><th>Duración</th><th>Lun–jue</th><th>Vie–dom</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($prices as $row)
                                    <tr>
                                        <td>{{ $durationLabel($row['duration']) }}</td>
                                        <td class="{{ $row['hh'] ? 'price' : 'dash' }}">{{ $row['hh'] ? '$'.number_format($row['hh'], 0, ',', '.') : '—' }}</td>
                                        <td class="{{ $row['hot'] ? 'price' : 'dash' }}">{{ $row['hot'] ? '$'.number_format($row['hot'], 0, ',', '.') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            </table>
                        </details>
                    @endif

                    <div class="room-actions">
                        <a class="btn-main" href="{{ route('catalog.reserve', ['categoria' => $category->id]) }}">Reservar {{ $category->name }}</a>
                        <a class="btn-ghost" href="{{ $entry['whatsappUrl'] }}" target="_blank" rel="noopener">Preguntar</a>
                    </div>
                </div>
            </section>
        @empty
            <p class="pad" style="color:#8e9197;">Todavía no hay categorías activas para mostrar.</p>
        @endforelse
        </div>
    </section>

    {{-- ===== 02 / Ubicación ===== --}}
    <section class="place pad" id="ubicacion">
        <div class="sec-num"><b>02</b> <span>Santiago</span> <i></i></div>
        <h3>Almirante<br>Barroso 334.</h3>
        <p>Santiago Centro, a pasos del Metro Santa Ana. Entrada discreta.<br>{{ $horario }}</p>
        <a class="btn-ghost" href="https://www.google.com/maps/search/?api=1&query=Almirante+Barroso+334+Santiago" target="_blank" rel="noopener">Cómo llegar →</a>
    </section>

    {{-- ===== 03 / Dudas ===== --}}
    <section class="help pad">
        <div class="sec-num" style="justify-content:center;"><b>03</b> <span>Dudas</span></div>
        <h3>¿Qué Playroom <em>quieres hoy?</em></h3>
        <p>Te ayudamos a elegir la mejor opción.</p>
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Escribir por WhatsApp</a>
    </section>
    <footer class="cat-footer">HH MOTEL SANTIAGO</footer>


    <script>
        // Galería de cada Playroom: fotos y videos se revisan acá mismo, sin
        // salir de la página ni abrir otra ventana. Las miniaturas, las
        // flechas y el deslizamiento mueven el mismo carrusel.
        document.querySelectorAll('.room').forEach((room) => {
            const track = room.querySelector('[data-slides]');
            if (!track) return;
            const media = room.querySelector('.room-media');
            const slides = Array.from(track.children);
            const thumbs = Array.from(room.querySelectorAll('[data-thumbs] .thumb'));
            const count = room.querySelector('[data-count]');
            const index = () => Math.round(track.scrollLeft / track.clientWidth);
            const goTo = (i) => {
                i = Math.max(0, Math.min(slides.length - 1, i));
                // Saltar varias fotos de golpe es instantáneo; de a una, suave.
                track.scrollTo({ left: i * track.clientWidth, behavior: Math.abs(i - index()) > 1 ? 'auto' : 'smooth' });
            };
            const sync = (scrollThumbs) => {
                const i = index();
                thumbs.forEach((t, n) => t.classList.toggle('on', n === i));
                if (count) count.textContent = (i + 1) + '/' + slides.length;
                const onVideo = !!slides[i] && slides[i].classList.contains('is-video');
                media.classList.toggle('show-video', onVideo);
                // Un video que se sale de la vista se pausa solo.
                track.querySelectorAll('video').forEach((v) => { if (v.closest('.slide') !== slides[i]) v.pause(); });
                // Solo al navegar: al cargar la página no se mueve nada.
                if (scrollThumbs && thumbs[i]) thumbs[i].scrollIntoView({ inline: 'nearest', block: 'nearest' });
            };
            let raf = 0;
            track.addEventListener('scroll', () => {
                cancelAnimationFrame(raf);
                raf = requestAnimationFrame(() => sync(true));
            }, { passive: true });
            // En computador con mouse no hay deslizar con el dedo: se arrastra
            // la foto, igual que en el celular. Sobre un video no se arrastra,
            // para no pelear con sus controles.
            let arrastre = null;
            track.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'mouse' || e.button !== 0 || e.target.closest('video')) return;
                arrastre = { x: e.clientX, left: track.scrollLeft, movio: false };
            });
            track.addEventListener('pointermove', (e) => {
                if (!arrastre) return;
                const dx = e.clientX - arrastre.x;
                if (!arrastre.movio && Math.abs(dx) < 4) return;
                if (!arrastre.movio) {
                    arrastre.movio = true;
                    track.classList.add('dragging');
                    track.style.scrollSnapType = 'none';
                    try { track.setPointerCapture(e.pointerId); } catch (err) {}
                }
                track.scrollLeft = arrastre.left - dx;
                e.preventDefault();
            });
            const soltar = () => {
                if (!arrastre) return;
                const movio = arrastre.movio;
                arrastre = null;
                track.classList.remove('dragging');
                track.style.scrollSnapType = '';
                if (movio) goTo(index());
            };
            track.addEventListener('pointerup', soltar);
            track.addEventListener('pointercancel', soltar);
            track.addEventListener('pointerleave', soltar);
            track.addEventListener('dragstart', (e) => e.preventDefault());

            thumbs.forEach((t) => t.addEventListener('click', () => goTo(Number(t.dataset.i))));
            room.querySelectorAll('[data-nav]').forEach((b) => b.addEventListener('click', () => goTo(index() + Number(b.dataset.nav))));
            sync();
        });

        // Cupones y ofertas: se arrastran con el mouse y avanzan solos.
        (function () {
            const pista = document.getElementById('deals-track');
            if (!pista) return;
            const paso = () => {
                const c = pista.querySelector('.deal');
                return (c ? c.getBoundingClientRect().width : 280) + 12;
            };

            let arrastre = null, movio = false;
            pista.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'mouse' || e.button !== 0) return;
                arrastre = { x: e.clientX, left: pista.scrollLeft };
                movio = false;
            });
            pista.addEventListener('pointermove', (e) => {
                if (!arrastre) return;
                const dx = e.clientX - arrastre.x;
                if (!movio && Math.abs(dx) < 4) return;
                if (!movio) {
                    movio = true;
                    pista.classList.add('dragging');
                    pista.style.scrollSnapType = 'none';
                    try { pista.setPointerCapture(e.pointerId); } catch (err) {}
                }
                pista.scrollLeft = arrastre.left - dx;
                e.preventDefault();
            });
            const soltar = () => {
                if (!arrastre) return;
                arrastre = null;
                pista.classList.remove('dragging');
                pista.style.scrollSnapType = '';
            };
            ['pointerup', 'pointercancel', 'pointerleave'].forEach((ev) => pista.addEventListener(ev, soltar));
            // Soltar después de arrastrar no debe abrir el cupón.
            pista.addEventListener('click', (e) => {
                if (!movio) return;
                movio = false;
                e.preventDefault();
                e.stopPropagation();
            }, true);
            pista.addEventListener('dragstart', (e) => e.preventDefault());

            // Avanza solo cada 5 segundos y vuelve al principio al llegar al final.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            let reloj = 0;
            const avanzar = () => {
                if (document.hidden || arrastre) return;
                const fin = pista.scrollWidth - pista.clientWidth - 4;
                if (pista.scrollLeft >= fin) pista.scrollTo({ left: 0, behavior: 'smooth' });
                else pista.scrollBy({ left: paso(), behavior: 'smooth' });
            };
            const seguir = () => { if (!reloj) reloj = setInterval(avanzar, 5000); };
            const pausar = () => { clearInterval(reloj); reloj = 0; };
            pista.addEventListener('pointerenter', pausar);
            pista.addEventListener('pointerleave', seguir);
            pista.addEventListener('touchstart', pausar, { passive: true });
            document.addEventListener('visibilitychange', () => document.hidden ? pausar() : seguir());
            seguir();
        })();

        // Chips de categoría: marcan la tarjeta que se está viendo y llevan a ella.
        (function () {
            const chips = Array.from(document.querySelectorAll('#cat-chips .chip'));
            if (!chips.length) return;
            const byTarget = new Map(chips.map((c) => [c.dataset.target, c]));
            function mark(id) {
                chips.forEach((c) => c.classList.toggle('on', c.dataset.target === id));
                const on = byTarget.get(id);
                if (on) on.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            }
            chips.forEach((chip) => chip.addEventListener('click', (e) => {
                e.preventDefault();
                const el = document.getElementById(chip.dataset.target);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                mark(chip.dataset.target);
            }));
            if ('IntersectionObserver' in window) {
                const cards = Array.from(document.querySelectorAll('[data-cat]'));
                const io = new IntersectionObserver((entries) => {
                    const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (visible) mark(visible.target.id);
                }, { rootMargin: '-35% 0px -55% 0px', threshold: [0, .1, .5] });
                cards.forEach((c) => io.observe(c));
                window.addEventListener('scroll', () => { if (window.scrollY < 200) mark('playrooms'); }, { passive: true });
            }
        })();
    </script>
    @include('catalog._welcome-offer')
</body>
</html>
