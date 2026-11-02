<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>HH — Pantalla</title>
    {{-- Se recarga cada 15 minutos para tomar combos y precios nuevos. --}}
    <meta http-equiv="refresh" content="900">
    <style>
        :root { --accent:#ff7918; --accent-soft:#ffb078; --ink:#f4f4f2; --muted:#a7a9ae; --bg:#0e0f11; }
        * { box-sizing:border-box; }
        html, body { margin:0; height:100%; background:var(--bg); color:var(--ink); font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; overflow:hidden; cursor:none; }
        .slide { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:5vh 6vw; opacity:0; transition:opacity 1.2s ease; }
        .slide.on { opacity:1; }
        .bg { position:absolute; inset:0; background-size:cover; background-position:center; filter:brightness(.28) saturate(1.1); transform:scale(1.04); transition:transform 14s linear; }
        .slide.on .bg { transform:scale(1.12); }
        .content { position:relative; width:100%; display:flex; align-items:center; gap:6vw; }
        .logo { position:fixed; top:4vh; left:5vw; height:7vh; z-index:5; opacity:.95; }
        .tag { display:inline-block; font-size:1.7vw; font-weight:800; letter-spacing:.12em; text-transform:uppercase; color:var(--accent-soft); border:2px solid var(--accent); border-radius:999px; padding:.5vh 1.4vw; margin-bottom:3vh; }
        h1 { font-size:6.4vw; line-height:1; letter-spacing:-.03em; margin:0 0 2.5vh; }
        .desc { font-size:2.2vw; color:var(--muted); margin:0 0 3vh; max-width:55vw; }
        ul { list-style:none; padding:0; margin:0 0 4vh; font-size:2.4vw; }
        li { padding:.6vh 0; }
        li::before { content:"✓"; color:var(--accent); font-weight:900; margin-right:1.2vw; }
        .price { font-size:7vw; font-weight:900; color:var(--accent); letter-spacing:-.02em; }
        .cta { font-size:2.2vw; color:var(--ink); margin-top:2vh; }
        .cta strong { color:var(--accent-soft); }
        .text { flex:1; }
        .qr { flex:none; background:#fff; border-radius:2vw; padding:1.2vw; box-shadow:0 2vh 6vh rgba(0,0,0,.5); }
        .qr img { display:block; width:30vh; height:30vh; }
        .qr-big img { width:44vh; height:44vh; }
        .dots { position:fixed; bottom:4vh; left:0; right:0; display:flex; justify-content:center; gap:1vw; z-index:5; }
        .dots i { width:1vw; height:1vw; max-width:14px; max-height:14px; border-radius:50%; background:#3a3c40; }
        .dots i.on { background:var(--accent); }
        .hint { position:fixed; bottom:2vh; right:2vw; font-size:13px; color:#55585e; z-index:5; }
    </style>
</head>
<body>
    <img class="logo" src="{{ asset('images/hh-logo-wide.png') }}?v=2" alt="HH">

    @php $photo = fn ($i) => $photos->isNotEmpty() ? $photos[$i % $photos->count()] : null; $n = 0; @endphp

    @foreach ($combos as $combo)
        <section class="slide" data-seconds="10">
            @if ($photo($n)) <div class="bg" style="background-image:url('{{ $photo($n++) }}')"></div> @endif
            <div class="content">
                <div class="text">
                    <span class="tag">Combo</span>
                    <h1>{{ $combo['name'] }}</h1>
                    @if ($combo['description'])<p class="desc">{{ $combo['description'] }}</p>@endif
                    @if ($combo['items']->isNotEmpty())
                        <ul>
                            @foreach ($combo['items'] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                    @endif
                    <div class="price">${{ number_format($combo['price'], 0, ',', '.') }}</div>
                    <div class="cta">Agrégalo a tu estadía en <strong>recepción</strong></div>
                </div>
            </div>
        </section>
        {{-- Un QR cada dos combos, para que no pasen muchos segundos sin verlos. --}}
        @if ($loop->iteration % 2 === 0 && ! $loop->last)
            @include('tv._qr', ['kind' => $loop->iteration % 4 === 0 ? 'review' : 'reserve'])
        @endif
    @endforeach

    @include('tv._qr', ['kind' => 'reserve'])
    @include('tv._qr', ['kind' => 'review'])

    <div class="dots" id="dots"></div>
    <div class="hint" id="hint">Toca la pantalla para verla completa</div>

    <script>
        const slides = Array.from(document.querySelectorAll('.slide'));
        const dots = document.getElementById('dots');
        slides.forEach(() => dots.appendChild(document.createElement('i')));
        let i = -1;
        function next() {
            if (i >= 0) { slides[i].classList.remove('on'); dots.children[i].classList.remove('on'); }
            i = (i + 1) % slides.length;
            slides[i].classList.add('on'); dots.children[i].classList.add('on');
            setTimeout(next, (Number(slides[i].dataset.seconds) || 10) * 1000);
        }
        next();
        // Pantalla completa con un toque (los TV y navegadores lo exigen).
        document.addEventListener('click', () => {
            const el = document.documentElement;
            (el.requestFullscreen || el.webkitRequestFullscreen || function () {}).call(el);
            document.getElementById('hint').remove();
        }, { once: true });
    </script>
</body>
</html>
