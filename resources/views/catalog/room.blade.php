<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $room->name }} — HH Motel</title>
    @include('partials.social-meta', ['shareTitle' => $room->name.' — HH Motel Santiago', 'shareDescription' => 'Conoce la habitación '.$room->name.' de HH Motel Santiago y reserva en línea.', 'shareImage' => $photos->isNotEmpty() ? url($photos->first()) : null])
    @include('catalog._app-head')
    <style>
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
        .wrap { max-width: 720px; margin: 0 auto; padding: 14px 16px 30px; }

        header.hero { text-align:center; padding: 32px 20px 24px; }
        header.hero .brand { font-size: 14px; font-weight: 800; letter-spacing: .1em; color: var(--hh-accent); margin-bottom: 8px; }
        a.back { display:inline-block; color:#62656c; text-decoration:none; font-size:13px; margin-bottom: 14px; }

        .room-card { background:#1b1c1e; color:#f5f5f5; border-radius: 24px; overflow:hidden; box-shadow: 0 8px 28px rgba(16,17,18,.14); }
        .room-card.cat-go { border-left-color:#5b9dd9; }
        .room-card.cat-lite { border-left-color:#4ecdc4; }
        .room-card.cat-plus { border-left-color:#b088e8; }
        .room-card.cat-max { border-left-color:#f2994a; }
        .room-card.cat-new-lite { border-left-width:3px; box-shadow: 0 0 0 1px rgba(232,199,111,.3) inset; }
        .cat-go h1 { color:#7fbfe8; }
        .cat-lite h1 { color:#6fe4db; }
        .cat-plus h1 { color:#c9a6f5; }
        .cat-max h1 { color:#f7b06a; }
        .cat-new-lite h1 { color:#f0d98a; }

        .gallery-wrap { position:relative; background:#232427; }
        .gallery { display:flex; overflow-x:auto; scroll-snap-type:x mandatory; scrollbar-width:none; -webkit-overflow-scrolling:touch; overscroll-behavior-x:contain; }
        .gallery::-webkit-scrollbar { display:none; }
        .gallery a { flex:0 0 100%; scroll-snap-align:center; display:block; aspect-ratio:4/3; overflow:hidden; background:#2a2b2e; }
        .gallery img { width:100%; height:100%; object-fit:cover; display:block; }
        .gallery.empty { aspect-ratio: 4/3; display:flex; align-items:center; justify-content:center; color:#8a8c90; font-size:14px; }
        .g-count { position:absolute; top:14px; right:14px; background:rgba(0,0,0,.55); color:#fff; font-size:12px; font-weight:700; padding:5px 10px; border-radius:999px; pointer-events:none; }
        .g-dots { position:absolute; left:0; right:0; bottom:10px; display:flex; justify-content:center; gap:5px; pointer-events:none; }
        .g-dots i { width:6px; height:6px; border-radius:50%; background:#ffffff66; transition:all .18s ease; }
        .g-dots i.on { width:16px; border-radius:4px; background:#fff; }

        .room-body { padding: 20px 18px 22px; }
        h1 { font-size: 22px; margin: 0 0 4px; letter-spacing:-.02em; }
        .cat-label { font-size: 12.5px; color:#9edaff; font-weight:600; margin-bottom: 12px; }
        .cap { font-size: 14px; color: #d5d8de; margin-bottom: 14px; }
        .sales-tip { display:flex; gap:8px; align-items:flex-start; background:#262729; border:1px solid #56585d; color:#f0d18a; border-radius:9px; padding:9px 11px; margin:0 0 14px; font-size:12.5px; line-height:1.35; }
        .sales-tip::before { content:'✦'; color:var(--hh-accent); font-weight:800; }
        .room-offer { background:#191a1c; border:1px solid #ff7918; border-radius:9px; padding:12px 14px; margin:0 0 16px; }
        .room-offer-label { display:inline-flex; align-items:center; gap:6px; background:#3a1c10; border:1px solid #ff7918; color:#ffb078; border-radius:20px; padding:3px 9px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; margin-bottom:8px; }
        .room-offer-price { display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; }
        .room-offer-price .old { color:#8a8d93; text-decoration:line-through; font-size:13.5px; }
        .room-offer-price .new { color:#6ee7b7; font-weight:800; font-size:20px; }
        .room-offer-price .save { background:#123a28; color:#6ee7b7; border-radius:6px; padding:2px 7px; font-size:11px; font-weight:800; }
        .room-offer small { display:block; color:#c9cbd0; margin-top:4px; }
        .features { display:flex; flex-wrap:wrap; gap: 7px; margin-bottom: 18px; }
        .features span { display:inline-block; font-family: ui-monospace, monospace; background:#262729; border:1px solid #56585d; color:#e0e2e5; font-size: 11px; font-weight:600; padding: 3px 11px; border-radius: 20px; }
        .equip-label { font-size: 11px; color:#9edaff; font-weight:700; text-transform:uppercase; letter-spacing:.03em; margin: 4px 0 8px; }
        .equip-features span { font-family:inherit; background:#1c2f3a; border-color:#3a5a72; }

        .video-links { display:flex; flex-wrap:wrap; gap:10px; margin: -6px 0 18px; }
        .video-links a { font-size: 12.5px; color:#9edaff; text-decoration:none; border:1px solid #3a5a72; padding:5px 11px; border-radius:20px; }
        .video-links a:hover { border-color:#9edaff; }
        .video-modal { display:none; position:fixed; inset:0; z-index:20; background:rgba(10,10,10,.92); align-items:center; justify-content:center; padding:24px; }
        .video-modal.is-open { display:flex; }
        .video-modal video { max-width:min(920px, 96vw); max-height:86vh; border-radius:10px; box-shadow:0 12px 40px #000; }
        .video-close { position:fixed; top:18px; right:18px; border:1px solid #ffffff66; background:#222; color:#fff; border-radius:24px; padding:10px 16px; font-weight:700; cursor:pointer; z-index:21; }

        table.price-table { width:100%; border-collapse:collapse; margin-bottom: 20px; font-size: 13.5px; }
        table.price-table th { text-align:left; color:#bbbfc6; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.03em; padding: 0 10px 8px 0; }
        table.price-table td { padding: 8px 10px 8px 0; border-top: 1px solid #505154; }
        table.price-table td.price { font-family: ui-monospace, monospace; font-weight:700; color:#fff; }
        table.price-table td.dash { color:#8a8c90; }

        .btn-row { display:flex; gap:10px; }
        a.btn { flex:1; display:block; text-align:center; background:#262729; border:1px solid #666; color:#eee; text-decoration:none; padding: 13px; border-radius: 9px; font-weight:700; font-size:13.5px; }
        a.btn:hover { border-color:#25d366; }
        a.btn.primary { background:var(--hh-accent); border-color:var(--hh-accent); color:#21170e; }
        a.btn.primary:hover { background:var(--hh-accent-hover); }
        @media (max-width: 420px) { .btn-row { flex-direction:column; } }


        footer.catalog-footer { text-align:center; color:#8a8d93; font-size:12.5px; padding: 26px 0 10px; }

    </style>
</head>
<body class="has-ctabar">
    @include('catalog._app-bar', ['appBack' => route('catalog.index'), 'appTitle' => $room->name])
    <div class="wrap">

        <section class="room-card cat-{{ Str::slug($category->name) }}">
            @if ($photos->isNotEmpty())
                @php $gSlides = $photos->take(8); @endphp
                <div class="gallery-wrap">
                    <div class="gallery" id="room-gallery">
                        @foreach ($gSlides as $url)
                            <a href="{{ \App\Support\Img::url($url, 1280) }}" data-viewer="room" aria-label="Ampliar foto {{ $loop->iteration }}"><img src="{{ \App\Support\Img::url($url, 640) }}" alt="Foto {{ $room->name }}" width="640" height="480" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async"></a>
                        @endforeach
                    </div>
                    @if ($gSlides->count() > 1)
                        <span class="g-count" id="g-count">1/{{ $gSlides->count() }}</span>
                        <div class="g-dots" id="g-dots">@foreach ($gSlides as $g)<i class="{{ $loop->first ? 'on' : '' }}"></i>@endforeach</div>
                    @endif
                </div>
            @else
                <div class="gallery empty">Fotos próximamente</div>
            @endif

            <div class="room-body">
                <h1>{{ $room->name }}</h1>
                <div class="cat-label">Categoría {{ $category->name }}</div>
                <div class="cap">Hasta {{ $category->max_capacity }} personas{{ $category->extra_guest_from ? ' · desde la '.$category->extra_guest_from.'ª persona, cargo adicional' : '' }}</div>

                @if ($offer)
                    <div class="room-offer">
                        <span class="room-offer-label">✦ Oferta activa</span>
                        <div class="room-offer-price">
                            <span class="old">${{ number_format($offer['originalPrice'], 0, ',', '.') }}</span>
                            <span class="new">${{ number_format($offer['offerPrice'], 0, ',', '.') }}</span>
                            @if ($offer['savePct'] > 0)<span class="save">-{{ $offer['savePct'] }}%</span>@endif
                        </div>
                        <small>{{ $offer['name'] }}{{ $offer['durationLabel'] ? ' · '.$offer['durationLabel'] : '' }} — precio ya reflejado al reservar esta habitación.</small>
                    </div>
                @endif

                @if ($salesTip)
                    <div class="sales-tip">{{ $salesTip }}</div>
                @endif

                @if ($category->description)
                    <div class="cap">{{ $category->description }}</div>
                @endif

                @if (!empty($category->features))
                    <div class="features">
                        @foreach ($category->features as $feature)
                            <span>{{ $feature }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($equipment->isNotEmpty())
                    <div class="equip-label">Equipamiento de esta habitación</div>
                    <div class="features equip-features">
                        @foreach ($equipment as $item)
                            <span>{{ $item->icon }} {{ $item->name }}</span>
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

            </div>
        </section>

        <footer class="catalog-footer">HH Motel</footer>
    </div>
    @php
        $from = null;
        foreach ($prices as $row) {
            foreach (['hh', 'hot'] as $k) {
                if ($row[$k] && ($from === null || $row[$k] < $from['price'])) { $from = ['price' => $row[$k], 'duration' => $row['duration']]; }
            }
        }
    @endphp
    <div class="cta-bar">
        <div class="cta-bar-in">
            @if ($from)
                <div class="cta-price"><small>Desde</small><b>${{ number_format($from['price'], 0, ',', '.') }}</b></div>
            @endif
            <a class="cta-go" href="{{ route('catalog.reserve', ['categoria' => $category->id, 'room_id' => $room->id]) }}">Reservar {{ $room->name }}</a>
        </div>
    </div>
    <div class="video-modal" id="video-modal" role="dialog" aria-modal="true" aria-label="Reproductor de video">
        <button class="video-close" type="button" id="video-close">Cerrar ✕</button>
        <video id="video-modal-player" controls playsinline></video>
    </div>
    <script>
        (function () {
            const g = document.getElementById('room-gallery'), dots = document.querySelectorAll('#g-dots i'), count = document.getElementById('g-count');
            if (!g || !dots.length) return;
            let raf = 0;
            g.addEventListener('scroll', () => {
                cancelAnimationFrame(raf);
                raf = requestAnimationFrame(() => {
                    const i = Math.round(g.scrollLeft / g.clientWidth);
                    dots.forEach((d, n) => d.classList.toggle('on', n === i));
                    if (count) count.textContent = (i + 1) + '/' + dots.length;
                });
            }, { passive: true });
        })();
        // Antes el video abría en pestaña nueva (target="_blank") sin ningún
        // botón para volver a la ficha -- ahora se reproduce en un modal con
        // su propio "Cerrar".
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
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeVideo(); });
    </script>
    @include('partials.photo-viewer')
</body>
</html>
