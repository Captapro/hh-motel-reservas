{{--
    Ordena la oferta de consumo: pestañas (combos, bebidas, sexshop dividido en
    tres), buscador y filas de una línea. Los agotados salen de la vista. Solo
    muestra u oculta filas: las cantidades siguen en sus campos del formulario.
--}}
<style>
    .hh-picker-bar { display:flex; flex-wrap:wrap; gap:6px; margin:8px 0; }
    .hh-picker-tab { background:#262626; border:1px solid #3a3a3a; color:#ccc; border-radius:999px; padding:7px 12px; font:600 12.5px -apple-system,'Segoe UI',sans-serif; cursor:pointer; white-space:nowrap; margin:0; width:auto; }
    .hh-picker-tab:hover { border-color:#ff7918; }
    .hh-picker-tab.on { background:#ff7918; border-color:#ff7918; color:#21170e; }
    .hh-picker-tab b { margin-left:4px; background:#21170e; color:#ff9a4d; border-radius:999px; padding:0 6px; font-size:11px; }
    .hh-picker-search { width:100%; box-sizing:border-box; background:#1c1c1c; border:1px solid #3a3a3a; border-radius:8px; color:#eee; padding:9px 12px; font-size:13.5px; margin:0; }
    .hh-picked .prod-cols, .hh-picked .offer-block { column-count:1 !important; columns:auto !important; }
    .hh-picked .cat-block { margin:0 !important; }
    .hh-picked .cat-block > .cat-label, .hh-picked .combos-label, .hh-picked .hh-combos-title { display:none !important; }
    .hh-picked .prod-row { display:flex; align-items:center; gap:10px; padding:4px 0 !important; border-top:1px solid rgba(255,255,255,.06) !important; }
    .hh-picked .prod-row > div:first-child { display:flex; align-items:center; gap:10px; flex:1 1 auto; min-width:0; }
    .hh-picked .prod-row .name { flex:1 1 auto; min-width:0; font-size:13.5px !important; }
    .hh-picked .prod-row .price { flex:0 0 auto; margin:0 !important; font-size:12px !important; white-space:nowrap; text-align:right; }
    .hh-picked .prod-row.is-out { opacity:.45; }
    .hh-picker-more { background:none; border:none; color:#9a9a9a; font-size:12px; text-decoration:underline; cursor:pointer; padding:8px 0 2px; margin:0; width:auto; }
    .hh-picker-none { color:#9a9a9a; font-size:13px; padding:12px 0; }
    @media (max-width:520px) { .hh-picked .prod-row > div:first-child { flex-direction:column; align-items:flex-start; gap:0; } .hh-picked .prod-row .price { text-align:left; } }
</style>
<script>
    window.hhProductPicker = function (root) {
        const list = root && root.querySelector('.prod-cols, .offer-block');
        if (!list) return;
        root.querySelectorAll('.hh-picker-bar, .hh-picker-search, .hh-picker-more, .hh-picker-none').forEach(e => e.remove());
        root.classList.add('hh-picked');

        const combos = root.querySelector('.combo-cards');
        if (combos && combos.previousElementSibling && combos.previousElementSibling.classList.contains('cat-label')) {
            combos.previousElementSibling.classList.add('hh-combos-title');
        }

        // El sexshop es muy largo: se divide por el nombre. Lo que no calce sigue en "Sexshop".
        const SEXSHOP = [
            ['Íntimo', /preservativ|lubric|\bgel\b|sachet|feromona/i],
            ['Juguetes', /vibrador|dildo|anillo|artefacto|masturb|plug|bala\b|succion/i],
            ['Juegos', /carta|dado|antifaz|oreja|fusta|flogger|cuerda|arn[eé]s|esposa|bombilla|juego/i],
        ];
        const labelFor = function (row) {
            const category = row.closest('.cat-block').querySelector('.cat-label').textContent.trim();
            if (/bebestible/i.test(category)) return 'Bebidas';
            if (!/sex\s*shop/i.test(category)) return category.charAt(0) + category.slice(1).toLowerCase();
            const name = row.querySelector('.name').textContent;
            const hit = SEXSHOP.find(([, rule]) => rule.test(name));
            return hit ? hit[0] : 'Sexshop';
        };

        const rows = [...list.querySelectorAll('.prod-row')].map(function (el) {
            const out = !!el.querySelector('.q-plus:disabled');
            el.classList.toggle('is-out', out);
            return { el, out, label: labelFor(el), text: el.querySelector('.name').textContent.toLowerCase() };
        });

        const labels = [...new Set(rows.map(r => r.label))];
        const rank = l => (/^(otro|otros)$/i.test(l) ? 2 : (/beb/i.test(l) ? 0 : 1));
        labels.sort((a, b) => rank(a) - rank(b));
        const tabs = (combos ? ['Combos'] : []).concat(labels);

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'hh-picker-search';
        search.placeholder = 'Buscar producto…';
        search.autocomplete = 'off';
        const bar = document.createElement('div');
        bar.className = 'hh-picker-bar';
        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'hh-picker-more';
        const none = document.createElement('div');
        none.className = 'hh-picker-none';
        none.textContent = 'No hay productos con ese nombre.';
        const start = combos && combos.previousElementSibling ? combos.previousElementSibling : list;
        start.before(search, bar);
        list.after(none, more);

        let current = tabs[0], showOut = false;

        const quantity = el => Number((el.querySelector('input[type=number]') || {}).value) || 0;
        const pickedIn = function (tab) {
            if (tab === 'Combos') return [...combos.querySelectorAll('input[type=number]')].filter(i => Number(i.value) > 0).length;
            return rows.filter(r => r.label === tab && quantity(r.el) > 0).length;
        };

        function render() {
            const q = search.value.trim().toLowerCase();
            bar.replaceChildren(...tabs.map(function (tab) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'hh-picker-tab' + (!q && tab === current ? ' on' : '');
                b.textContent = tab;
                const n = pickedIn(tab);
                if (n) { const c = document.createElement('b'); c.textContent = n; b.appendChild(c); }
                b.addEventListener('click', function () { current = tab; showOut = false; search.value = ''; render(); });
                return b;
            }));

            const inCombos = !q && current === 'Combos';
            if (combos) combos.style.display = inCombos ? '' : 'none';
            list.style.display = inCombos ? 'none' : '';

            const here = q ? rows.filter(r => r.text.includes(q)) : rows.filter(r => r.label === current);
            rows.forEach(r => { r.el.style.display = 'none'; });
            const hidden = here.filter(r => r.out && !showOut && !quantity(r.el));
            here.filter(r => !hidden.includes(r)).forEach(r => { r.el.style.display = ''; });

            more.hidden = inCombos || !hidden.length;
            more.textContent = 'Ver agotados (' + hidden.length + ')';
            none.hidden = inCombos || !q || here.length > 0;
        }

        more.addEventListener('click', function () { showOut = true; render(); });
        search.addEventListener('input', function () { showOut = false; render(); });
        // Enter en el buscador no debe enviar la reserva.
        search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
        root.addEventListener('click', function (e) { if (e.target.closest('.qty')) setTimeout(render, 0); });
        root.addEventListener('change', function (e) { if (e.target.closest('.qty')) render(); });
        render();
    };
    document.querySelectorAll('.consumo-panel, .offer-panel').forEach(window.hhProductPicker);
</script>
