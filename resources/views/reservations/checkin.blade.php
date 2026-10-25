<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Check-in {{ $booking->code }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 520px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 14px; margin-bottom: 6px; }
        .meta { font-size: 13px; color:#999; margin-bottom: 4px; }
        .hint-box { background:#1c2f3a; border:1px solid #3a5a72; color:#a9d3e6; padding:12px 14px; border-radius:8px; margin: 16px 0; font-size:14px; }
        .late-box { background:#3a331c; border:1px solid #6b5a1e; color:#e8c76f; padding:10px 14px; border-radius:8px; margin: 10px 0 0; font-size:13.5px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; }
        .cat-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.04em; margin: 14px 0 8px; }
        .cat-label:first-child { margin-top:0; }
        .prod-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-top:1px solid #262626; }
        .prod-row:first-of-type { border-top:none; }
        .prod-row .name { font-size:14px; }
        .prod-row .price { font-size:12px; color:#888; }
        .prod-row input { width:60px; background:#111; border:1px solid #333; color:#fff; padding:7px; border-radius:6px; text-align:center; }
        .existing-box { background:#14251c; border:1px solid #2e6e45; border-radius:10px; padding:14px 16px; margin-bottom:16px; }
        .existing-box .cat-label { color:#8fe0ad; margin-top:0; }
        .existing-row { display:flex; justify-content:space-between; font-size:14px; padding:5px 0; color:#dff5e6; }
        .existing-row b { font-family: ui-monospace, monospace; }
        .existing-total { display:flex; justify-content:space-between; font-size:14px; font-weight:700; color:#8fe0ad; border-top:1px solid #2e6e45; margin-top:6px; padding-top:8px; }
        .manual-extras summary { cursor:pointer; list-style:none; font-size:13.5px; color:#999; padding:12px 4px; border-bottom:1px solid #262626; margin-bottom: 4px; }
        .manual-extras summary::-webkit-details-marker { display:none; }
        .manual-extras summary::before { content:'+ '; color:#ff7918; font-weight:700; }
        .manual-extras[open] summary::before { content:'− '; }
        .manual-extras summary:hover { color:#eee; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        .guests-required { border-color:#6b5a1e; }
        .guests-required .cat-label { color:#e8c76f; }
        .guests-required p { font-size:13px; color:#bbb; margin:0 0 12px; line-height:1.4; }
        .guest-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
        .guest-row input { flex:1; min-width:0; background:#111; border:1px solid #333; color:#fff; padding:9px 10px; border-radius:6px; font-size:14px; }
        .guest-row .guest-remove { flex:none; width:auto; background:#241717; border:1px solid #4a2a2a; color:#c47a7a; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:14px; }
        .guest-row .guest-remove:hover { border-color:#a83a3a; color:#e88a8a; }
        .guest-add-btn { width:100%; background:#1c1c1c; border:1px dashed #444; color:#999; padding:9px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; }
        .guest-add-btn:hover { border-color:#ff7918; color:#ff7918; }
        .guests-error { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:10px 12px; border-radius:8px; font-size:13px; margin-top:10px; }
        button { width:100%; background:#ff7918; color:#fff; border:none; padding:14px; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
        a.back { display:block; text-align:center; margin-top: 16px; color:#999; font-size: 13px; text-decoration:none; }

        .upsell-overlay { position:fixed; inset:0; background:rgba(0,0,0,.72); display:flex; align-items:center; justify-content:center; z-index:200; padding:20px; }
        .upsell-overlay[hidden] { display:none; }
        .upsell-modal { background:#1c1c1c; border:1px solid #444; border-radius:12px; padding:20px 22px; max-width:440px; width:100%; max-height:88vh; display:flex; flex-direction:column; }
        .upsell-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .upsell-title { font-size:16px; font-weight:700; color:#f0d98a; }
        .upsell-x { width:auto; background:none; border:none; color:#888; font-size:18px; cursor:pointer; padding:0; margin:0; }
        .upsell-sub { font-size:13px; color:#bbb; margin:6px 0 14px; line-height:1.4; flex:none; }
        .upsell-list { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; overflow-y:auto; flex:1; }
        .upsell-item { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#141414; border:1px solid #2c2c2c; border-radius:9px; padding:10px 12px; }
        .upsell-item.picked { border-color:#e8c76f; background:#211d10; }
        .ui-name { font-size:14px; font-weight:600; }
        .ui-price { font-size:12px; color:#999; margin-top:2px; }
        .ui-pick { width:auto; flex:none; background:#2a2a2a; border:1px solid #555; color:#eee; border-radius:7px; padding:7px 13px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .ui-pick:hover { border-color:#ff7918; }
        .upsell-item.picked .ui-pick { background:#e8c76f; color:#1c1c1c; border-color:#e8c76f; }
        .upsell-add { width:100%; margin:0 0 8px; background:#ff7918; color:#fff; border:none; padding:13px; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
        .upsell-skip { width:100%; margin:0; background:transparent; border:1px solid #444; color:#aaa; padding:11px; border-radius:8px; font-size:13.5px; cursor:pointer; }
        .upsell-skip:hover { border-color:#666; color:#ccc; }
        /* Dos columnas en pantalla ancha: registro de acompañantes y acción | consumo para ofrecer */
        .page-inner { max-width: 1100px; }
        .ci-grid { display:grid; grid-template-columns: minmax(0,.8fr) minmax(0,1.2fr); gap: 0 28px; align-items:start; }
        /* Panel de oferta: oscuro como el resto, categorías en bloques y cantidades con − / + */
        .hh-checkin-page .offer-panel { background:#1c1c1c; border:1px solid #333; border-radius:12px; padding:12px 18px 4px; }
        .offer-head { display:flex; justify-content:space-between; align-items:center; gap:12px; padding-bottom:10px; margin-bottom:6px; border-bottom:1px solid #2c2c2c; }
        .offer-title { font-size:14px; font-weight:700; color:#eee; }
        .offer-total { font-size:13px; color:#888; font-family: ui-monospace, monospace; }
        .offer-total.has { color:#8fe0ad; font-weight:700; }
        .offer-block { column-count:2; column-gap:26px; margin-bottom:6px; }
        .offer-block .cat-block { break-inside:avoid; margin-bottom:6px; }
        .offer-block .cat-label { margin:6px 0 2px; font-size:10.5px; letter-spacing:.06em; }
        .hh-checkin-page .offer-panel .prod-row { padding:3px 0; gap:10px; align-items:center; }
        .hh-checkin-page .offer-panel .prod-row .name { font-size:13.5px; line-height:1.25; }
        .hh-checkin-page .offer-panel .prod-row .price { font-size:11.5px; margin-top:2px; color:#8d8f94; }
        .qty { display:flex; align-items:center; gap:2px; flex:none; }
        .hh-checkin-page .offer-panel .qty button { width:28px; height:28px; margin:0; padding:0; border-radius:7px; background:#262626; border:1px solid #444; color:#ddd; font-size:17px; line-height:1; font-weight:600; min-height:0; }
        .hh-checkin-page .offer-panel .qty button:hover:not(:disabled) { border-color:#ff7918; color:#ff7918; }
        .hh-checkin-page .offer-panel .qty input { width:34px; padding:0; text-align:center; background:transparent; border:none; color:#888; font-size:15px; font-weight:700; -moz-appearance:textfield; appearance:textfield; }
        .hh-checkin-page .offer-panel .qty input::-webkit-inner-spin-button, .hh-checkin-page .offer-panel .qty input::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
        .hh-checkin-page .offer-panel .qty.has input { color:#ff7918; }
        .ci-col .card:first-child, .ci-col .existing-box:first-child { margin-top:0; }
        button.save { width:100%; margin-top:6px; }
        /* Cabecera compacta para que todo quepa en una pantalla */
        .page-inner { padding-top:14px; padding-bottom:16px; }
        .page-inner > h1 { display:inline-block; margin:0 14px 4px 0; }
        .page-inner > .code { display:inline-block; margin:0; }
        .page-inner > .meta { margin:0 0 10px; }
        .hint-box { padding:8px 12px; margin-bottom:12px; }
        @media (max-width: 860px) { .ci-grid { grid-template-columns: 1fr; }  .offer-block { column-count:1; } }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner hh-checkin-page">
    <h1>Check-in</h1>
    <div class="code">{{ $booking->code }} — {{ $booking->room->name }} · {{ $booking->customer->name }}</div>
    <div class="meta">Reservado para {{ $booking->starts_at->timezone('America/Santiago')->format('d/m H:i') }} · {{ \App\Support\TimeFormat::minutes($booking->duration_minutes) }}</div>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="hint-box">
        Momento de ofrecerle los extras al cliente — lo que cargues acá se suma a la reserva junto con el check-in.
        @php $delay = (int) round($booking->starts_at->diffInMinutes(now(), false)); @endphp
        @if ($delay > 5)
            <div class="late-box">Llega {{ $delay }} min después de la hora reservada — queda registrado como atraso.</div>
        @endif
    </div>

    <form method="POST" action="{{ route('bookings.checkin', $booking->code) }}">
        @csrf

        <div class="ci-grid">
            <div class="ci-col">
        @php $requiredGuests = max(0, $booking->guests_count - 1); @endphp
        <div class="card guests-required">
            <div class="cat-label" style="margin-top:0;">Registro de acompañantes — obligatorio</div>
            <p>
                Esta reserva es para <b>{{ $booking->guests_count }}</b> {{ Str::plural('persona', $booking->guests_count) }}.
                @if ($requiredGuests > 0)
                    El titular ya está identificado — falta el nombre y apellido de {{ $requiredGuests }} {{ Str::plural('acompañante', $requiredGuests) }} para poder habilitar la habitación.
                @else
                    La reserva es para 1 persona — no hace falta registrar acompañantes.
                @endif
            </p>
            <div id="guest-rows"></div>
            @if ($requiredGuests > 0)
                <button type="button" class="guest-add-btn" onclick="hhAddGuestRow()">+ Agregar acompañante</button>
            @endif
            <textarea name="guest_names" id="guest-names-hidden" hidden>{{ old('guest_names', $booking->guests->pluck('name')->implode("\n")) }}</textarea>
            <div class="guests-error" id="guests-error" hidden></div>
        </div>

        @if ($booking->addons->isNotEmpty())
            <div class="existing-box">
                <div class="cat-label">✓ Ya cargado en esta reserva</div>
                @foreach ($booking->addons as $addon)
                    <div class="existing-row"><span>{{ $addon->description }}</span><b>${{ number_format($addon->amount, 0, ',', '.') }}</b></div>
                @endforeach
                <div class="existing-total"><span>Total consumo</span><span>${{ number_format($booking->addons->sum('amount'), 0, ',', '.') }}</span></div>
            </div>
        @endif

                <button type="submit" class="save">Registrar check-in</button>
                <a class="back" href="{{ route('reservations.show', $booking->code) }}">← Volver sin registrar</a>
            </div>
            <div class="ci-col">
        <div class="offer-panel">
                <div class="offer-head">
                    <span class="offer-title">Ofrecer productos y combos</span>
                    <span class="offer-total" id="offer-total">Sin consumo todavía</span>
                </div>

                @if ($combos->isNotEmpty())
                    <div class="offer-block">
                        <div class="cat-block">
                            <div class="cat-label">Combos</div>
                            @foreach ($combos as $combo)
                                <div class="prod-row">
                                    <div>
                                        <div class="name">{{ $combo->name }}</div>
                                        <div class="price">
                                            ${{ number_format($combo->price, 0, ',', '.') }}
                                            @if ($combo->description)
                                                · {{ $combo->description }}
                                            @endif
                                            {{ $combo->isOutOfStock() ? '· agotado' : '' }}
                                        </div>
                                    </div>
                                    <div class="qty">
                                        <button type="button" class="q-minus" aria-label="Menos" @disabled($combo->isOutOfStock())>−</button>
                                        <input type="number" name="combo_quantities[{{ $combo->id }}]" value="0" min="0" max="{{ $combo->availableCount() !== null ? min(20, $combo->availableCount()) : 20 }}" data-price="{{ $combo->price }}" @disabled($combo->isOutOfStock())>
                                        <button type="button" class="q-plus" aria-label="Más" @disabled($combo->isOutOfStock())>+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="offer-block">
                    @foreach ($products->groupBy('category') as $category => $items)
                        <div class="cat-block">
                            <div class="cat-label">{{ $category ?? 'Otros' }}</div>
                            @foreach ($items as $product)
                                <div class="prod-row">
                                    <div>
                                        <div class="name">{{ $product->name }}</div>
                                        <div class="price">
                                            ${{ number_format($product->price, 0, ',', '.') }}
                                            @if ($product->track_inventory)
                                                · {{ $product->isOutOfStock() ? 'agotado' : $product->stock.' disp.' }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="qty">
                                        <button type="button" class="q-minus" aria-label="Menos" @disabled($product->isOutOfStock())>−</button>
                                        <input type="number" name="quantities[{{ $product->id }}]" value="0" min="0" max="{{ $product->track_inventory ? min(20, $product->stock) : 20 }}" data-price="{{ $product->price }}" @disabled($product->isOutOfStock())>
                                        <button type="button" class="q-plus" aria-label="Más" @disabled($product->isOutOfStock())>+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            </div>
        </div>
    </form>

    </div>

    <script>
        // Mismo patrón de nombre+apellido por acompañante que en la reserva
        // nueva, pero acá es la fuente de verdad — obligatorio, y se valida
        // el conteo justo antes de enviar (fase de captura, corre antes que
        // el modal de upsell, para no dejar avanzar el check-in sin esto).
        function hhSyncGuestNames() {
            const rows = document.querySelectorAll('#guest-rows .guest-row');
            const names = [];
            rows.forEach(function (row) {
                const first = row.querySelector('.guest-first').value.trim();
                const last = row.querySelector('.guest-last').value.trim();
                const full = (first + ' ' + last).trim();
                if (full) names.push(full);
            });
            document.getElementById('guest-names-hidden').value = names.join('\n');
        }
        function hhAddGuestRow(first, last) {
            const container = document.getElementById('guest-rows');
            const row = document.createElement('div');
            row.className = 'guest-row';
            const firstInput = document.createElement('input');
            firstInput.type = 'text';
            firstInput.placeholder = 'Nombre';
            firstInput.className = 'guest-first';
            firstInput.value = first || '';
            const lastInput = document.createElement('input');
            lastInput.type = 'text';
            lastInput.placeholder = 'Apellido';
            lastInput.className = 'guest-last';
            lastInput.value = last || '';
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'guest-remove';
            removeBtn.textContent = '✕';
            removeBtn.addEventListener('click', function () { row.remove(); hhSyncGuestNames(); });
            [firstInput, lastInput].forEach(function (inp) { inp.addEventListener('input', hhSyncGuestNames); });
            row.appendChild(firstInput);
            row.appendChild(lastInput);
            row.appendChild(removeBtn);
            container.appendChild(row);
        }
        (function () {
            const existing = document.getElementById('guest-names-hidden').value.split(/\r\n|\r|\n/).map(function (s) { return s.trim(); }).filter(Boolean);
            existing.forEach(function (name) {
                const parts = name.split(' ');
                hhAddGuestRow(parts[0], parts.slice(1).join(' '));
            });
            const required = {{ $requiredGuests }};
            while (document.querySelectorAll('#guest-rows .guest-row').length < required) {
                hhAddGuestRow();
            }
        })();
        document.querySelector('form').addEventListener('submit', function (e) {
            hhSyncGuestNames();
            const required = {{ $requiredGuests }};
            const filled = document.getElementById('guest-names-hidden').value.split('\n').filter(function (s) { return s.trim(); }).length;
            const errEl = document.getElementById('guests-error');
            if (filled < required) {
                errEl.textContent = 'Faltan ' + (required - filled) + ' acompañante(s) por registrar (nombre y apellido) antes de poder hacer el check-in.';
                errEl.hidden = false;
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
            errEl.hidden = true;
        }, true);
    </script>

    @php
        $gateUpsell = $booking->addons->isEmpty();
        $modalOptions = $upsells->isNotEmpty()
            ? $upsells->map(fn ($u) => ['id' => $u['id'], 'kind' => 'upsell:'.$u['type'], 'name' => $u['label'], 'price' => $u['price'], 'detail' => $u['detail']])
            : $combos->reject->isOutOfStock()->map(fn ($c) => ['id' => $c->id, 'kind' => 'combo', 'name' => $c->name, 'price' => $c->price, 'detail' => $c->description]);
    @endphp
    @if ($gateUpsell && $modalOptions->isNotEmpty())
        <div class="upsell-overlay" id="upsell-overlay" hidden>
            <div class="upsell-modal">
                <div class="upsell-head">
                    <div class="upsell-title">🎁 El cliente llega sin nada cargado</div>
                    <button type="button" class="upsell-x" onclick="hhCloseUpsell()">✕</button>
                </div>
                <p class="upsell-sub">No se ofreció nada al reservar. Ahora que llegó — subir de categoría, más tiempo o un pack.</p>
                <div class="upsell-list" id="upsell-list"></div>
                <button type="button" class="upsell-add" onclick="hhConfirmUpsell(true)">Agregar lo elegido y registrar check-in</button>
                <button type="button" class="upsell-skip" onclick="hhConfirmUpsell(false)">El cliente no quiere nada — check-in igual</button>
            </div>
        </div>
        <script>
            (function () {
                const OPTS = {{ Illuminate\Support\Js::from($modalOptions->values()) }};
                const overlay = document.getElementById('upsell-overlay');
                const list = document.getElementById('upsell-list');
                const form = document.querySelector('form');
                if (!overlay || !form) return;
                let resolved = false;
                const ICONS = { category_upgrade: '⬆️', time_extension: '⏰', combo: '🎁' };

                function extrasTotal() {
                    let n = 0;
                    form.querySelectorAll('input[name^="combo_quantities"], input[name^="quantities"]').forEach(i => { n += Number(i.value) || 0; });
                    n += form.querySelectorAll('input[name="accepted_upsells[]"]').length;
                    return n;
                }
                function render() {
                    list.innerHTML = '';
                    OPTS.forEach(o => {
                        const el = document.createElement('div');
                        el.className = 'upsell-item';
                        el.dataset.kind = o.kind; el.dataset.id = o.id;
                        el.innerHTML = '<div><div class="ui-name">' + (ICONS[(o.kind.split(':')[1] || o.kind)] || '🎁') + ' ' + o.name + '</div>'
                            + '<div class="ui-price">$' + Number(o.price).toLocaleString('es-CL') + (o.detail ? ' · ' + o.detail : '') + '</div></div>'
                            + '<button type="button" class="ui-pick">Agregar</button>';
                        el.querySelector('.ui-pick').addEventListener('click', function () {
                            const on = el.classList.toggle('picked');
                            this.textContent = on ? '✓ Agregado' : 'Agregar';
                        });
                        list.appendChild(el);
                    });
                }
                window.hhCloseUpsell = function () { overlay.hidden = true; };
                window.hhConfirmUpsell = function (addThem) {
                    form.querySelectorAll('input[name="accepted_upsells[]"]').forEach(i => i.remove());
                    if (addThem) {
                        overlay.querySelectorAll('.upsell-item.picked').forEach(item => {
                            if (item.dataset.kind.startsWith('upsell:')) {
                                const inp = document.createElement('input');
                                inp.type = 'hidden'; inp.name = 'accepted_upsells[]'; inp.value = item.dataset.id;
                                form.appendChild(inp);
                            } else {
                                const c = form.querySelector('input[name="combo_quantities[' + item.dataset.id + ']"]');
                                if (c) c.value = Math.max(1, Number(c.value) || 0);
                            }
                        });
                    }
                    resolved = true;
                    overlay.hidden = true;
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                };
                form.addEventListener('submit', function (e) {
                    if (resolved || extrasTotal() > 0) return;
                    e.preventDefault();
                    render();
                    overlay.hidden = false;
                });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !overlay.hidden) hhCloseUpsell(); });
                overlay.addEventListener('click', function (e) { if (e.target === overlay) hhCloseUpsell(); });
            })();
        </script>
    @endif

    <script>
        // Cantidades con − / + y total del consumo a cargar, en vivo.
        (function () {
            const inputs = Array.from(document.querySelectorAll('.offer-panel .qty input'));
            const totalEl = document.getElementById('offer-total');
            if (!inputs.length || !totalEl) return;
            const money = (n) => '$' + Number(n).toLocaleString('es-CL');

            function refresh() {
                let total = 0, items = 0;
                inputs.forEach((i) => {
                    const q = Math.max(0, Number(i.value) || 0);
                    total += q * (Number(i.dataset.price) || 0);
                    items += q;
                    i.closest('.qty').classList.toggle('has', q > 0);
                });
                totalEl.textContent = items ? ('A cargar: ' + money(total) + ' · ' + items + (items === 1 ? ' ítem' : ' ítems')) : 'Sin consumo todavía';
                totalEl.classList.toggle('has', items > 0);
            }
            window.hhRefreshOffer = refresh;

            document.querySelectorAll('.offer-panel .qty').forEach((box) => {
                const input = box.querySelector('input');
                const step = (d) => {
                    const min = Number(input.min) || 0, max = Number(input.max) || 20;
                    input.value = Math.min(max, Math.max(min, (Number(input.value) || 0) + d));
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                };
                box.querySelector('.q-minus').addEventListener('click', () => step(-1));
                box.querySelector('.q-plus').addEventListener('click', () => step(1));
                input.addEventListener('input', refresh);
            });
            refresh();
        })();
    </script>
</body>
</html>
