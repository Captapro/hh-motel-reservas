{{--
    Sugiere nombres y apellidos mientras se escribe. Tab o Enter acepta la
    primera opción (flechas para elegir otra). Sirve para las filas de
    acompañantes que se crean después, porque escucha en todo el documento.
--}}
<style>
    #name-suggest { position:absolute; z-index:9998; background:#1c1c1c; border:1px solid #444; border-radius:7px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,.5); font:14px -apple-system,'Segoe UI',sans-serif; }
    #name-suggest div { padding:7px 12px; color:#ddd; cursor:pointer; white-space:nowrap; }
    #name-suggest div.on { background:#ff7918; color:#fff; }
</style>
<script>
    (function () {
        const FIRST = '#customer-first-name, .guest-first';
        const LAST = '#customer-last-name, .guest-last';
        const MAX_SHOWN = 6;
        const plain = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

        let lists = null, box = null, field = null, options = [], active = 0;

        // Apenas abre la pantalla, para que esté lista antes de la primera letra.
        function load() {
            fetch('{{ route('customers.names') }}', { headers: { 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : null)
                .then(d => {
                    if (!d) return;
                    const index = words => words.map(w => [w, plain(w)]);
                    lists = { first: index(d.first), last: index(d.last) };
                })
                .catch(() => {});
        }

        function hide() {
            if (box) box.remove();
            box = null; options = []; field = null;
        }

        // Se completa la última palabra, para el segundo nombre o apellido.
        function currentWord(input) { return input.value.split(/\s+/).pop(); }

        function accept(i) {
            const input = field;
            const words = input.value.split(/\s+/);
            words[words.length - 1] = options[i];
            input.value = words.join(' ');
            input.dispatchEvent(new Event('input', { bubbles: true }));
            hide();
        }

        function render() {
            if (box) box.remove();
            box = document.createElement('div');
            box.id = 'name-suggest';
            options.forEach(function (word, i) {
                const row = document.createElement('div');
                row.textContent = word;
                if (i === active) row.className = 'on';
                // mousedown: antes de que el campo pierda el foco.
                row.addEventListener('mousedown', function (e) { e.preventDefault(); accept(i); });
                box.appendChild(row);
            });
            const rect = field.getBoundingClientRect();
            box.style.left = (rect.left + window.scrollX) + 'px';
            box.style.top = (rect.bottom + window.scrollY + 2) + 'px';
            box.style.minWidth = Math.min(rect.width, 240) + 'px';
            document.body.appendChild(box);
        }

        load();

        document.addEventListener('input', function (e) {
            const input = e.target;
            if (!input.matches || !input.matches(FIRST + ',' + LAST)) return;
            // Lo que escribe el lector o el sistema no llega con el foco como tecleo.
            if (!lists || document.activeElement !== input || !e.isTrusted) { hide(); return; }
            const typed = plain(currentWord(input));
            if (typed.length < 2) { hide(); return; }
            const list = input.matches(FIRST) ? lists.first : lists.last;
            const found = [];
            for (const [word, key] of list) {
                if (key.startsWith(typed) && key !== typed) found.push(word);
                if (found.length === MAX_SHOWN) break;
            }
            if (!found.length) { hide(); return; }
            field = input; options = found; active = 0;
            render();
        });

        document.addEventListener('keydown', function (e) {
            if (!box || e.target !== field) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length;
                render();
            } else if (e.key === 'Tab') {
                accept(active); // sin preventDefault: Tab sigue al próximo campo
            } else if (e.key === 'Enter') {
                e.preventDefault();
                accept(active);
            } else if (e.key === 'Escape') {
                hide();
            }
        });

        document.addEventListener('focusout', function (e) { if (e.target === field) hide(); });
    })();
</script>
