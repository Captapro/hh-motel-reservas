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
    .cc-box { margin-top:8px; padding:9px 10px; border:1px dashed rgba(255,121,24,.6); border-radius:9px; text-align:left; font-size:12.5px; width:100%; box-sizing:border-box; }
    .cc-box.cc-missing { border-style:solid; border-color:#e05252; box-shadow:0 0 0 2px rgba(224,82,82,.2); }
    .cc-title { font-weight:700; margin-bottom:6px; }
    .cc-title small { font-weight:500; opacity:.75; }
    .cc-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:3px 0; }
    .cc-row .cc-name { flex:1; min-width:0; }
    .cc-row .cc-name small { opacity:.6; }
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
        box.innerHTML = '';
        spec.forEach(function (item) {
            const need = item.quantity * qty;
            const p = picks[item.item_id] = picks[item.item_id] || {};
            // Si bajó la cantidad del combo, recorta lo elegido de más.
            let total = Object.values(p).reduce((a, b) => a + b, 0);
            for (const id of Object.keys(p).reverse()) { while (total > need && p[id] > 0) { p[id]--; total--; } }
            const left = need - total;
            const title = document.createElement('div');
            title.className = 'cc-title';
            title.innerHTML = 'Elige ' + need + ' <small>' + (left > 0 ? '· faltan ' + left : '· listo ✓') + '</small>';
            box.appendChild(title);
            item.options.forEach(function (opt) {
                const count = p[opt.id] || 0;
                const row = document.createElement('div');
                row.className = 'cc-row';
                const stockLeft = opt.stock === null ? null : opt.stock - count;
                row.innerHTML = '<span class="cc-name"></span><span class="cc-step"><button type="button" aria-label="Menos">−</button><b>' + count + '</b><button type="button" aria-label="Más">+</button></span>';
                row.querySelector('.cc-name').textContent = opt.name;
                if (opt.stock !== null) row.querySelector('.cc-name').insertAdjacentHTML('beforeend', ' <small>· ' + opt.stock + ' disp.</small>');
                const [minus, plus] = row.querySelectorAll('button');
                minus.disabled = count <= 0;
                plus.disabled = left <= 0 || (stockLeft !== null && stockLeft <= 0);
                minus.addEventListener('click', function () { p[opt.id] = count - 1; render(input); });
                plus.addEventListener('click', function () { p[opt.id] = count + 1; render(input); });
                box.appendChild(row);
                if (count > 0) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'combo_choices[' + comboId + '][' + item.item_id + '][' + opt.id + ']';
                    hidden.value = count;
                    box.appendChild(hidden);
                }
            });
        });
        box.classList.toggle('cc-missing', box.classList.contains('cc-missing') && !complete(input));
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
        missing.forEach(i => i._ccBox && i._ccBox.classList.add('cc-missing'));
        (missing[0]._ccBox || missing[0]).scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, true);
})();
</script>
