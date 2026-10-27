{{--
    Combos "a elección" (ej. "2 bebidas a elegir entre Coca-Cola, Sprite y
    Fanta"): bajo cada combo con elección que se esté vendiendo aparece un
    selector para repartir las unidades entre los sabores. Arma los campos
    combo_choices[combo][componente][producto] = cantidad que lee
    ConsumptionService::addCombo(), y no deja enviar el formulario si falta
    elegir. El campo de cantidad del combo lleva data-choices con
    Combo::choicesSpec() y data-combo-id.
--}}
<style>
    .cc-box { margin-top:8px; text-align:left; font-size:12.5px; width:100%; box-sizing:border-box; }
    .cc-box .cc-toggle { display:flex !important; align-items:center; justify-content:space-between; gap:6px; width:100% !important; margin:0 !important; padding:7px 10px !important; border-radius:8px; border:1px dashed rgba(255,121,24,.7); background:rgba(255,121,24,.08); color:#ff9a4d; font:600 12px/1.3 inherit; text-align:left; cursor:pointer; }
    .cc-box .cc-toggle.cc-done { border-style:solid; border-color:rgba(52,211,153,.55); background:rgba(52,211,153,.08); color:#34d399; }
    .cc-box .cc-toggle span { overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
    .cc-box .cc-toggle i { font-style:normal; flex:none; transition:transform .15s ease; }
    .cc-box.cc-open .cc-toggle i { transform:rotate(180deg); }
    .cc-box .cc-body { margin-top:6px; padding:9px 10px; border:1px dashed rgba(255,121,24,.6); border-radius:9px; }
    .cc-box:not(.cc-open) .cc-body { display:none; }
    .cc-box.cc-missing .cc-toggle, .cc-box.cc-missing .cc-body { border-style:solid; border-color:#e05252; box-shadow:0 0 0 2px rgba(224,82,82,.2); color:#ff8a8a; }
    .cc-title { font-weight:700; margin-bottom:6px; }
    .cc-title small { font-weight:500; opacity:.75; }
    .cc-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:3px 0; }
    .cc-row .cc-name { flex:1; min-width:0; display:flex; flex-direction:column; line-height:1.25; }
    .cc-row .cc-name span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:600; }
    .cc-row .cc-name small { opacity:.6; font-size:11px; }
    /* Con el selector abierto, la tarjeta del combo ocupa dos columnas para
       que los nombres de los sabores entren en una línea. */
    .addon-combo:has(> .cc-box.cc-open), .combo-card:has(> .cc-box.cc-open) { grid-column: span 2; }
    .cc-box .cc-options { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:2px 14px; }
    .cc-row .cc-step { display:inline-flex; align-items:center; gap:6px; }
    .cc-row .cc-step button { width:26px !important; height:26px; padding:0 !important; margin:0 !important; border-radius:6px; border:1px solid rgba(127,127,127,.45); background:transparent; color:inherit; font-size:15px; line-height:1; cursor:pointer; }
    .cc-row .cc-step button:disabled { opacity:.35; cursor:default; }
    .cc-row .cc-step b { min-width:16px; text-align:center; }
</style>
<script>
(function () {
    const state = new Map(); // input -> { [itemId]: { [productId]: count } }

    function qtyOf(input) {
        if (input.disabled) return 0;
        if (input.type === 'checkbox') return input.checked ? 1 : 0;
        return Math.max(0, parseInt(input.value, 10) || 0);
    }

    function render(input) {
        const spec = JSON.parse(input.dataset.choices || '[]');
        const comboId = input.dataset.comboId;
        const qty = qtyOf(input);
        let box = input._ccBox;
        if (!qty || !spec.length) { if (box) box.remove(); input._ccBox = null; return; }
        if (!box) {
            box = document.createElement('div');
            box.className = 'cc-box';
            const anchor = input.closest('[data-cc-anchor]') || input.parentElement;
            anchor.insertAdjacentElement('afterend', box);
            input._ccBox = box;
        }
        const picks = state.get(input) || {};
        state.set(input, picks);
        const wasComplete = input._ccComplete;
        box.innerHTML = '';
        // Botón que despliega la lista; muestra lo que falta o lo ya elegido.
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'cc-toggle';
        toggle.addEventListener('click', function () { box.classList.toggle('cc-open'); });
        box.appendChild(toggle);
        const body = document.createElement('div');
        body.className = 'cc-body';
        box.appendChild(body);
        let missingTotal = 0;
        const chosen = [];
        spec.forEach(function (item) {
            const need = item.quantity * qty;
            const p = picks[item.item_id] = picks[item.item_id] || {};
            // Si bajó la cantidad del combo, recorta lo elegido de más.
            let total = Object.values(p).reduce((a, b) => a + b, 0);
            for (const id of Object.keys(p).reverse()) { while (total > need && p[id] > 0) { p[id]--; total--; } }
            const left = need - total;
            missingTotal += left;
            const title = document.createElement('div');
            title.className = 'cc-title';
            title.innerHTML = 'Elige ' + need + ' <small>' + (left > 0 ? '· faltan ' + left : '· listo ✓') + '</small>';
            body.appendChild(title);
            const list = document.createElement('div');
            list.className = 'cc-options';
            body.appendChild(list);
            item.options.forEach(function (opt) {
                const count = p[opt.id] || 0;
                const row = document.createElement('div');
                row.className = 'cc-row';
                const stockLeft = opt.stock === null ? null : opt.stock - count;
                row.innerHTML = '<span class="cc-name"></span><span class="cc-step"><button type="button" aria-label="Menos">−</button><b>' + count + '</b><button type="button" aria-label="Más">+</button></span>';
                const nameEl = document.createElement('span');
                nameEl.textContent = opt.name;
                nameEl.title = opt.name;
                row.querySelector('.cc-name').appendChild(nameEl);
                if (opt.stock !== null) row.querySelector('.cc-name').insertAdjacentHTML('beforeend', '<small>' + (opt.stock > 0 ? opt.stock + ' disp.' : 'agotado') + '</small>');
                const [minus, plus] = row.querySelectorAll('button');
                minus.disabled = count <= 0;
                plus.disabled = left <= 0 || (stockLeft !== null && stockLeft <= 0);
                minus.addEventListener('click', function () { p[opt.id] = count - 1; render(input); });
                plus.addEventListener('click', function () { p[opt.id] = count + 1; render(input); });
                list.appendChild(row);
                if (count > 0) chosen.push(count + ' ' + opt.name);
                if (count > 0) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'combo_choices[' + comboId + '][' + item.item_id + '][' + opt.id + ']';
                    hidden.value = count;
                    box.appendChild(hidden);
                }
            });
        });
        const done = missingTotal === 0;
        toggle.classList.toggle('cc-done', done);
        toggle.innerHTML = '<span></span><i>▾</i>';
        toggle.querySelector('span').textContent = done ? '✓ ' + chosen.join(', ') : 'Elegir · faltan ' + missingTotal;
        // Al completar la elección la lista se recoge sola.
        if (done && wasComplete === false) box.classList.remove('cc-open');
        input._ccComplete = done;
        box.classList.toggle('cc-missing', box.classList.contains('cc-missing') && !done);
    }

    function complete(input) {
        const spec = JSON.parse(input.dataset.choices || '[]');
        const qty = qtyOf(input);
        const picks = state.get(input) || {};
        return spec.every(item => Object.values(picks[item.item_id] || {}).reduce((a, b) => a + b, 0) === item.quantity * qty);
    }

    const inputs = () => Array.from(document.querySelectorAll('[data-choices]')).filter(i => i.dataset.choices !== '[]');
    const last = new Map();
    function sync() {
        inputs().forEach(function (input) {
            const q = qtyOf(input);
            if (last.get(input) !== q) { last.set(input, q); render(input); }
        });
    }
    // Los botones − / + de cada pantalla cambian el valor sin avisar: se revisa seguido.
    document.addEventListener('input', sync);
    document.addEventListener('change', sync);
    document.addEventListener('click', function () { setTimeout(sync, 0); });
    setInterval(sync, 400);
    sync();

    // Sin elegir los sabores no se envía: se marca el selector que falta.
    document.addEventListener('submit', function (e) {
        const missing = inputs().filter(i => e.target.contains(i) && qtyOf(i) > 0 && !complete(i));
        if (!missing.length) return;
        e.preventDefault();
        e.stopPropagation();
        missing.forEach(i => i._ccBox && i._ccBox.classList.add('cc-missing', 'cc-open'));
        (missing[0]._ccBox || missing[0]).scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, true);
})();
</script>
