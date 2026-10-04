<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->name }} — HH Motel</title>
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
        .wrap { max-width: 720px; margin: 0 auto; padding: 0 20px 60px; }

        header.hero { text-align:center; padding: 32px 20px 24px; }
        header.hero .brand { font-size: 14px; font-weight: 800; letter-spacing: .1em; color: var(--hh-accent); margin-bottom: 8px; }
        a.back { display:inline-block; color:#62656c; text-decoration:none; font-size:13px; margin-bottom: 14px; }

        .room-card { background:var(--hh-surface); color:#f5f5f5; border:1px solid #454649; border-left-width:4px; border-radius: 12px; overflow:hidden; box-shadow: 0 3px 10px #1011120b; }
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

        .gallery { display:grid; grid-template-columns: repeat(4, 1fr); gap:2px; background:#202122; }
        .gallery a { display:block; aspect-ratio: 4/3; overflow:hidden; }
        .gallery img { width:100%; height:100%; object-fit:cover; display:block; transition: transform .2s ease; }
        .gallery a:hover img { transform: scale(1.05); }
        .gallery.empty { aspect-ratio: 16/6; display:flex; align-items:center; justify-content:center; color:#8a8c90; font-size:13px; grid-template-columns:none; }

        .room-body { padding: 22px 24px 26px; }
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

        .share-box { margin-top:18px; background:#fff; border:1px solid #e2e2df; border-radius:10px; padding:14px 16px; }
        .share-box .label { font-size:11.5px; color:#62656c; text-transform:uppercase; letter-spacing:.04em; margin-bottom:8px; }
        .share-row { display:flex; gap:8px; }
        .share-row input { flex:1; min-width:0; background:#f5f5f4; border:1px solid #d1d3d8; border-radius:7px; padding:9px 11px; font-size:12.5px; color:#42464e; }
        .share-row button { flex:none; background:#262729; color:#eee; border:none; border-radius:7px; padding:9px 14px; font-weight:600; font-size:12.5px; cursor:pointer; }
        .share-row button:hover { background:#1c1d1f; }

        footer.catalog-footer { text-align:center; color:#8a8d93; font-size:12.5px; padding: 26px 0 10px; }

        @media (max-width: 560px) {
            .gallery { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="hero">
            <div class="brand">HH MOTEL</div>
        </header>
        <a class="back" href="{{ route('catalog.index') }}">← Volver al catálogo</a>

        <section class="room-card cat-{{ Str::slug($category->name) }}">
            @if ($photos->isNotEmpty())
                <div class="gallery">
                    @foreach ($photos->take(8) as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="Foto {{ $room->name }}" loading="lazy"></a>
                    @endforeach
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

                <div class="btn-row">
                    <a class="btn primary" href="{{ route('catalog.reserve', ['categoria' => $category->id, 'room_id' => $room->id]) }}" style="flex:none; width:100%;">Reservar online →</a>
                </div>
            </div>
        </section>

        <div class="share-box">
            <div class="label">Link de esta ficha</div>
            <div class="share-row">
                <input type="text" readonly value="{{ route('catalog.room', $room) }}" id="room-link" onclick="this.select()">
                <button type="button" id="room-link-copy" onclick="hhCopyRoomLink()">Copiar</button>
            </div>
        </div>

        <footer class="catalog-footer">HH Motel</footer>
    </div>
    <div class="video-modal" id="video-modal" role="dialog" aria-modal="true" aria-label="Reproductor de video">
        <button class="video-close" type="button" id="video-close">Cerrar ✕</button>
        <video id="video-modal-player" controls playsinline></video>
    </div>
    <script>
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

        // navigator.clipboard falla callado (sin lanzar error visible) en
        // varios navegadores embebidos -- como el de WhatsApp -- y sin
        // .catch() el botón igual decía "¡Copiado!" aunque no copió nada.
        // Con execCommand('copy') como respaldo, y mostrando el resultado
        // real, no el optimista.
        function hhCopyRoomLink() {
            const input = document.getElementById('room-link');
            const btn = document.getElementById('room-link-copy');
            const show = (ok) => {
                btn.textContent = ok ? '¡Copiado!' : 'No se pudo — mantén tocado el link';
                setTimeout(() => { btn.textContent = 'Copiar'; }, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(() => show(true)).catch(() => {
                    input.select();
                    try { show(document.execCommand('copy')); } catch (e) { show(false); }
                });
            } else {
                input.select();
                try { show(document.execCommand('copy')); } catch (e) { show(false); }
            }
        }
    </script>
</body>
</html>
