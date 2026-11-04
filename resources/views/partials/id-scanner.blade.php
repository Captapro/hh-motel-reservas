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
            const colors = { ok: ['#1c3a2a', '#6fd39a'], warn: ['#3a331c', '#e8c76f'], bad: ['#4a1c1c', '#ffb4b4'] }[kind];
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

        return { parse, report, lookup, addGuest };
    })();
</script>
