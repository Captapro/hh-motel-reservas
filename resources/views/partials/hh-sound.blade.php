<script>
        // Un solo audio para toda la página. El navegador lo deja mudo hasta el primer
        // toque o tecla en la página: por eso el tablero se actualiza sin recargar
        // y lo que no pudo sonar queda en cola.
        (function () {
            if (window.hhSound) return;
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            const queue = [];
            let ctx = null;
            const OFF_KEY = 'hh-sound-off-until';
            const OFF_MINUTES = 60;
            let wokeAt = 0;
            const running = () => !!ctx && ctx.state === 'running';
            const offUntil = () => { try { return Number(localStorage.getItem(OFF_KEY) || 0); } catch (e) { return 0; } };
            const muted = () => offUntil() > Date.now();
            // Un solo botón muestra el estado: activas, silenciadas o bloqueadas por el navegador.
            const hint = () => {
                const el = document.getElementById('hh-sound-btn');
                if (!el) return;
                const state = !AudioCtx ? 'off' : muted() ? 'off' : running() ? 'on' : 'blocked';
                el.dataset.state = state;
                el.textContent = '🔊';
                el.title = { on: 'Sonido activado. Toca para silenciar', off: 'Sonido silenciado: vuelve solo en una hora, o toca para activarlo', blocked: 'Sonido sin activar. Toca para activarlo' }[state];
                el.setAttribute('aria-label', el.title);
            };
            const flush = () => {
                while (running() && queue.length) queue.shift()(ctx);
                hint();
            };
            const context = () => {
                if (!ctx) {
                    ctx = new AudioCtx();
                    ctx.onstatechange = flush;
                }
                return ctx;
            };
            const wake = () => {
                if (!AudioCtx) return;
                if (!running()) wokeAt = Date.now();
                try { context().resume().then(flush).catch(() => {}); } catch (e) { /* sin audio */ }
            };
            window.hhSound = function (play) {
                if (!AudioCtx || muted()) return;
                try {
                    if (context().state === 'running') { play(ctx); return; }
                    if (queue.length < 6) queue.push(play);
                    wake();
                    hint();
                } catch (e) { /* sin audio: queda el aviso en pantalla */ }
            };
            document.addEventListener('pointerdown', wake, true);
            document.addEventListener('keydown', wake, true);
            document.addEventListener('click', (event) => {
                if (!event.target.closest || !event.target.closest('#hh-sound-btn')) return;
                // El primer toque solo desbloquea; los siguientes silencian o activan.
                if (Date.now() - wokeAt > 800) {
                    try { localStorage.setItem(OFF_KEY, muted() ? '0' : String(Date.now() + OFF_MINUTES * 60000)); } catch (e) { /* sin almacenamiento */ }
                }
                hint();
            });
            document.addEventListener('DOMContentLoaded', () => { wake(); setTimeout(hint, 400); });
            setInterval(hint, 30000);
        })();
</script>
