{{-- Cáscara "app" del catálogo público: tema del celular, instalable en la
     pantalla de inicio, barra superior, barra inferior de pestañas y
     transición suave entre páginas. Se incluye dentro de <head>. --}}
<meta name="theme-color" content="#000000">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="HH Motel">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192.png') }}">
<style>
    @view-transition { navigation: auto; }
    ::view-transition-old(root) { animation: hh-vt-out .12s ease both; }
    ::view-transition-new(root) { animation: hh-vt-in .18s ease both; }
    @keyframes hh-vt-out { to { opacity:0; } }
    @keyframes hh-vt-in { from { opacity:0; transform:translateY(6px); } }

    :root { --safe-t: env(safe-area-inset-top, 0px); --safe-b: env(safe-area-inset-bottom, 0px); --bar-h: 56px; }
    html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; scroll-behavior: smooth; }
    body { -webkit-tap-highlight-color: transparent; -webkit-font-smoothing: antialiased; }
    a, button, summary, label { touch-action: manipulation; }
    img { -webkit-user-drag: none; }
    @media (max-width: 760px) {
        /* 16px evita que iOS haga zoom al tocar un campo. */
        input, select, textarea { font-size: 16px !important; }
    }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        ::view-transition-old(root), ::view-transition-new(root) { animation: none; }
    }

    /* ===== Barra superior ===== */
    .app-bar { position: sticky; top: 0; z-index: 60; background: #000; color: #fff; padding-top: var(--safe-t); }
    .app-bar-in { height: var(--bar-h); max-width: 1100px; margin: 0 auto; padding: 0 8px; display: grid; grid-template-columns: 48px 1fr 48px; align-items: center; gap: 4px; }
    .ab-btn { width: 44px; height: 44px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; background: transparent; border: 0; padding: 0; cursor: pointer; }
    .ab-btn:active { background: #ffffff24; }
    .ab-btn svg { width: 24px; height: 24px; }
    .ab-btn.wa { background: #1f2a22; color: #4ade80; }
    .ab-btn.wa:active { background: #2b3a2f; }
    .ab-center { justify-self: center; display: flex; align-items: center; min-width: 0; color: #fff; text-decoration: none; }
    .ab-center img { display: block; height: 42px; width: auto; }
    .ab-title { font-size: 16px; font-weight: 700; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60vw; }
    .ab-nav { display: none; }
    @media (min-width: 900px) {
        .app-bar-in { grid-template-columns: 1fr auto 1fr; padding: 0 20px; }
        .ab-nav { display: flex; gap: 6px; justify-self: end; align-items: center; grid-column: 3; grid-row: 1; }
        .ab-nav a { color: #d6d8dc; text-decoration: none; font-size: 14px; font-weight: 600; padding: 9px 14px; border-radius: 999px; }
        .ab-nav a:hover { background: #ffffff1c; color: #fff; }
        .ab-nav a.primary { background: var(--hh-accent); color: #21170e; }
        .ab-nav a.primary:hover { background: var(--hh-accent-hover); }
        .ab-btn.wa { display: none; }
        .ab-center { grid-column: 2; }
    }

    /* ===== Barra inferior de pestañas (solo celular) ===== */
    .tab-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 60; background: rgba(0,0,0,.9); -webkit-backdrop-filter: blur(16px); backdrop-filter: blur(16px); border-top: 1px solid #ffffff1c; padding-bottom: var(--safe-b); }
    .tab-bar-in { display: flex; align-items: center; justify-content: space-around; max-width: 560px; margin: 0 auto; height: 62px; padding: 0 6px; }
    .tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 56px; color: #9a9ca1; text-decoration: none; font-size: 10.5px; font-weight: 700; letter-spacing: .01em; }
    .tab svg { width: 24px; height: 24px; }
    .tab.on { color: var(--hh-accent); }
    .tab:active { opacity: .7; }
    .tab.cta { flex: 1.5; }
    .tab.cta span { background: var(--hh-accent); color: #21170e; border-radius: 16px; padding: 12px 20px; font-size: 14px; font-weight: 800; letter-spacing: 0; box-shadow: 0 6px 18px #ff791840; }
    .tab.cta:active span { background: var(--hh-accent-hover); transform: scale(.97); }
    body.has-tabbar { padding-bottom: calc(76px + var(--safe-b)); }
    @media (min-width: 900px) { .tab-bar { display: none; } body.has-tabbar { padding-bottom: 0; } }

    /* ===== Barra de acción fija (ficha de habitación) ===== */
    .cta-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 60; background: rgba(0,0,0,.92); -webkit-backdrop-filter: blur(16px); backdrop-filter: blur(16px); border-top: 1px solid #ffffff1c; padding: 10px 14px calc(10px + var(--safe-b)); }
    .cta-bar-in { max-width: 560px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
    .cta-bar .cta-price { color: #fff; line-height: 1.15; flex: none; }
    .cta-bar .cta-price small { display: block; color: #9a9ca1; font-size: 11px; font-weight: 600; }
    .cta-bar .cta-price b { font-size: 20px; letter-spacing: -.02em; }
    .cta-bar a.cta-go { flex: 1; text-align: center; background: var(--hh-accent); color: #21170e; text-decoration: none; font-weight: 800; font-size: 15px; padding: 15px 18px; border-radius: 14px; box-shadow: 0 6px 18px #ff791840; }
    .cta-bar a.cta-go:active { background: var(--hh-accent-hover); transform: scale(.98); }
    body.has-ctabar { padding-bottom: calc(86px + var(--safe-b)); }
</style>
