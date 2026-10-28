{{-- Panel lateral con el detalle completo de una venta. Cualquier fila con
     data-sale="CODIGO" lo abre al hacer click (los links dentro de la fila
     siguen funcionando normal). --}}
<style>
    tr[data-sale], tr[data-sale-url] { cursor:pointer; }
    tr[data-sale]:hover td, tr[data-sale-url]:hover td { background:#fff7ed !important; }
    #sd-back { position:fixed; inset:0; background:rgba(17,24,39,.35); z-index:200; display:none; }
    #sd-back.open { display:block; }
    #sd { position:fixed; top:0; right:0; bottom:0; width:min(480px, 100vw); background:#fff; color:#1f2937; z-index:201; box-shadow:-8px 0 30px rgba(16,24,40,.18); transform:translateX(100%); transition:transform .18s ease; display:flex; flex-direction:column; }
    #sd.open { transform:none; }
    #sd-bar { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; }
    #sd-close { background:#fff; border:1px solid #d1d5db; border-radius:7px; width:34px; height:34px; font-size:18px; line-height:1; cursor:pointer; color:#374151; }
    #sd-body { overflow-y:auto; padding:6px 20px 28px; flex:1; -webkit-overflow-scrolling:touch; }
    .sd-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin:12px 0 4px; }
    .sd-head h2 { margin:2px 0 0; font-size:20px; color:#111827; }
    .sd-code { font-family:ui-monospace,monospace; color:#ea580c; font-size:13px; font-weight:700; }
    .sd-status { font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; background:#f3f4f6; color:#4b5563; white-space:nowrap; }
    .sd-status.s-finalizada { background:#eff6ff; color:#1d4ed8; }
    .sd-status.s-check_in, .sd-status.s-confirmada { background:#fff7ed; color:#c2410c; }
    .sd-status.s-cancelada, .sd-status.s-expirada, .sd-status.s-no_show { background:#fef2f2; color:#b91c1c; }
    .sd-sec { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin:20px 0 6px; padding-top:12px; border-top:1px solid #eef0f3; font-weight:700; }
    .sd-dl { display:grid; grid-template-columns:120px 1fr; gap:5px 12px; margin:0; font-size:14px; }
    .sd-dl dt { color:#6b7280; } .sd-dl dd { margin:0; color:#111827; word-break:break-word; }
    .sd-list { margin:0; padding-left:18px; font-size:14px; }
    .sd-mut { color:#6b7280; } .sd-small { font-size:12px; margin-top:2px; }
    .sd-tbl { width:100%; border-collapse:collapse; font-size:14px; }
    .sd-tbl td { padding:7px 0; border-bottom:1px solid #eef0f3 !important; border-left:0 !important; border-right:0 !important; border-top:0 !important; background:transparent !important; vertical-align:top; white-space:normal; }
    .sd-tbl td.n { text-align:right; font-family:ui-monospace,monospace; white-space:nowrap; }
    .sd-tbl tr.sum td { font-weight:700; border-bottom:0 !important; }
    .sd-tag { font-size:10.5px; background:#fef2f2; color:#b91c1c; border-radius:20px; padding:1px 7px; margin-left:4px; }
    .sd-total { margin-top:20px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; }
    .sd-total div { display:flex; justify-content:space-between; padding:4px 0; font-size:14px; }
    .sd-total b { font-family:ui-monospace,monospace; }
    .sd-total div:first-child b { font-size:17px; }
    .sd-total .owe b { color:#dc2626; }
    #sd-body p { font-size:14px; margin:4px 0; }
    .sd-notes { font-size:14px; white-space:pre-wrap; margin:0; }
    .sd-open { display:block; text-align:center; margin-top:22px; background:#ff7918; color:#fff; text-decoration:none; font-weight:700; padding:12px; border-radius:9px; }
</style>
<div id="sd-back"></div>
<aside id="sd" aria-hidden="true" role="dialog" aria-label="Detalle de la venta">
    <div id="sd-bar"><span>Detalle de la venta</span><button type="button" id="sd-close" aria-label="Cerrar">×</button></div>
    <div id="sd-body"></div>
</aside>
<script>
    (function () {
        const panel = document.getElementById('sd'), back = document.getElementById('sd-back'), body = document.getElementById('sd-body');
        const base = @json(url('/ventas/reserva'));
        let token = 0;
        function close() { panel.classList.remove('open'); back.classList.remove('open'); panel.setAttribute('aria-hidden', 'true'); }
        async function open(url) {
            const mine = ++token;
            body.innerHTML = '<p style="padding:24px 0;color:#6b7280">Cargando…</p>';
            panel.classList.add('open'); back.classList.add('open'); panel.setAttribute('aria-hidden', 'false');
            try {
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error(r.status);
                const html = await r.text();
                if (mine === token) { body.innerHTML = html; body.scrollTop = 0; }
            } catch (e) {
                if (mine === token) body.innerHTML = '<p style="padding:24px 0;color:#b91c1c">No se pudo cargar el detalle. Intenta de nuevo.</p>';
            }
        }
        document.addEventListener('click', function (ev) {
            const row = ev.target.closest('tr[data-sale], tr[data-sale-url]');
            if (!row || ev.target.closest('a, button, input, select, label')) return;
            open(row.dataset.saleUrl || base + '/' + encodeURIComponent(row.dataset.sale));
        });
        back.addEventListener('click', close);
        document.getElementById('sd-close').addEventListener('click', close);
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
    })();
</script>
