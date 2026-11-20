{{--
    Base del diseño nuevo del catálogo: colores, tipografía y los detalles
    que se repiten en todas las pantallas públicas. Se incluye DESPUÉS del
    <style> propio de cada página, así que lo que viene acá manda.
    Cada página agrega después lo suyo.
--}}
<style>
    :root {
        --hh-accent: #ff7918;
        --hh-accent-hover: #ee6909;
        --hh-bg: #f6f4f0;
        --hh-bg-2: #ffffff;
        --hh-line: #e4e0d8;
        --hh-soft: #6c6f75;
        --hh-tinta: #1b1c1e;
        --hh-warm: #a8713a;
        --hh-canvas: #f6f4f0;
        --hh-ink: #1b1c1e;
    }
    html, body { margin: 0; padding: 0; max-width: 100%; }
    html { background: var(--hh-bg); }
    body { background: var(--hh-bg); color: var(--hh-tinta); }

    h1, h2, h3 { letter-spacing: -.03em; }
    a.back { color: var(--hh-soft); }

    /* Las tarjetas dejan de ser oscuras: blancas, con borde fino. */
    .card { background: var(--hh-bg-2); color: var(--hh-tinta); border: 1px solid var(--hh-line); border-radius: 18px; box-shadow: 0 2px 12px rgba(30,25,20,.05); }

    /* Botones redondos y naranjos, como en el catálogo. */
    .btn-main, button.submit, a.cta-go { border-radius: 14px; }
    a.cta-go { box-shadow: 0 6px 18px rgba(255,121,24,.3); }
</style>
