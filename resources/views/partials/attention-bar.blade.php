{{-- Contactos que Cata traspasó a una persona: visibles en toda pantalla hasta marcarlos atendidos. --}}
@include('partials.hh-sound')
<style>
    #hh-attention-bar { position:fixed; bottom:14px; left:50%; transform:translateX(-50%); z-index:9989; display:flex; flex-direction:column; gap:6px; width:min(640px, calc(100vw - 24px)); }
    #hh-attention-bar[hidden] { display:none; }
    .hh-attention-row { display:flex; align-items:center; gap:10px; padding:10px 12px 10px 16px; border-radius:12px; background:#111827; color:#fff; font-size:15px; font-weight:800; box-shadow:0 8px 24px rgba(0,0,0,.35); border:2px solid #ffc233; }
    .hh-attention-row.is-urgent { border-color:#0a5cff; background:#0a5cff; }
    .hh-attention-row.is-urgent a { background:#fff; color:#0a3fb0; }
    .hh-attention-row.is-urgent small { color:#dbe7ff; }
    .hh-attention-row .hh-attention-text { flex:1 1 auto; min-width:0; overflow-wrap:anywhere; }
    .hh-attention-row small { display:block; font-size:12.5px; font-weight:600; color:#d1d5db; }
    .hh-attention-row a, .hh-attention-row button { flex:none; width:auto; margin:0; padding:8px 12px; border-radius:9px; font:inherit; font-size:13.5px; font-weight:800; cursor:pointer; text-decoration:none; }
    .hh-attention-row a { background:#ffc233; color:#3a2a00; }
    .hh-attention-row button { background:transparent; color:#fff; border:1.5px solid #fff; }
    @media (max-width:600px) { .hh-attention-row { flex-wrap:wrap; } .hh-attention-row .hh-attention-text { flex-basis:100%; } }
</style>
<div id="hh-attention-bar" role="alert" hidden></div>
<script>
    (function () {
        const bar = document.getElementById('hh-attention-bar');
        const url = @json(route('attention.pending'));
        const token = @json(csrf_token());
        const esc = (t) => String(t ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        // En Conversaciones los mensajes ya están en pantalla: ahí solo suena.
        const onInbox = location.pathname.startsWith('/admin/conversaciones');
        const isHandoff = (item) => item.kind !== 'inbox';
        const announcedKey = 'hh-inbox-announced';
        const announced = () => { try { return JSON.parse(sessionStorage.getItem(announcedKey)) || []; } catch (e) { return []; } };
        const remember = (ids) => { try { sessionStorage.setItem(announcedKey, JSON.stringify(ids)); } catch (e) { /* sin memoria: puede volver a sonar */ } };
        let showing = [];
        let lastBeep = 0;

        function beep() {
            lastBeep = Date.now();
            window.hhSound((ctx) => [0, 0.2, 0.4].forEach((at) => {
                const osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.frequency.value = 660; gain.gain.value = 0.6;
                osc.connect(gain); gain.connect(ctx.destination);
                osc.start(ctx.currentTime + at); osc.stop(ctx.currentTime + at + 0.12);
            }));
        }

        function render() {
            bar.innerHTML = showing.map((item) => isHandoff(item) ? handoffRow(item) : inboxRow(item)).join('');
            bar.hidden = showing.length === 0;
        }

        function inboxRow(item) {
            return '<div class="hh-attention-row' + (item.urgent ? ' is-urgent' : '') + '"><div class="hh-attention-text">' + (item.urgent ? '🛎️ ' : '💬 ') + esc(item.title)
                + (item.detail ? '<small>' + esc(item.detail) + '</small>' : '') + '</div>'
                + '<a href="' + esc(item.conversation) + '">Abrir conversación</a></div>';
        }

        function handoffRow(item) {
            return '<div class="hh-attention-row">'
                + '<div class="hh-attention-text">💬 ' + esc(item.name) + ' requiere atención'
                + '<small>' + esc(item.phone) + ' · ' + esc(item.since) + (item.reason ? ' · ' + esc(item.reason) : '') + '</small></div>'
                + (item.conversation
                    ? '<a href="' + esc(item.conversation) + '">Abrir conversación</a>'
                    : '<a href="' + esc(item.whatsapp) + '" target="hh-whatsapp" rel="noopener">Abrir WhatsApp</a>')
                + '<button type="button" data-copy="' + esc(item.phone) + '">Copiar número</button>'
                + '<button type="button" data-attend="' + esc(item.attend_url) + '">✓ Atendido</button></div>';
        }

        bar.addEventListener('click', async (event) => {
            const copy = event.target.closest('[data-copy]');
            if (copy) {
                try {
                    await navigator.clipboard.writeText(copy.dataset.copy);
                    copy.textContent = '✓ Copiado';
                } catch (e) {
                    copy.textContent = copy.dataset.copy;
                }
                return;
            }
            const button = event.target.closest('[data-attend]');
            if (!button) return;
            button.disabled = true;
            try {
                const response = await fetch(button.dataset.attend, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
                if (response.ok) showing = showing.filter((item) => item.attend_url !== button.dataset.attend);
            } catch (e) { /* sin red: sigue visible */ }
            button.disabled = false;
            render();
        });

        async function poll() {
            try {
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const before = showing.filter(isHandoff).map((item) => item.id);
                const all = await response.json();
                const next = onInbox ? all.filter(isHandoff) : all;
                if (JSON.stringify(next) !== JSON.stringify(showing)) {
                    showing = next;
                    render();
                }
                const handoffs = all.filter(isHandoff);
                const waiting = all.filter((item) => !isHandoff(item)).map((item) => item.id);
                const seen = announced();
                const isNew = handoffs.some((item) => !before.includes(item.id)) || waiting.some((id) => !seen.includes(id));
                remember(waiting);
                // Traspasos y clientes en la habitación insisten cada minuto; los demás suenan una vez.
                const insists = handoffs.length || all.some((item) => item.urgent);
                if (isNew || (insists && Date.now() - lastBeep >= 58000)) beep();
            } catch (e) { /* sin red: se reintenta */ }
        }
        poll();
        setInterval(poll, 20000);
    })();
</script>
