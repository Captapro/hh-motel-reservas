{{-- Avisos de la administración: se abren encima de todo hasta que la persona confirma. --}}
<style>
    .hh-notice { position:fixed; inset:0; z-index:99999; display:flex; align-items:center; justify-content:center; padding:18px; background:rgba(0,0,0,.72); font-family:-apple-system,'Segoe UI',sans-serif; }
    .hh-notice-card { width:100%; max-width:440px; overflow:hidden; border-radius:18px; background:#fff; color:#1f2937; box-shadow:0 12px 40px rgba(0,0,0,.4); }
    .hh-notice-head { padding:12px 16px; background:#111; color:#ffb379; font-size:14px; font-weight:800; }
    .hh-notice-card.urgent .hh-notice-head { background:#ff7918; color:#21170e; font-size:16px; }
    .hh-notice-body { padding:16px; font-size:17px; line-height:1.4; white-space:pre-wrap; overflow-wrap:anywhere; }
    .hh-notice-meta { padding:0 16px 12px; color:#6b7280; font-size:12.5px; }
    .hh-notice-ok { display:block; width:calc(100% - 32px); min-height:50px; margin:0 16px 16px; border:none; border-radius:11px; background:#ff7918; color:#21170e; font-size:16px; font-weight:800; cursor:pointer; }
</style>
<script>
    (function () {
        const token = @json(csrf_token());
        const pendingUrl = @json(route('staff_notices.pending'));
        const readUrl = @json(route('staff_notices.read', ['notice' => '__ID__']));
        let open = false;

        const beep = function () {
            try {
                const audio = new (window.AudioContext || window.webkitAudioContext)();
                [0, .28, .56].forEach(function (delay) {
                    const osc = audio.createOscillator();
                    const gain = audio.createGain();
                    osc.frequency.value = 880;
                    gain.gain.value = .25;
                    osc.connect(gain); gain.connect(audio.destination);
                    osc.start(audio.currentTime + delay);
                    osc.stop(audio.currentTime + delay + .18);
                });
            } catch (e) {}
        };

        const show = function (notice) {
            open = true;
            const overlay = document.createElement('div');
            overlay.className = 'hh-notice';
            overlay.setAttribute('role', 'alertdialog');
            const card = document.createElement('div');
            card.className = 'hh-notice-card' + (notice.urgent ? ' urgent' : '');
            const add = function (cls, text, tag) {
                const el = document.createElement(tag || 'div');
                el.className = cls;
                el.textContent = text;
                card.appendChild(el);
                return el;
            };
            add('hh-notice-head', notice.urgent ? 'AVISO URGENTE' : 'Aviso de administración');
            add('hh-notice-body', notice.body);
            add('hh-notice-meta', (notice.sender || 'Administración') + ' · ' + notice.at);
            const ok = add('hh-notice-ok', 'Entendido', 'button');
            ok.type = 'button';
            ok.addEventListener('click', function () {
                ok.disabled = true;
                fetch(readUrl.replace('__ID__', notice.id), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                }).finally(function () {
                    overlay.remove();
                    open = false;
                    check();
                });
            });
            overlay.appendChild(card);
            document.body.appendChild(overlay);
            if (notice.urgent) beep();
        };

        const check = function () {
            if (open) return;
            fetch(pendingUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : []; })
                .then(function (notices) { if (notices.length && !open) show(notices[0]); })
                .catch(function () {});
        };

        check();
        setInterval(check, 30000);
    })();
</script>
