{{-- Piezas por vencer: visible en todas las pantallas de recepción. Suena cada minuto hasta confirmar "Avisado". --}}
@include('partials.hh-sound')
<style>
    #hh-ending-bar { position:fixed; top:8px; left:50%; transform:translateX(-50%); z-index:9990; display:flex; flex-direction:column; gap:6px; width:min(720px, calc(100vw - 24px)); }
    #hh-ending-bar[hidden] { display:none; }
    .hh-ending-row { display:flex; align-items:center; gap:10px; padding:10px 12px 10px 16px; border-radius:12px; background:#0a5cff; color:#fff; font-size:16px; font-weight:800; box-shadow:0 10px 28px rgba(0,0,0,.4); }
    .hh-ending-row.late { background:#ffc233; color:#3a2a00; }
    .hh-ending-row .hh-ending-text { flex:1 1 auto; min-width:0; }
    .hh-ending-row small { display:block; font-size:12.5px; font-weight:600; }
    .hh-ending-row a, .hh-ending-row button { flex:none; width:auto; margin:0; padding:8px 12px; border-radius:9px; font:inherit; font-size:13.5px; font-weight:800; cursor:pointer; text-decoration:none; }
    .hh-ending-row a { background:transparent; color:inherit; border:1.5px solid currentColor; }
    .hh-ending-row button { background:#fff; color:#0a3fb0; border:none; }
    .hh-ending-row.late button { background:#3a2a00; color:#fff; }
</style>
<div id="hh-ending-bar" role="alert" hidden></div>
<script>
    (function () {
        window.hhEndingBar = true;
        const bar = document.getElementById('hh-ending-bar');
        const url = @json(route('rooms.ending_soon'));
        const KEY = 'hh-ending-ack';
        const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const acked = () => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } };
        // Confirmar "por salir" no apaga el aviso de atraso: al pasarse la hora vuelve en rojo.
        const stage = (stay) => stay.code + (stay.minutes < 0 ? ':atraso' : ':salida');
        let showing = [];
        let lastBeep = 0;

        function beep() {
            lastBeep = Date.now();
            window.hhSound((ctx) => [0, 0.35].forEach((at) => {
                const osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.frequency.value = 880; gain.gain.value = 0.6;
                osc.connect(gain); gain.connect(ctx.destination);
                osc.start(ctx.currentTime + at); osc.stop(ctx.currentTime + at + 0.2);
            }));
        }

        function render() {
            bar.innerHTML = showing.map((stay) => '<div class="hh-ending-row' + (stay.minutes < 0 ? ' late' : '') + '">'
                + '<div class="hh-ending-text">⏰ ' + esc(stay.room) + (stay.minutes < 0 ? ' pasó su hora hace ' : ' sale en ') + esc(stay.time)
                + '<small>' + (stay.minutes < 0 ? 'Avisar a la pieza o cobrar el tiempo extra' : 'Avisar a la pieza u ofrecer +1 hora') + '</small></div>'
                + '<a href="' + esc(stay.url) + '">Ver</a>'
                + '<button type="button" data-ack="' + esc(stage(stay)) + '">✓ Avisado</button></div>').join('');
            bar.hidden = showing.length === 0;
        }

        bar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-ack]');
            if (!button) return;
            try { localStorage.setItem(KEY, JSON.stringify(acked().concat(button.dataset.ack).slice(-200))); } catch (e) { /* sin almacenamiento */ }
            showing = showing.filter((stay) => stage(stay) !== button.dataset.ack);
            render();
        });

        async function poll() {
            try {
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const done = acked();
                const before = showing.map(stage);
                showing = (await response.json()).filter((stay) => !done.includes(stage(stay)));
                render();
                const isNew = showing.some((stay) => !before.includes(stage(stay)));
                if (showing.length && (isNew || Date.now() - lastBeep >= 58000)) beep();
            } catch (e) { /* sin red: se reintenta */ }
        }
        poll();
        setInterval(poll, 20000);
    })();
</script>
