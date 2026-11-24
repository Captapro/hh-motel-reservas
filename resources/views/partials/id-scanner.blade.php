{{--
    Lector de carnet USB: funciona como teclado y escribe el QR del carnet
    chileno donde esté el cursor. Se reconoce por el contenido (no importa
    qué campo tenga el foco ni la distribución del teclado del lector, que
    cambia ":" "/" "=" por otros signos) y se avisa con el evento hh:id-scan.
    El QR no trae el nombre: solo RUN, nacimiento y vencimiento. El QR del pase
    de una reserva también se reconoce, en cualquier pantalla, y abre el ingreso.
--}}
<div id="id-scan-toast" hidden style="position:fixed;top:16px;right:16px;left:16px;margin-left:auto;max-width:420px;z-index:9999;padding:12px 16px;border-radius:10px;font:14px/1.45 -apple-system,'Segoe UI',sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.5);cursor:pointer;"></div>
<script>
    window.hhIdScan = (function () {
        const GAP_MS = 200; // más que esto entre teclas ya no es el lector

        function rutDv(body) {
            let sum = 0, mul = 2;
            for (let i = body.length - 1; i >= 0; i--) { sum += Number(body[i]) * mul; mul = mul === 7 ? 2 : mul + 1; }
            const r = 11 - (sum % 11);
            return r === 11 ? '0' : (r === 10 ? 'K' : String(r));
        }
        // Dígito de control de la zona legible del documento (pesos 7-3-1).
        function mrzDigit(text) {
            const w = [7, 3, 1];
            return String([...text].reduce((s, c, i) => s + Number(c) * w[i % 3], 0) % 10);
        }
        function looksLikeCard(raw) { return /docstatus|sidiv/i.test(raw) && /RUN/i.test(raw); }
        function passCode(raw) {
            const m = /reservas[^a-z0-9]{0,2}HH[^0-9]{0,2}(\d{8})[^0-9]{0,2}(\d{5})[^a-z0-9]{0,2}validar/i.exec(raw);
            return m ? 'HH-' + m[1] + '-' + m[2] : null;
        }

        function parse(raw) {
            const run = /RUN[^0-9]{0,3}(\d{6,8})[^0-9kK]{0,3}([0-9kK])/i.exec(raw);
            if (!run || rutDv(run[1]) !== run[2].toUpperCase()) return null;
            const out = {
                rut: run[1] + '-' + run[2].toUpperCase(),
                foreigner: /CEDULA.{0,2}EXT/i.test(raw),
                birthDate: null,
                expiresOn: null,
            };
            const mrz = /mrz[^0-9]{0,3}(\d{24})/i.exec(raw);
            if (mrz) {
                const m = mrz[1], birth = m.slice(10, 16), expiry = m.slice(17, 23);
                const iso = (yymmdd, century) => century + yymmdd.slice(0, 2) + '-' + yymmdd.slice(2, 4) + '-' + yymmdd.slice(4, 6);
                if (mrzDigit(birth) === m[16]) {
                    const thisYear = new Date().getFullYear() % 100;
                    out.birthDate = iso(birth, Number(birth.slice(0, 2)) > thisYear ? '19' : '20');
                }
                if (mrzDigit(expiry) === m[23]) out.expiresOn = iso(expiry, '20');
            }
            return out;
        }

        function today() {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Santiago' }).format(new Date());
        }
        function age(birthDate) {
            const t = today();
            const years = Number(t.slice(0, 4)) - Number(birthDate.slice(0, 4));
            return t.slice(5) < birthDate.slice(5) ? years - 1 : years;
        }

        let hideTimer = null;
        function toast(kind, html) {
            const el = document.getElementById('id-scan-toast');
            const colors = { ok: ['#2a1a0c', '#ffb379'], warn: ['#3a331c', '#e8c76f'], bad: ['#4a1c1c', '#ffb4b4'] }[kind];
            el.style.background = colors[0];
            el.style.color = colors[1];
            el.style.border = '1px solid ' + colors[1];
            el.innerHTML = html;
            el.hidden = false;
            clearTimeout(hideTimer);
            // Lo que hay que mirar queda hasta que recepción lo cierre.
            if (kind === 'ok') hideTimer = setTimeout(() => { el.hidden = true; }, 5000);
        }

        function report(card, extra) {
            const lines = [];
            let kind = 'ok';
            const years = card.birthDate ? age(card.birthDate) : null;
            if (years !== null && years < 18) { kind = 'bad'; lines.push('<b>⛔ Menor de edad: ' + years + ' años</b>'); }
            if (card.expiresOn && card.expiresOn < today()) {
                if (kind === 'ok') kind = 'warn';
                lines.push('<b>⚠ Carnet vencido</b> el ' + card.expiresOn.split('-').reverse().join('/'));
            }
            if (card.foreigner) {
                if (kind === 'ok') kind = 'warn';
                lines.push('Carnet de extranjero: revisa la nacionalidad.');
            }
            if (card.fromPhoto) {
                if (kind === 'ok') kind = 'warn';
                lines.push('📷 Leído por foto: <b>revisa el RUT y los nombres</b> antes de guardar.');
            }
            if (!lines.length) lines.push('✓ Carnet leído' + (years !== null ? ' · ' + years + ' años' : ''));
            if (extra) lines.push(extra);
            toast(kind, lines.join('<br>'));
        }

        // Quién es por el RUT: ficha de cliente o nombre de una visita anterior.
        async function lookup(rut) {
            try {
                const r = await fetch('{{ route('customers.lookup') }}?scan=1&rut=' + encodeURIComponent(rut), { headers: { 'Accept': 'application/json' } });
                return r.ok ? await r.json() : null;
            } catch (e) { return null; }
        }

        // Completa la primera fila libre de acompañantes. addRow la crea si
        // la pantalla permite sumar personas; si no, devuelve false.
        function addGuest(card, person, addRow) {
            const rows = () => [...document.querySelectorAll('#guest-rows .guest-row')];
            const docOf = row => row.querySelector('.guest-doc').value.replace(/[^0-9kK]/g, '').toUpperCase();
            const rut = card.rut.replace('-', '');
            if (rows().some(row => docOf(row) === rut)) {
                report(card, 'Este acompañante ya está registrado.');
                return;
            }
            const free = () => rows().find(row => !row.querySelector('.guest-first').value.trim() && !docOf(row));
            let row = free();
            if (!row && addRow) { addRow(); row = free(); }
            if (!row) {
                report(card, 'Ya están registradas todas las personas de esta reserva.');
                return;
            }
            if (card.firstName) person = { first_name: card.firstName, last_name: card.lastName || '' };
            const first = row.querySelector('.guest-first');
            first.value = (person && person.first_name) || '';
            row.querySelector('.guest-last').value = (person && person.last_name) || '';
            const doc = row.querySelector('.guest-doc');
            doc.value = card.rut;
            doc.dispatchEvent(new Event('input', { bubbles: true }));
            if (first.value) {
                report(card, 'Acompañante agregado: ' + first.value.replace(/</g, '&lt;') + '.');
            } else {
                first.focus();
                report(card, 'Acompañante nuevo: escribe nombre y apellido.');
            }
        }

        // RUT de acompañante escrito a mano: trae solo el nombre de esa persona, nunca su historial.
        let guestTimer = null;
        document.addEventListener('input', function (e) {
            const doc = e.target;
            if (!doc.matches || !doc.matches('.guest-doc') || !e.isTrusted) return;
            clearTimeout(guestTimer);
            const clean = doc.value.replace(/[^0-9kK]/g, '').toUpperCase();
            if (clean.length < 7 || rutDv(clean.slice(0, -1)) !== clean.slice(-1)) return;
            guestTimer = setTimeout(async function () {
                const person = await fetch('{{ route('customers.lookup') }}?rut=' + encodeURIComponent(clean.slice(0, -1) + '-' + clean.slice(-1)), { headers: { 'Accept': 'application/json' } })
                    .then(r => (r.ok ? r.json() : null)).catch(() => null);
                const row = doc.closest('.guest-row');
                const first = row && row.querySelector('.guest-first');
                if (!person || !person.first_name || !first || first.value.trim()) return;
                first.value = person.first_name;
                row.querySelector('.guest-last').value = person.last_name || '';
                first.dispatchEvent(new Event('input', { bubbles: true }));
            }, 400);
        });

        let buffer = '', lastKey = 0, target = null, targetValue = null, timer = null, swallowUntil = 0;

        function finish() {
            clearTimeout(timer);
            const raw = buffer;
            buffer = '';
            const code = passCode(raw);
            if (!code && !looksLikeCard(raw)) return false;
            // Lo que el lector alcanzó a escribir en el campo con foco no sirve.
            if (target && targetValue !== null) {
                target.value = targetValue;
                target.dispatchEvent(new Event('input', { bubbles: true }));
            }
            swallowUntil = Date.now() + 500;
            if (code) {
                window.location.href = '/reservas/' + code + '/validar?ir=1';
                return true;
            }
            const card = parse(raw);
            if (!card) {
                toast('bad', 'No se pudo leer el carnet. Pásalo de nuevo o escribe el RUT a mano.');
                return true;
            }
            document.dispatchEvent(new CustomEvent('hh:id-scan', { detail: card }));
            return true;
        }

        document.addEventListener('keydown', function (e) {
            const now = Date.now();
            if (e.key === 'Enter' || e.key === 'Tab') {
                if (finish() || now < swallowUntil) { e.preventDefault(); e.stopPropagation(); }
                return;
            }
            if (e.key.length !== 1 || e.ctrlKey || e.metaKey) return;
            if (now - lastKey > GAP_MS) {
                buffer = '';
                target = document.activeElement;
                targetValue = target && 'value' in target ? target.value : null;
            }
            lastKey = now;
            buffer += e.key;
            clearTimeout(timer);
            timer = setTimeout(finish, GAP_MS);
        }, true);

        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#id-scan-toast')) document.getElementById('id-scan-toast').hidden = true;
        });

        // ---- Carnet por cámara (piloto): escáner en vivo, celular o tablet, sin instalar nada ----
        // Se abre la cámara a pantalla completa; cada toque en «Escanear» lee un carnet y lo manda al formulario
        // (el primero al titular y los siguientes a los acompañantes). La cámara sigue abierta hasta tocar «Listo».
        // La foto no se guarda: se reduce en el aparato, se lee y se descarta.
        function shrinkSource(source, w, h, maxSide) {
            const scale = Math.min(1, maxSide / Math.max(w, h));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(w * scale);
            canvas.height = Math.round(h * scale);
            canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);
            return new Promise((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('imagen'))), 'image/jpeg', 0.85));
        }

        function shrinkFile(file, maxSide) {
            return new Promise(function (resolve, reject) {
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = function () { URL.revokeObjectURL(url); shrinkSource(img, img.width, img.height, maxSide).then(resolve, reject); };
                img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('imagen')); };
                img.src = url;
            });
        }

        // Manda la imagen a leer. Devuelve { ok, ...datos } o { ok: false, error }.
        async function readBlob(blob) {
            try {
                const form = new FormData();
                form.append('photo', blob, 'carnet.jpg');
                form.append('_token', '{{ csrf_token() }}');
                const r = await fetch('{{ route('customers.id_photo') }}', { method: 'POST', body: form, headers: { 'Accept': 'application/json' } });
                const data = await r.json().catch(() => null);
                if (!r.ok || !data || !data.ok) {
                    return { ok: false, error: (data && (data.error || data.message)) || 'No se pudo leer el carnet. Intenta de nuevo con más luz.' };
                }
                return data;
            } catch (e) {
                return { ok: false, error: 'No se pudo leer el carnet. Intenta de nuevo.' };
            }
        }

        function sendToForm(data) {
            document.dispatchEvent(new CustomEvent('hh:id-scan', { detail: {
                rut: data.rut, foreigner: !!data.foreigner, birthDate: data.birth_date, expiresOn: data.expires_on,
                firstName: data.first_name, lastName: data.last_name, nationality: data.nationality, fromPhoto: true,
            } }));
        }

        // Respaldo si el aparato no deja abrir la cámara en vivo: elegir o sacar una foto.
        function pickPhoto() {
            const input = document.createElement('input');
            input.type = 'file'; input.accept = 'image/*'; input.setAttribute('capture', 'environment');
            input.addEventListener('change', async function () {
                if (!input.files || !input.files[0]) return;
                toast('warn', '📷 Leyendo el carnet… un momento.');
                try {
                    const data = await readBlob(await shrinkFile(input.files[0], 1600));
                    if (!data.ok) { toast('bad', data.error); return; }
                    sendToForm(data);
                } catch (e) { toast('bad', 'No se pudo leer el carnet. Intenta de nuevo.'); }
            });
            input.click();
        }

        async function openScanner() {
            if (document.getElementById('hh-cam')) return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { pickPhoto(); return; }
            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
            } catch (e) {
                toast('warn', 'No pude abrir la cámara (¿diste el permiso?). Te dejo sacar una foto en su lugar.');
                pickPhoto();
                return;
            }
            try { const t = stream.getVideoTracks()[0]; await t.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }); } catch (e) {}

            const box = document.createElement('div');
            box.id = 'hh-cam';
            box.style.cssText = 'position:fixed;inset:0;z-index:9990;background:#000;display:flex;flex-direction:column;font:15px/1.4 -apple-system,Segoe UI,sans-serif;color:#fff;';
            box.innerHTML =
                '<div id="hh-cam-msg" style="padding:14px 16px;background:rgba(0,0,0,.75);text-align:center;font-weight:700;"></div>' +
                '<div style="position:relative;flex:1;min-height:0;overflow:hidden;background:#000;">' +
                  '<video id="hh-cam-video" playsinline muted autoplay style="width:100%;height:100%;object-fit:cover;"></video>' +
                  '<div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(88%,560px);aspect-ratio:1.586;border:3px solid #ff7918;border-radius:16px;box-shadow:0 0 0 9999px rgba(0,0,0,.45);pointer-events:none;"></div>' +
                '</div>' +
                '<div style="padding:14px 16px calc(14px + env(safe-area-inset-bottom));background:rgba(0,0,0,.85);display:flex;gap:12px;align-items:center;justify-content:center;">' +
                  '<button type="button" id="hh-cam-go" style="flex:1;max-width:320px;background:#ff7918;color:#fff;border:0;border-radius:999px;padding:16px;font:800 17px -apple-system,Segoe UI,sans-serif;cursor:pointer;">📸 Escanear carnet</button>' +
                  '<button type="button" id="hh-cam-done" style="background:#2a2a2a;color:#fff;border:1px solid #555;border-radius:999px;padding:16px 22px;font:700 16px -apple-system,Segoe UI,sans-serif;cursor:pointer;">Listo</button>' +
                '</div>';
            document.body.appendChild(box);
            const video = box.querySelector('#hh-cam-video'), msg = box.querySelector('#hh-cam-msg');
            const go = box.querySelector('#hh-cam-go'), done = box.querySelector('#hh-cam-done');
            video.srcObject = stream;
            video.play().catch(() => {});
            let reads = 0, busy = false;
            const say = (text, color) => { msg.textContent = text; msg.style.color = color || '#fff'; };
            say('Encuadra el carnet del titular dentro del marco', '#fff');

            function close() {
                stream.getTracks().forEach(t => t.stop());
                box.remove();
            }
            done.addEventListener('click', close);

            go.addEventListener('click', async function () {
                if (busy || !video.videoWidth) return;
                busy = true; go.disabled = true; go.style.opacity = '.6';
                say('Leyendo el carnet…', '#ffb379');
                try {
                    const data = await readBlob(await shrinkSource(video, video.videoWidth, video.videoHeight, 1600));
                    if (!data.ok) {
                        say(data.error, '#ffb4b4');
                    } else {
                        sendToForm(data);
                        reads += 1;
                        const who = [data.first_name, data.last_name].filter(Boolean).join(' ');
                        say('✓ ' + (who || data.rut) + (reads === 1 ? '' : ' (acompañante)') + ' — pon el siguiente carnet o toca «Listo»', '#9be39b');
                    }
                } catch (e) {
                    say('No se pudo leer el carnet. Intenta de nuevo.', '#ffb4b4');
                }
                busy = false; go.disabled = false; go.style.opacity = '1';
            });
        }

        function addPhotoButton() {
            if (document.getElementById('hh-id-photo-btn')) return;
            if (!document.body || !document.querySelector('[name="document_number"], [name="holder_document_number"], .guest-doc')) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.id = 'hh-id-photo-btn';
            button.textContent = '📷 Escanear carnet';
            button.title = 'Abrir la cámara y escanear el carnet del titular y de los acompañantes';
            button.style.cssText = 'position:fixed;right:16px;bottom:76px;z-index:9998;background:#ff7918;color:#fff;border:0;border-radius:999px;padding:12px 18px;font:700 15px -apple-system,Segoe UI,sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.45);cursor:pointer;';
            button.addEventListener('click', openScanner);
            document.body.appendChild(button);
        }
        // Esta pantalla puede ejecutar el script antes o después de terminar de cargar: se intenta en cada momento
        // (la función no hace nada si el botón ya existe o si la pantalla no tiene un documento que completar).
        addPhotoButton();
        document.addEventListener('DOMContentLoaded', addPhotoButton);
        window.addEventListener('load', addPhotoButton);
        setTimeout(addPhotoButton, 800);

        return { parse, report, lookup, addGuest };
    })();
</script>
