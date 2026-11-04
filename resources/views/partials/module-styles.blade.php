{{-- Módulos de HH Admin, panel de mucamas y ronda: fondo neutro, un punto de color por estado. --}}
<style>
    .mod { --dot:#9ca3af; border-radius:16px; overflow:hidden; margin-top:14px; background:#fff; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.06); }
    .mod-h { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:11px 14px; border-bottom:1px solid #f0f0ee; color:#111827; font-size:15px; font-weight:800; }
    .mod-h span::before { content:''; display:inline-block; width:10px; height:10px; margin-right:8px; border-radius:50%; background:var(--dot); }
    .mod-h b { min-width:26px; padding:1px 8px; border-radius:999px; background:#f3f4f6; color:#1f2937; text-align:center; font-size:13px; }
    .mod-b { padding:10px; }
    .mod-orange { --dot:#ff7918; }
    .mod-blue { --dot:#0a5cff; }
    .mod-dark { --dot:#111111; }
    .mod-amber { --dot:#a8530f; }
    .mod-gray { --dot:#4b5563; }
</style>
