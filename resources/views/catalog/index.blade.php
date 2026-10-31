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
            --hh-canvas: #f5f5f4;
            --hh-surface: #1b1c1e;
            --hh-ink: #202124;
        }
        * { box-sizing: border-box; }
        body { background:var(--hh-canvas); color:var(--hh-ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; line-height: 1.45; }
        a { color: inherit; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 0 16px 40px; }

        /* ===== Entrada ===== */
        .intro { padding: 20px 2px 6px; }
        .intro h1 { font-size: 30px; line-height: 1.1; letter-spacing: -.035em; margin: 0 0 6px; }
        .intro p { color:#4b4e54; font-size: 17px; margin: 0; }

        /* ===== Categorías (chips que se quedan fijos al bajar) ===== */
        .cat-chips-wrap { position: sticky; top: calc(var(--bar-h) + var(--safe-t)); z-index: 40; margin: 12px -16px 4px; padding: 8px 16px; background: rgba(245,245,244,.92); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); }
        .cat-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; padding: 2px 0; }
        .cat-chips::-webkit-scrollbar { display: none; }
        .chip { flex: none; display: inline-flex; align-items: center; gap: 7px; min-height: 40px; padding: 0 16px; border-radius: 999px; background: #fff; border: 1px solid #dcdcd8; color: #3a3d42; font-size: 16px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .chip i { width: 9px; height: 9px; border-radius: 50%; background: var(--c, #9a9ca1); }
        .chip.on { background: #111; border-color: #111; color: #fff; }
        .chip:active { transform: scale(.97); }

        /* ===== Ofertas y cupones ===== */
        .deals-carousel { margin: 14px 0 22px; scroll-margin-top: calc(var(--bar-h) + var(--safe-t) + 60px); }
        .deals-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin:0 0 10px; }
        .deals-head h2 { font-size:20px; letter-spacing:-.02em; margin:0; color:#242527; }
        .limited-badge { display:inline-flex; align-items:center; gap:6px; background:#3a1c10; border:1px solid #ff7918; color:#ffb078; border-radius:20px; padding:4px 10px; font-size:12.5px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
        .limited-badge .dot { width:6px; height:6px; border-radius:50%; background:#ff7918; animation: hh-pulse 1.6s ease-in-out infinite; }
        @keyframes hh-pulse { 0%, 100% { opacity:1; } 50% { opacity:.25; } }
        .deals-track-wrap { position:relative; margin: 0 -16px; }
        .deals-track { display:flex; gap:12px; overflow-x:auto; scroll-snap-type:x mandatory; padding:2px 16px 12px; scroll-padding: 0 16px; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
        .deals-track::-webkit-scrollbar { display:none; }
        .deal-card { scroll-snap-align:start; flex:0 0 80%; max-width:300px; display:flex; flex-direction:column; border-radius:18px; overflow:hidden; text-decoration:none; color:inherit; }
        @media (min-width:600px) { .deal-card { flex-basis:300px; } }
        .deal-card.is-offer { background:#17181a; border:1px solid #ff7918; }
        .deal-card.is-coupon { background:#111214; border:1px solid #3a5a72; }
        .deal-band { padding:9px 15px; font-size:13px; font-weight:800; letter-spacing:.08em; color:#ffb379; background:linear-gradient(90deg, #3a1f0c, #241912); border-bottom:1px solid #4a2c14; }
        .deal-band.coupon { color:#9edaff; background:linear-gradient(90deg, #12293a, #151d24); border-bottom-color:#243a4c; }
        .deal-body { padding:14px 16px 16px; display:flex; flex-direction:column; flex:1; }
        .deal-body strong.deal-title { display:block; font-size:18px; color:#fff; margin-bottom:3px; }
        .deal-sub-link { color:#c9cbd0; font-size:15px; text-decoration:none; display:inline-block; margin-top:2px; }
        .offer-price-row { display:flex; align-items:baseline; gap:7px; margin:2px 0 3px; flex-wrap:wrap; }
        .offer-price-old { color:#6b6e74; text-decoration:line-through; font-size:15.5px; }
        .offer-price-new { color:#ffd9b8; font-weight:800; font-size:20px; }
        .offer-save-badge { background:#4a2410; color:#ffb078; border-radius:6px; padding:2px 6px; font-size:12.5px; font-weight:800; }
        .deal-coupon-benefit { color:#9edaff; font-size:28px; font-weight:800; letter-spacing:-.02em; }
        .deal-coupon-hint { color:#6b6e74; font-size:14px; margin-top:4px; }
        .deal-cta-btn { display:block; text-align:center; margin-top:auto; padding-top:12px; }
        .deal-cta-btn span { display:block; background:#ff7918; color:#21170e; border-radius:12px; padding:13px 12px; font-size:16px; font-weight:800; }
        .deal-card.is-coupon .deal-cta-btn { color:#9edaff; font-size:15.5px; font-weight:700; }
        .deals-nav { display:none; align-items:center; justify-content:center; position:absolute; top:50%; transform:translateY(-50%); width:36px; height:36px; border-radius:50%; background:#fff; border:1px solid #d9dadd; box-shadow:0 2px 8px #0002; cursor:pointer; font-size:18px; z-index:2; padding:0; }
        .deals-nav.prev { left:4px; }
        .deals-nav.next { right:4px; }
        @media (hover:hover) and (min-width:760px) { .deals-nav { display:flex; } }

        /* ===== Tarjetas de Playroom ===== */
        .pg-list { display: grid; gap: 18px; margin-top: 8px; }
        @media (min-width: 900px) { .pg-list { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px; } .intro h1 { font-size: 36px; } }
        .pg-card { --c: #9a9ca1; background: var(--hh-surface); color: #f5f5f5; border-radius: 24px; overflow: hidden; box-shadow: 0 8px 28px rgba(16,17,18,.14); scroll-margin-top: calc(var(--bar-h) + var(--safe-t) + 66px); display: flex; flex-direction: column; }
        .pg-card.cat-go { --c: #5b9dd9; } .pg-card.cat-lite { --c: #4ecdc4; } .pg-card.cat-plus { --c: #b088e8; }
        .pg-card.cat-max { --c: #f2994a; } .pg-card.cat-new-lite { --c: #e8c76f; }
        .chip.cat-go { --c: #5b9dd9; } .chip.cat-lite { --c: #4ecdc4; } .chip.cat-plus { --c: #b088e8; }
        .chip.cat-max { --c: #f2994a; } .chip.cat-new-lite { --c: #e8c76f; }

        .pg-media { position: relative; background: #232427; }
        .pg-slides { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
        .pg-slides::-webkit-scrollbar { display: none; }
        .pg-slide { flex: 0 0 100%; scroll-snap-align: center; display: block; aspect-ratio: 4 / 3; overflow: hidden; background: #2a2b2e; }
        .pg-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pg-empty { aspect-ratio: 4 / 3; display: flex; align-items: center; justify-content: center; color: #8a8c90; font-size: 16px; background: linear-gradient(160deg, #26272a, #1b1c1e); }
        .pg-shade { position: absolute; left: 0; right: 0; bottom: 0; height: 58%; background: linear-gradient(to top, rgba(10,10,11,.82), rgba(10,10,11,0)); pointer-events: none; }
        .pg-title { position: absolute; left: 18px; right: 18px; bottom: 16px; pointer-events: none; }
        .pg-title h2 { margin: 0; font-size: 28px; line-height: 1.05; letter-spacing: -.03em; color: #fff; text-shadow: 0 2px 12px rgba(0,0,0,.4); }
        .pg-title span { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 15.5px; font-weight: 600; color: #e6e7ea; }
        .pg-title span::before { content: ''; width: 9px; height: 9px; border-radius: 50%; background: var(--c); }
        .pg-count { position: absolute; top: 14px; right: 14px; background: rgba(0,0,0,.55); color: #fff; font-size: 14.5px; font-weight: 700; padding: 5px 10px; border-radius: 999px; -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); pointer-events: none; }
        .pg-offer { position: absolute; top: 14px; left: 14px; background: #ff7918; color: #21170e; font-size: 14.5px; font-weight: 800; padding: 6px 11px; border-radius: 999px; pointer-events: none; }
        .pg-dots { z-index: 2; position: absolute; left: 50%; bottom: 8px; transform: translateX(-50%); display: flex; justify-content: center; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 999px; background: rgba(0,0,0,.55); box-shadow: 0 1px 4px rgba(0,0,0,.35); pointer-events: none; }
        .pg-dots i { width: 8px; height: 8px; border-radius: 50%; background: #ffffffa6; transition: all .18s ease; }
        .pg-dots i.on { width: 20px; border-radius: 5px; background: #fff; }

        .pg-body { padding: 18px 18px 20px; display: flex; flex-direction: column; gap: 14px; flex: 1; }
        .pg-price { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
        .pg-price small { color: #a2a5ab; font-size: 15.5px; font-weight: 600; }
        .pg-price b { font-size: 30px; letter-spacing: -.03em; color: #fff; }
        .pg-price em { font-style: normal; color: #a2a5ab; font-size: 15.5px; }
        .pg-desc { margin: 0; color: #d5d8de; font-size: 16.5px; }
        .pg-features { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -18px; padding: 0 18px; }
        .pg-features::-webkit-scrollbar { display: none; }
        .pg-features span { flex: none; background: #2a2b2e; border: 1px solid #3a3c40; color: #e6e7ea; font-size: 15px; font-weight: 600; padding: 7px 13px; border-radius: 999px; }
        .pg-tip { display: flex; gap: 9px; align-items: flex-start; background: #26272a; border-radius: 14px; padding: 11px 13px; color: #f0d18a; font-size: 15.5px; line-height: 1.4; }
        .pg-tip::before { content: '✦'; color: var(--hh-accent); font-weight: 800; }

        details.pg-prices { background: #26272a; border-radius: 14px; }
        details.pg-prices summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-height: 48px; padding: 0 14px; font-size: 16px; font-weight: 700; color: #e6e7ea; }
        details.pg-prices summary::-webkit-details-marker { display: none; }
        details.pg-prices summary::after { content: '▾'; color: #a2a5ab; transition: transform .18s ease; }
        details.pg-prices[open] summary::after { transform: rotate(180deg); }
        table.price-table { width: calc(100% - 28px); margin: 0 14px 12px; border-collapse: collapse; font-size: 16px; }
        table.price-table th { text-align: left; color: #a2a5ab; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; padding: 0 8px 8px 0; }
        table.price-table td { padding: 10px 8px 10px 0; border-top: 1px solid #3a3c40; }
        table.price-table td.price { font-weight: 800; color: #fff; font-variant-numeric: tabular-nums; }
        table.price-table td.dash { color: #6f7176; }

        .video-links { display: flex; flex-wrap: wrap; gap: 8px; }
        .video-links a { font-size: 15.5px; color: #9edaff; text-decoration: none; border: 1px solid #3a5a72; padding: 8px 14px; border-radius: 999px; }

        a.pg-btn { display: block; text-align: center; background: var(--hh-accent); color: #21170e; text-decoration: none; font-weight: 800; font-size: 18px; padding: 16px; border-radius: 16px; margin-top: auto; box-shadow: 0 8px 22px #ff79183a; }
        a.pg-btn:active { background: var(--hh-accent-hover); transform: scale(.985); }

        .help { margin: 26px 0 6px; background: #fff; border: 1px solid #e4e4e0; border-radius: 22px; padding: 20px; text-align: center; }
        .help strong { display: block; font-size: 19px; margin-bottom: 4px; }
        .help p { margin: 0 0 14px; color: #4b4e54; font-size: 16px; }
        .help a { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 22px; border-radius: 14px; background: #111; color: #fff; text-decoration: none; font-weight: 700; font-size: 17px; }
        footer.catalog-footer { text-align: center; color: #6b6e74; font-size: 15px; padding: 18px 0 8px; }

        .photo-modal, .video-modal { display:none; position:fixed; inset:0; z-index:90; background:rgba(8,8,9,.95); align-items:center; justify-content:center; padding: calc(24px + var(--safe-t)) 12px calc(24px + var(--safe-b)); }
        .photo-modal.is-open, .video-modal.is-open { display:flex; }
        .photo-modal img { max-width:min(1000px, 100%); max-height:82vh; object-fit:contain; border-radius:14px; }
        .video-modal video { max-width:min(1000px, 100%); max-height:82vh; border-radius:14px; }
        .photo-close { position:fixed; top: calc(14px + var(--safe-t)); right:14px; border:0; background:#ffffff26; color:#fff; border-radius:999px; min-height:44px; padding:0 18px; font-weight:700; font-size:16px; cursor:pointer; z-index:91; }
    </style>
</head>
@php
    $hasOffers = $categories->contains(fn ($entry) => $entry['offer']);
    $hasDeals = $hasOffers || $coupons->isNotEmpty();
@endphp
<body class="has-tabbar">
    @include('catalog._app-bar', ['appHasOffers' => $hasDeals])

    <div class="wrap">
        <section class="intro">
            <h1>Elige tu Playroom</h1>
            <p>Reserva en línea en un minuto o escríbenos por WhatsApp.</p>
        </section>

        @if ($categories->count() > 1)
            <div class="cat-chips-wrap">
                <nav class="cat-chips" id="cat-chips" aria-label="Categorías">
                    <a class="chip on" href="#top" data-target="top">Todas</a>
                    @foreach ($categories as $entry)
                        <a class="chip cat-{{ Str::slug($entry['category']->name) }}" href="#cat-{{ Str::slug($entry['category']->name) }}" data-target="cat-{{ Str::slug($entry['category']->name) }}"><i></i>{{ $entry['category']->name }}</a>
                    @endforeach
                </nav>
            </div>
        @endif

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
        @if ($hasDeals)
            <section class="deals-carousel" id="ofertas">
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
                                        <div class="deal-band">✦ OFERTA · {{ $entry['category']->name }}</div>
                                        <div class="deal-body">
                                            <strong class="deal-title">{{ $offerRoom['name'] }}{{ $entry['offer']['durationLabel'] ? ' · '.$entry['offer']['durationLabel'] : '' }}</strong>
                                            {!! $offerPriceRow($entry['offer']) !!}
                                            <a class="deal-sub-link" href="{{ route('catalog.room', $offerRoom['id']) }}">{{ $entry['category']->name }} · Ver ficha y fotos →</a>
                                            <a class="deal-cta-btn" href="{{ route('catalog.reserve', ['categoria' => $entry['category']->id, 'room_id' => $offerRoom['id']]) }}"><span>Reservar oferta →</span></a>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="deal-card is-offer">
                                    <div class="deal-band">✦ OFERTA · {{ $entry['category']->name }}</div>
                                    <div class="deal-body">
                                        <strong class="deal-title">{{ $entry['category']->name }}{{ $entry['offer']['durationLabel'] ? ' · '.$entry['offer']['durationLabel'] : '' }}</strong>
                                        {!! $offerPriceRow($entry['offer']) !!}
                                        <a class="deal-cta-btn" href="{{ route('catalog.reserve', ['categoria' => $entry['category']->id]) }}"><span>Reservar oferta →</span></a>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @foreach ($coupons as $coupon)
                            <a class="deal-card is-coupon" href="{{ route('catalog.reserve', ['cupon' => $coupon['code']]) }}">
                                <div class="deal-band coupon">🎟 CUPÓN</div>
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

        <div id="top"></div>
        <div class="pg-list">
        @forelse ($categories as $entry)
            @php
                [$category, $photos, $videos, $prices] = [$entry['category'], $entry['photos'], $entry['videos'], $entry['prices']];
                $slug = Str::slug($category->name);
                $slides = $photos->take(6);
                // "Desde $X": el precio más bajo de la tabla y a qué duración corresponde.
                $from = null;
                foreach ($prices as $row) {
                    foreach (['hh', 'hot'] as $k) {
                        if ($row[$k] && ($from === null || $row[$k] < $from['price'])) {
                            $from = ['price' => $row[$k], 'duration' => $row['duration']];
                        }
                    }
                }
            @endphp
            <section class="pg-card cat-{{ $slug }}" id="cat-{{ $slug }}" data-cat>
                <div class="pg-media">
                    @if ($slides->isNotEmpty())
                        <div class="pg-slides" data-slides>
                            @foreach ($slides as $url)
                                <a href="{{ $url }}" class="pg-slide photo-trigger" data-photo="{{ \App\Support\Img::url($url, 1280) }}" aria-label="Ver foto {{ $loop->iteration }} de {{ $category->name }}"><img src="{{ \App\Support\Img::url($url, 640) }}" alt="Foto {{ $category->name }}" width="640" height="480" @if ($loop->first && $loop->parent->first) fetchpriority="high" @else loading="lazy" @endif decoding="async"></a>
                            @endforeach
                        </div>
                        @if ($slides->count() > 1)
                            <span class="pg-count" data-count>1/{{ $slides->count() }}</span>
                            <div class="pg-dots" data-dots>@foreach ($slides as $s)<i class="{{ $loop->first ? 'on' : '' }}"></i>@endforeach</div>
                        @endif
                    @else
                        <div class="pg-empty">Fotos próximamente</div>
                    @endif
                    @if ($entry['offer'])<span class="pg-offer">✦ Oferta</span>@endif
                    <div class="pg-shade"></div>
                    <div class="pg-title">
                        <h2>{{ $category->name }}</h2>
                        <span>Hasta {{ $category->max_capacity }} personas</span>
                    </div>
                </div>

                <div class="pg-body">
                    @if ($from)
                        <div class="pg-price"><small>Desde</small><b>${{ number_format($from['price'], 0, ',', '.') }}</b><em>/ {{ $from['duration'] >= 60 ? intdiv($from['duration'], 60).' h' : $from['duration'].' min' }}</em></div>
                    @endif

                    @if ($category->description)
                        <p class="pg-desc">{{ $category->description }}</p>
                    @endif

                    @if (!empty($category->features))
                        <div class="pg-features">
                            @foreach ($category->features as $feature)
                                <span>{{ $feature }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="pg-tip">{{ $entry['salesTip'] }}</div>

                    @if ($category->extra_guest_from)
                        <p class="pg-desc" style="font-size:15.5px;color:#a2a5ab;margin-top:-4px;">Desde la {{ $category->extra_guest_from }}ª persona se cobra un adicional.</p>
                    @endif

                    @if ($prices->isNotEmpty())
                        <details class="pg-prices">
                            <summary>Ver todas las tarifas</summary>
                            <table class="price-table">
                                <thead>
                                    <tr><th>Duración</th><th>Lun a jue</th><th>Vie a dom</th></tr>
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
                        </details>
                    @endif

                    @if ($videos->isNotEmpty())
                        <div class="video-links">
                            @foreach ($videos as $url)
                                <a href="{{ $url }}" class="video-trigger" data-video="{{ $url }}" target="_blank" rel="noopener">▶ Ver video {{ $loop->index + 1 }}</a>
                            @endforeach
                        </div>
                    @endif

                    <a class="pg-btn" href="{{ route('catalog.reserve', ['categoria' => $category->id]) }}">Reservar {{ $category->name }}</a>
                </div>
            </section>
        @empty
            <p style="text-align:center; color:#555;">Todavía no hay categorías activas para mostrar.</p>
        @endforelse
        </div>

        <section class="help">
            <strong>¿Tienes dudas?</strong>
            <p>Te ayudamos a elegir la mejor opción.</p>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Escribir por WhatsApp</a>
        </section>
        <footer class="catalog-footer">HH Motel Santiago</footer>
    </div>

    @include('catalog._tab-bar', ['tabOn' => 'home', 'tabHasOffers' => $hasDeals])

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

        // El video se reproduce en un modal con su propio "Cerrar" (antes abría
        // otra pestaña sin forma de volver al catálogo).
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

        // Carrusel de fotos de cada tarjeta: puntitos y contador siguen el deslizamiento.
        document.querySelectorAll('[data-slides]').forEach((track) => {
            const media = track.closest('.pg-media');
            const dots = media.querySelectorAll('[data-dots] i');
            const count = media.querySelector('[data-count]');
            let raf = 0;
            track.addEventListener('scroll', () => {
                cancelAnimationFrame(raf);
                raf = requestAnimationFrame(() => {
                    const i = Math.round(track.scrollLeft / track.clientWidth);
                    dots.forEach((d, n) => d.classList.toggle('on', n === i));
                    if (count) count.textContent = (i + 1) + '/' + dots.length;
                });
            }, { passive: true });
        });

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
                if (chip.dataset.target === 'top') window.scrollTo({ top: 0, behavior: 'smooth' });
                else if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                mark(chip.dataset.target);
            }));
            if ('IntersectionObserver' in window) {
                const cards = Array.from(document.querySelectorAll('[data-cat]'));
                const io = new IntersectionObserver((entries) => {
                    const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (visible) mark(visible.target.id);
                }, { rootMargin: '-35% 0px -55% 0px', threshold: [0, .1, .5] });
                cards.forEach((c) => io.observe(c));
                window.addEventListener('scroll', () => { if (window.scrollY < 120) mark('top'); }, { passive: true });
            }
        })();

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
    </script>
</body>
</html>
