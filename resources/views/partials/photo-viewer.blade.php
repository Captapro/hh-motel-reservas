<style>
    .pv { display:none; position:fixed; inset:0; z-index:1000; background:rgba(8,8,10,.94); align-items:center; justify-content:center; padding:16px; }
    .pv.is-open { display:flex; }
    .pv img { max-width:min(1100px, 96vw); max-height:84vh; object-fit:contain; border-radius:10px; box-shadow:0 12px 40px #000; }
    .pv button { position:absolute; background:rgba(255,255,255,.14); color:#fff; border:0; cursor:pointer; font:600 22px/1 system-ui, sans-serif; border-radius:999px; width:48px; height:48px; display:flex; align-items:center; justify-content:center; }
    .pv button:hover { background:rgba(255,255,255,.28); }
    .pv .pv-close { top:14px; right:14px; width:auto; padding:0 18px; font-size:15px; gap:8px; }
    .pv .pv-prev { left:12px; top:50%; transform:translateY(-50%); }
    .pv .pv-next { right:12px; top:50%; transform:translateY(-50%); }
    .pv .pv-count { position:absolute; bottom:16px; left:0; right:0; text-align:center; color:#cfd0d4; font:13px system-ui, sans-serif; }
</style>
<div class="pv" id="photo-viewer" role="dialog" aria-modal="true" aria-label="Vista ampliada de la foto">
    <button type="button" class="pv-close" aria-label="Cerrar">✕ Cerrar</button>
    <button type="button" class="pv-prev" aria-label="Foto anterior">‹</button>
    <img id="photo-viewer-img" src="" alt="Foto ampliada">
    <button type="button" class="pv-next" aria-label="Foto siguiente">›</button>
    <div class="pv-count" id="photo-viewer-count"></div>
</div>
<script>
    (function () {
        const box = document.getElementById('photo-viewer');
        const img = document.getElementById('photo-viewer-img');
        const count = document.getElementById('photo-viewer-count');
        let group = [], pos = 0;
        function show() {
            img.src = group[pos].href;
            count.textContent = (pos + 1) + ' / ' + group.length;
            box.querySelector('.pv-prev').style.display = box.querySelector('.pv-next').style.display = group.length > 1 ? 'flex' : 'none';
        }
        function open(link) {
            group = Array.from(document.querySelectorAll('a[data-viewer="' + link.dataset.viewer + '"]'));
            pos = Math.max(0, group.indexOf(link));
            show();
            box.classList.add('is-open');
        }
        function close() { box.classList.remove('is-open'); img.src = ''; }
        function step(d) { pos = (pos + d + group.length) % group.length; show(); }
        document.querySelectorAll('a[data-viewer]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); open(a); }));
        box.querySelector('.pv-close').addEventListener('click', close);
        box.querySelector('.pv-prev').addEventListener('click', () => step(-1));
        box.querySelector('.pv-next').addEventListener('click', () => step(1));
        box.addEventListener('click', (e) => { if (e.target === box) close(); });
        document.addEventListener('keydown', (e) => {
            if (!box.classList.contains('is-open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') step(-1);
            if (e.key === 'ArrowRight') step(1);
        });
    })();
</script>
