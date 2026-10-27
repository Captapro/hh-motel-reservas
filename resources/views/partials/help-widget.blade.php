{{-- Botón "Ayuda" del equipo: chat con el asistente que explica cómo usar el sistema. Solo para quien esté autorizado en Personal. --}}
@if (auth()->user()?->canUseAi('ayuda'))
<style>
    /* Algunas pantallas tienen estilos generales para todos los botones,
       campos o íconos (ancho 100%, márgenes, mayúsculas). El botón y el chat
       de Ayuda fijan sus propias medidas para verse igual en todas. */
    #hh-help-btn, #hh-help-panel button, #hh-help-panel textarea {
        width:auto !important; min-width:0 !important; max-width:none !important; height:auto; margin:0 !important;
        box-sizing:border-box !important; text-transform:none !important; letter-spacing:normal !important; line-height:1.25 !important; flex:none;
    }
    #hh-help-btn { left:auto !important; top:auto !important; right:18px !important; bottom:18px !important; display:flex !important; }
    #hh-help-btn svg { width:17px !important; height:17px !important; flex:none; }
    #hh-help-panel .hh-help-tips button { width:100% !important; }
    #hh-help-panel .hh-help-form textarea { flex:1 1 auto !important; width:auto !important; height:42px !important; }
    #hh-help-panel .hh-help-form button { height:42px !important; }
    .hh-help-btn { position:fixed; right:18px; bottom:18px; z-index:900; background:#ff7918; color:#fff; border:0; border-radius:24px; padding:10px 16px; font:600 14px -apple-system, "Segoe UI", sans-serif; box-shadow:0 6px 18px rgba(0,0,0,.25); cursor:pointer; display:flex; align-items:center; gap:6px; }
    .hh-help-btn:hover { background:#e86a0e; }
    .hh-help-panel { position:fixed; right:18px; bottom:72px; z-index:901; width:380px; max-width:calc(100vw - 32px); height:560px; max-height:calc(100vh - 100px); background:#fff; color:#1f2937; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 18px 44px rgba(0,0,0,.3); display:flex; flex-direction:column; overflow:hidden; font-family:-apple-system, "Segoe UI", sans-serif; }
    .hh-help-panel[hidden] { display:none; }
    .hh-help-head { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid #eee; }
    .hh-help-head b { font-size:15px; color:#111827; }
    .hh-help-head small { display:block; color:#6b7280; font-size:11.5px; font-weight:400; }
    .hh-help-head button { background:none; border:0; font-size:13px; color:#6b7280; cursor:pointer; padding:4px 6px; }
    .hh-help-log { flex:1; overflow-y:auto; padding:12px 14px; display:flex; flex-direction:column; gap:10px; font-size:14px; line-height:1.5; }
    .hh-help-q { align-self:flex-end; max-width:85%; background:#ff7918; color:#fff; padding:8px 12px; border-radius:12px 12px 4px 12px; white-space:pre-wrap; }
    .hh-help-a { align-self:flex-start; max-width:95%; background:#f3f4f6; color:#1f2937; padding:8px 12px; border-radius:12px 12px 12px 4px; }
    .hh-help-a p { margin:0 0 6px; } .hh-help-a p:last-child { margin:0; }
    .hh-help-a ol, .hh-help-a ul { margin:0 0 6px; padding-left:20px; }
    .hh-help-a.err { background:#fdecee; color:#9b1c2c; }
    .hh-help-tips { display:flex; flex-direction:column; gap:6px; }
    .hh-help-tips button { text-align:left; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:7px 10px; font-size:13px; color:#374151; cursor:pointer; }
    .hh-help-tips button:hover { border-color:#ff7918; }
    .hh-help-form { display:flex; gap:8px; padding:10px; border-top:1px solid #eee; }
    .hh-help-form textarea { flex:1; resize:none; height:42px; border:1px solid #d1d5db !important; background:#fff !important; color:#111 !important; border-radius:10px; padding:10px; font:14px -apple-system, "Segoe UI", sans-serif; }
    .hh-help-form button { background:#ff7918; color:#fff; border:0; border-radius:10px; padding:0 14px; font-weight:600; cursor:pointer; }
    .hh-help-form button:disabled { opacity:.5; cursor:default; }
    .hh-help-dots::after { content:'…'; animation:hh-help-dots 1s steps(3) infinite; }
    @keyframes hh-help-dots { 0% { content:'.'; } 33% { content:'..'; } 66% { content:'...'; } }
    @media print { .hh-help-btn, .hh-help-panel { display:none !important; } }
</style>
<button type="button" class="hh-help-btn" id="hh-help-btn" aria-expanded="false" aria-controls="hh-help-panel">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
    Ayuda
</button>
<div class="hh-help-panel" id="hh-help-panel" role="dialog" aria-label="Asistente de ayuda" hidden>
    <div class="hh-help-head">
        <div><b>Asistente de ayuda</b><small>Pregunta cómo hacer algo en el sistema</small></div>
        <div><button type="button" id="hh-help-clear" title="Empezar de nuevo">Limpiar</button><button type="button" id="hh-help-close" aria-label="Cerrar">✕</button></div>
    </div>
    <div class="hh-help-log" id="hh-help-log"></div>
    <form class="hh-help-form" id="hh-help-form">
        <textarea id="hh-help-input" maxlength="1000" placeholder="Ej.: ¿cómo cobro una hora extra?"></textarea>
        <button type="submit" id="hh-help-send">Enviar</button>
    </form>
</div>
<script>
(function () {
    const btn = document.getElementById('hh-help-btn');
    const panel = document.getElementById('hh-help-panel');
    const log = document.getElementById('hh-help-log');
    const form = document.getElementById('hh-help-form');
    const input = document.getElementById('hh-help-input');
    const send = document.getElementById('hh-help-send');
    const url = @json(route('help.ask'));
    const token = @json(csrf_token());
    const page = @json(trim(request()->route()?->getName() ?? request()->path()));
    const KEY = 'hh-help-chat';
    const tips = ['¿Cómo hago el check-in de un cliente con reserva?', '¿Cómo cobro una hora extra?', '¿Cómo registro un pago con tarjeta?', '¿Cómo finalizo una estadía y paso la habitación a aseo?'];
    let history = [];
    try { history = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { history = []; }

    function save() { try { sessionStorage.setItem(KEY, JSON.stringify(history.slice(-12))); } catch (e) {} }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function paint() {
        log.innerHTML = '';
        if (!history.length) {
            log.insertAdjacentHTML('beforeend', '<div class="hh-help-a">Hola. Pregúntame cómo hacer cualquier cosa en el sistema y te explico paso a paso.</div><div class="hh-help-tips">' + tips.map(t => '<button type="button">' + esc(t) + '</button>').join('') + '</div>');
            log.querySelectorAll('.hh-help-tips button').forEach(b => b.addEventListener('click', () => ask(b.textContent)));
        }
        history.forEach(t => log.insertAdjacentHTML('beforeend', '<div class="hh-help-q">' + esc(t.q) + '</div><div class="hh-help-a">' + t.html + '</div>'));
        log.scrollTop = log.scrollHeight;
    }

    function toggle(open) {
        panel.hidden = !open;
        window.hhHoldRefresh = open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { paint(); input.focus(); }
    }

    function ask(q) {
        q = q.trim();
        if (!q || send.disabled) return;
        const tipsBox = log.querySelector('.hh-help-tips'); if (tipsBox) tipsBox.remove();
        log.insertAdjacentHTML('beforeend', '<div class="hh-help-q">' + esc(q) + '</div><div class="hh-help-a"><span class="hh-help-dots">Buscando la respuesta</span></div>');
        const box = log.lastElementChild;
        log.scrollTop = log.scrollHeight;
        input.value = '';
        send.disabled = true;
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ question: q, page: page + ' — ' + document.title, history: history.map(t => ({ q: t.q, a: t.a })).slice(-6) }),
        }).then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok || data.status !== 'listo') {
                box.classList.add('err');
                box.textContent = data.answer || data.message || 'No se pudo responder. Intenta de nuevo.';
                return;
            }
            box.innerHTML = data.answer_html;
            history.push({ q, a: data.answer, html: data.answer_html });
            save();
        }).catch(() => { box.classList.add('err'); box.textContent = 'Sin conexión. Intenta de nuevo.'; })
          .finally(() => { send.disabled = false; log.scrollTop = log.scrollHeight; });
    }

    btn.addEventListener('click', () => toggle(panel.hidden));
    document.getElementById('hh-help-close').addEventListener('click', () => toggle(false));
    document.getElementById('hh-help-clear').addEventListener('click', () => { history = []; save(); paint(); });
    form.addEventListener('submit', e => { e.preventDefault(); ask(input.value); });
    input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(input.value); } });
})();
</script>
@endif
