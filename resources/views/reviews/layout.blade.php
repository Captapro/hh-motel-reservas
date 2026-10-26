<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111111">
    <title>@yield('title') — HH Motel</title>
    <style>{!! file_get_contents(public_path('css/hh-theme.css')) !!}</style>
    <style>
        :root { --accent:var(--hh-accent); --muted:#c1c3c7; }
        body { min-height:100svh; padding:0; }
        .shell { width:min(100%,480px); margin:auto; padding:28px 20px 24px; }
        .brand { background:#000; padding:22px 20px 20px; border-radius:0 0 18px 18px; }
        .brand-inner { max-width:440px; margin:auto; text-align:center; }
        .brand img { display:block; width:auto; max-width:min(260px,70vw); height:78px; object-fit:contain; margin:auto; }
        .review-content { text-align:left; }
        .eyebrow { color:#62656c; font-size:16px; font-weight:600; margin:0 0 12px; }
        h1 { font-size:clamp(36px,10vw,44px); font-weight:750; line-height:1.06; letter-spacing:-.045em; margin:0 0 18px; }
        h1 em { color:#a74600; font-style:normal; }
        .intro { color:#62656c; font-size:18px; max-width:380px; margin:0 0 24px; }
        .review-form { background:var(--hh-surface); color:#f5f5f5; border:1px solid #454649; border-radius:12px; padding:24px; }
        fieldset { padding:0; margin:0; border:0; min-width:0; }
        legend { width:100%; color:#e0e2e5; font-size:17px; margin-bottom:16px; }
        .stars { display:flex; justify-content:space-between; gap:10px; }
        .rating-option { position:relative; flex:1; max-width:78px; cursor:pointer; }
        .rating-option input { position:absolute; opacity:0; width:1px; height:1px; }
        .rating-tile { display:flex; align-items:center; justify-content:center; aspect-ratio:1; border:1px solid transparent; border-radius:8px; background:transparent; color:#bbbfc5; transition:background .18s,transform .18s,color .18s; }
        .rating-tile svg { width:44px; height:44px; fill:transparent; stroke:currentColor; stroke-width:1.5; }
        .rating-option input:checked + .rating-tile,.rating-option:has(~ .rating-option input:checked) .rating-tile { color:var(--accent); background:transparent; border-color:transparent; }
        .rating-option input:checked + .rating-tile svg,.rating-option:has(~ .rating-option input:checked) svg { fill:currentColor; }
        .rating-option:hover .rating-tile {  color:#ffb379;  }
        .rating-option input:focus-visible + .rating-tile,button:focus-visible,textarea:focus-visible { outline:3px solid #ffb379; outline-offset:4px; }
        .scale { display:flex; justify-content:space-between; color:var(--muted); font-size:14px; margin:10px 0 0; }
        .selection { font-size:17px; color:#ffb379; min-height:22px; margin:22px 0 18px; }
        .submit { display:flex; justify-content:center; align-items:center; gap:14px; width:100%; border:0; border-radius:9px; padding:16px; background:var(--hh-accent); color:#21170e; font:700 18px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; cursor:pointer;  }
        .submit:hover { background:var(--hh-accent-hover); }
        .submit span { font-size:20px; line-height:1; }
        .note { color:#62656c; margin:20px 0 0; font-size:16px; }
        footer { display:flex; justify-content:space-between; border-top:1px solid #d9dadd; margin-top:32px; color:#62656c; font-size:14px; padding-top:16px; }
        footer span { display:block; margin-bottom:5px; }
        .brand-motto { margin:32px 0 28px; color:#62656c; text-align:left; font-size:22px; line-height:1.4; letter-spacing:-.02em; font-weight:400; }
        .brand-motto strong { color:var(--hh-ink); font-weight:750; }
        .banner,.error { padding:12px 15px; border-radius:12px; margin:0 0 22px; font-size:16px; }
        .banner { background:#243c2d; color:#c0e8c9; }
        .error { background:#4e2626; color:#ffd0ca; }
        .comment-label { display:block; text-align:left; font-size:17px; margin:0 0 9px; color:#e0e2e5; }
        textarea { width:100%; min-height:150px; background:#202123; border:1px solid #62656b; border-radius:9px; padding:15px; font-size:18px; line-height:1.6; font-family:inherit; color:#f4f5f7; resize:vertical; }
        textarea::placeholder { color:#b9bdc4; }
        .field-note { text-align:right; margin:5px 0 20px; color:var(--muted); font-size:14px; }
        @media(max-width:400px) { .shell { padding:26px 20px 24px; } .brand { padding:20px; } .review-form { padding:20px 16px; } .stars { gap:4px; } .rating-tile svg { width:38px; height:38px; } }
        @media(prefers-reduced-motion:reduce) { *,*::before { transition:none!important; } .rating-option:hover .rating-tile { transform:none; } }
        .submit { min-height:52px; }
        footer { padding-bottom:env(safe-area-inset-bottom, 0px); }
    </style>
</head>
<body>
    <header class="brand"><div class="brand-inner">
            <img src="{{ asset('images/hh-logo-wide.png').'?v=2' }}" alt="HH Motel Santiago Playrooms" width="720" height="280">
    </div></header>
    <div class="shell">
        <main class="review-content">
            @yield('content')
        </main>
        <p class="brand-motto">Aquí lo privado<br>se convierte en <strong>libertad.</strong></p>
        <footer><span>HH MOTEL</span><span>Santiago, Chile</span></footer>
    </div>
    @yield('scripts')
</body>
</html>
