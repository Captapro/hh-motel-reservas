<script>
        // Un solo audio para toda la página. El navegador lo deja mudo hasta el primer
        // toque o tecla en la página: por eso el tablero se actualiza sin recargar
        // y lo que no pudo sonar queda en cola.
        (function () {
            if (window.hhSound) return;
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            const queue = [];
            let ctx = null;
            const running = () => !!ctx && ctx.state === 'running';
            const hint = () => {
                const el = document.getElementById('hh-sound-hint');
                if (el) el.hidden = !AudioCtx || running();
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
                try { context().resume().then(flush).catch(() => {}); } catch (e) { /* sin audio */ }
            };
            window.hhSound = function (play) {
                if (!AudioCtx) return;
                try {
                    if (context().state === 'running') { play(ctx); return; }
                    if (queue.length < 6) queue.push(play);
                    wake();
                    hint();
                } catch (e) { /* sin audio: queda el aviso en pantalla */ }
            };
            document.addEventListener('pointerdown', wake, true);
            document.addEventListener('keydown', wake, true);
            document.addEventListener('DOMContentLoaded', () => { wake(); setTimeout(hint, 400); });
        })();
</script>
