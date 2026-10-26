<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservar — HH Motel</title>
    @include('partials.social-meta', ['shareTitle' => 'Reservar — HH Motel Santiago', 'shareDescription' => 'Elige tu playroom, fecha y duración, y confirma tu reserva en línea en HH Motel Santiago.'])
    <style>
        :root {
            color-scheme: light;
            --hh-accent: #ff7918;
            --hh-accent-hover: #ee6909;
            --hh-canvas: #f5f5f4;
            --hh-surface: #353638;
            --hh-ink: #202124;
        }
        * { box-sizing: border-box; }
        body { background:var(--hh-canvas); color:var(--hh-ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; line-height: 1.45; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 24px 16px 60px; }
        a.back { color:#62656c; text-decoration:none; font-size:13px; display:inline-block; margin-bottom: 18px; }
        .brand { font-size: 13px; font-weight: 800; letter-spacing: .1em; color: var(--hh-accent); margin-bottom: 8px; }
        h1 { font-size: 25px; margin: 0 0 6px; letter-spacing: -.03em; }
        p.sub { color:#62656c; font-size: 13.5px; margin: 0 0 24px; }
        .card { background:var(--hh-surface); color:#f5f5f5; border-radius:12px; box-shadow: 0 3px 10px #1011120b; padding: 22px 22px 26px; }
        .hint-box { background:#1c2f1c; border:1px solid #2e5a2e; color:#8fe0ad; border-radius:9px; padding:12px 14px; font-size:12.5px; margin-bottom:22px; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        .steps { display:flex; gap:8px; margin: 0 0 18px; font-size:11px; font-weight:700; color:#8a8d93; text-transform:uppercase; letter-spacing:.06em; }
        .steps span { flex:1; padding:8px 6px; border-bottom:2px solid #d9dadd; text-align:center; }
        .steps span:first-child { color:var(--hh-accent); border-color:var(--hh-accent); }
        label { display:block; font-size: 12.5px; color:#c1c3c7; margin: 16px 0 6px; }
        input, select { width:100%; box-sizing:border-box; background:#202123; border:1px solid #62656b; color:#f4f5f7; padding:11px 12px; border-radius:8px; font-size:15px; min-height:46px; }
        input[type="date"], input[type="time"] { color-scheme: dark; }
        .time-wheel { position:relative; display:flex; align-items:center; justify-content:center; gap:4px; height:200px; border:1px solid #62656b; border-radius:14px; background:#202123; overflow:hidden; }
        .time-wheel.empty { opacity:.45; }
        .wheel { list-style:none; margin:0; padding:80px 0; height:200px; width:92px; overflow-y:auto; overscroll-behavior:contain; scroll-snap-type:y mandatory; scrollbar-width:none; -webkit-overflow-scrolling:touch; text-align:center; position:relative; z-index:1; outline:none; -webkit-mask-image:linear-gradient(transparent, #000 32%, #000 68%, transparent); mask-image:linear-gradient(transparent, #000 32%, #000 68%, transparent); }
        .wheel::-webkit-scrollbar { display:none; }
        .wheel li { height:40px; line-height:40px; scroll-snap-align:center; font-size:22px; color:#8a8d93; font-variant-numeric:tabular-nums; cursor:pointer; user-select:none; -webkit-user-select:none; }
        .wheel li.sel { color:#ff7918; font-weight:800; font-size:26px; }
        .wheel:focus-visible { box-shadow:inset 0 0 0 2px #ff791866; border-radius:10px; }
        .wheel-band { position:absolute; left:10px; right:10px; top:80px; height:40px; border-top:1px solid #ff791380; border-bottom:1px solid #ff791380; background:#ff79181a; border-radius:8px; pointer-events:none; }
        .wheel-sep { font-size:26px; font-weight:800; color:#f4f5f7; z-index:1; padding-bottom:3px; }
        .time-range { font-size:12px; color:#8a8d93; margin-top:6px; min-height:16px; }
        .time-range.err { color:#f3b8b8; }
        .time-parts { display:grid; grid-template-columns:1fr 1fr 1fr; gap:7px; }
        input:focus, select:focus { outline:none; border-color:var(--hh-accent); box-shadow: 0 0 0 3px #ff791833; }
        .row2 { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        /* Fecha + Hora compartiendo fila deja los 3 selectores de hora con
           tan poco ancho en el celular que el texto ("AM/PM") se corta
           ("AI") -- se apilan uno debajo del otro para que la hora tenga
           todo el ancho de la tarjeta. Tiene que ir DESPUÉS de ".row2" acá
           arriba -- misma especificidad, así que gana el que aparece último. */
        @media (max-width: 640px) {
            .row2-datetime { grid-template-columns: 1fr; }
        }
        .honey-field { position:absolute; left:-9999px; top:-9999px; }
        button.submit { width:100%; margin-top:26px; background:var(--hh-accent); color:#21170e; border:none; padding:14px; border-radius:9px; font-size:15px; font-weight:750; cursor:pointer; }
        button.submit:hover { background:var(--hh-accent-hover); }
        .summary { background:#fff; color:var(--hh-ink); border:1px solid #dedfdf; border-radius:10px; padding:13px 14px; margin-top:18px; font-size:13px; }
        .summary strong { display:block; font-size:15px; margin-bottom:3px; }
        .summary small { color:#62656c; }
        .legal { font-size:11.5px; color:#8a8d93; margin-top:14px; text-align:center; }
        .coupon-applied { display:flex; align-items:center; justify-content:space-between; gap:10px; background:#12202b; border:1px solid #2e5a72; color:#9edaff; border-radius:9px; padding:11px 14px; font-size:12.5px; margin-bottom:22px; }
        .coupon-applied a { color:#9edaff; text-decoration:underline; flex:none; font-size:12px; }
        .coupon-warning { display:none; background:#3a2a12; border:1px solid #7a5a1f; color:#ffd699; border-radius:9px; padding:11px 14px; font-size:12.5px; margin-top:10px; }
        .coupon-warning.show { display:block; }
        .avail-status { display:none; align-items:center; gap:7px; font-size:12.5px; margin-top:10px; padding:9px 12px; border-radius:8px; }
        .avail-status.show { display:flex; }
        .avail-status.checking { background:#242527; color:#c1c3c7; }
        .avail-status.ok { background:#1c2f1c; border:1px solid #2e5a2e; color:#8fe0ad; }
        .avail-status.no { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; }
        .avail-status .spin { width:11px; height:11px; border-radius:50%; border:2px solid currentColor; border-top-color:transparent; animation:hh-spin .7s linear infinite; flex:none; }
        @keyframes hh-spin { to { transform:rotate(360deg); } }
        .extras { margin-top:22px; padding-top:4px; }
        .extras h2 { font-size:17px; margin:0 0 4px; }
        .extras p { color:#c1c3c7; font-size:12px; margin:0 0 12px; }
        .extra-list { display:grid; gap:9px; }
        .extra-item { display:flex; align-items:center; gap:10px; background:linear-gradient(105deg,#27201b,#202123 58%); border:1px solid #b45c24; border-left:4px solid var(--hh-accent); border-radius:10px; padding:11px 12px; box-shadow:0 3px 12px #0002; }
        .extra-item input { width:18px; min-height:18px; accent-color:var(--hh-accent); flex:none; }
        .extra-copy { flex:1; min-width:0; }
        .extra-name { display:block; color:#fff; font-size:13.5px; font-weight:800; line-height:1.25; }
        .extra-detail { display:block; color:#d5a27f; font-size:11px; margin-top:4px; line-height:1.25; }
        .extra-badge { display:inline-block; color:#21170e; background:#ffb37d; border-radius:999px; padding:2px 7px; font-size:9px; font-weight:900; letter-spacing:.04em; margin-bottom:5px; }
        .extra-price { color:#ffab72; font-size:13px; font-weight:750; white-space:nowrap; }
        .extra-qty { width:58px; min-height:34px; padding:6px; font-size:13px; }
        .extras-total { color:#ffbd91; font-size:12px; margin-top:10px; text-align:right; }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="{{ route('catalog.index') }}">← Volver al catálogo</a>
        <div class="brand">HH MOTEL</div>
        <h1>Reserva tu Playroom</h1>
        <p class="sub">Elige tu fecha y horario. Te mostraremos una opción disponible y recibirás la confirmación al finalizar.</p>

        @if ($errors->any())
            <div class="errors">
                @foreach (array_unique($errors->all()) as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
        <div class="steps" aria-label="Pasos de la reserva"><span>1. Estadía</span><span>2. Tus datos</span><span>3. Confirmación</span></div>
        <div class="hint-box">Horario: lunes a jueves 10:30 a 03:00 · viernes a domingo 10:30 a 22:30 corrido.</div>

        @if ($selectedCoupon)
            <div class="coupon-applied">
                <span>🏷️ Cupón aplicado: <strong>{{ $selectedCoupon->internal_name }}</strong></span>
                <a href="{{ route('catalog.reserve', request()->except('cupon')) }}">Quitar</a>
            </div>
            <div class="coupon-warning" id="coupon-warning"></div>
        @endif

        <form method="POST" action="{{ route('catalog.reserve.store') }}" id="reserve-form">
            @csrf
            <input type="hidden" name="room_id" value="{{ $selectedRoomId ?? '' }}">
            <input type="hidden" name="coupon_code" value="{{ $selectedCoupon->code ?? '' }}">
            <div class="honey-field" aria-hidden="true">
                <label for="website">No completar</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label for="room_category_id">Elige tu Playroom</label>
            <select id="room_category_id" name="room_category_id" required onchange="hhUpdateDurations()">
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('room_category_id', $selectedCategoryId) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>

            <div class="row2 row2-datetime">
                <div>
                    <label for="date">Fecha</label>
                    <input type="date" id="date" name="date" min="{{ now()->toDateString() }}" value="{{ old('date', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label for="time">Hora</label>
                    <div class="time-wheel" id="time-wheel" role="group" aria-label="Hora de llegada">
                        <div class="wheel-band"></div>
                        <ul class="wheel" id="wheel-hour" tabindex="0" aria-label="Hora"></ul>
                        <span class="wheel-sep">:</span>
                        <ul class="wheel" id="wheel-min" tabindex="0" aria-label="Minutos"></ul>
                    </div>
                    <div class="time-range" id="time-range"></div>
                    <input type="hidden" id="time-select" value="">
                    <input type="hidden" id="time" value="">
                    <input type="hidden" name="time_hour" id="time_hour" value="{{ old('time_hour') }}">
                    <input type="hidden" name="time_minute" id="time_minute" value="{{ old('time_minute') }}">
                </div>
            </div>

            <label for="duration_minutes">¿Cuánto tiempo quieres quedarte?</label>
            <select id="duration_minutes" name="duration_minutes" required></select>
            <div class="avail-status" id="avail-status" aria-live="polite"></div>

            <label for="guests_count">Cantidad de personas</label>
            <input type="number" id="guests_count" name="guests_count" min="1" max="10" value="{{ old('guests_count', 2) }}" required>

            @if ($publicUpsells->isNotEmpty() || $categoryUpsells->isNotEmpty())
                <section class="extras" aria-labelledby="extras-title">
                    <h2 id="extras-title">Aprovecha esta oportunidad exclusiva</h2>
                    <p>Extras y combos con precio preferencial al reservar online. Agrégalos ahora y disfruta más tu experiencia.</p>
                    <div class="extra-list">
                        @foreach ($publicUpsells as $upsell)
                            @php $combo = $upsell->combo; @endphp
                            <label class="extra-item">
                                <input type="checkbox" name="combo_quantities[{{ $combo->id }}]" value="1" data-extra-price="{{ $combo->price }}" data-extra-toggle>
                                <span class="extra-copy"><span class="extra-badge">OPORTUNIDAD ONLINE</span><span class="extra-name">{{ $upsell->name }}</span><span class="extra-detail">{{ $combo->description ?: 'Combo especial' }}</span></span>
                                <span class="extra-price">+ ${{ number_format($combo->price, 0, ',', '.') }}</span>
                            </label>
                        @endforeach
                        @foreach ($categoryUpsells as $upsell)
                            <label class="extra-item">
                                <input type="checkbox" name="accepted_upsells[]" value="{{ $upsell->id }}" data-extra-price="{{ $upsell->price }}" data-extra-toggle>
                                <span class="extra-copy"><span class="extra-badge">OPORTUNIDAD ONLINE</span><span class="extra-name">{{ $upsell->name }}</span><span class="extra-detail">Pasa de {{ $upsell->fromCategory?->name }} a {{ $upsell->toCategory?->name }} por un adicional preferencial</span></span>
                                <span class="extra-price">+ ${{ number_format($upsell->price, 0, ',', '.') }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="extras-total" id="extras-total">Sin extras seleccionados</div>
                </section>
            @endif

            <div class="row2">
                <div>
                    <label for="first_name">Nombre</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                </div>
                <div>
                    <label for="last_name">Apellido</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                </div>
            </div>

            <label for="phone">Teléfono / WhatsApp</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+56 9 1234 5678" required>

            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="tucorreo@ejemplo.com">

            @if ($selectedCoupon && $selectedCoupon->min_age)
                <label for="birth_date">Fecha de nacimiento <span style="color:#9edaff; font-weight:400;">— para aplicar el cupón ({{ $selectedCoupon->min_age }}+ años)</span></label>
                <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}" required>
            @endif

            <div class="summary" id="booking-summary" aria-live="polite"><strong>Resumen de tu reserva</strong><small>Selecciona Playroom, fecha, hora y duración para ver el detalle.</small></div>
            <button class="submit" type="submit">Solicitar reserva</button>
            <p class="legal">Al reservar aceptas presentar tu documento de identidad al llegar. HH se reserva el derecho de admisión.</p>
        </form>
        </div>
    </div>
    <script>
        const hhDurationsByCategory = @json($durationsByCategory->mapWithKeys(fn ($v, $k) => [(string) $k => $v]));
        const hhCouponConstraints = @json($couponConstraints);
        const hhDurationLabel = (min) => min >= 60 ? (min / 60) + ' h' : min + ' min';

        function hhUpdateDurations() {
            const catId = document.getElementById('room_category_id').value;
            const select = document.getElementById('duration_minutes');
            const options = hhDurationsByCategory[catId] || [];
            const prevValue = '{{ old('duration_minutes') }}';
            select.innerHTML = '';
            options.forEach((min) => {
                const opt = document.createElement('option');
                opt.value = min;
                opt.textContent = hhDurationLabel(min);
                if (String(min) === prevValue) opt.selected = true;
                select.appendChild(opt);
            });
        }

        // Una sola lista de horas en formato 24 h (cada 15 minutos). Las de hoy que ya pasaron no se ofrecen.
        function hhTimeLabel(h, m) {
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        // Horas realmente ofrecidas ese día para esa categoría (según el horario de las tarifas).
        // Si la consulta falla, se muestra la lista completa y la disponibilidad se valida igual al elegir.
        let hhAllowedTimes = null;
        async function hhLoadTimes() {
            const date = document.getElementById('date').value;
            const category = document.getElementById('room_category_id').value;
            hhAllowedTimes = null;
            if (date && category) {
                try {
                    const r = await fetch('{{ route('catalog.reserve.times') }}?' + new URLSearchParams({ date: date, room_category_id: category }), { headers: { 'Accept': 'application/json' } });
                    if (r.ok) hhAllowedTimes = (await r.json()).times;
                } catch (e) { /* se usa la lista completa */ }
            }
            hhBuildTimes();
        }
        // Selector tipo app: dos ruedas (hora y minutos) que se deslizan con el dedo y se detienen en el valor.
        // Solo ofrece horas dentro del horario de atención de ese día y categoría.
        let hhTimeList = [];
        const HH_ITEM = 40;
        const hhToMin = (v) => { const p = v.split(':').map(Number); return p[0] * 60 + p[1]; };
        const hhFromMin = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
        const hhPad = (n) => String(n).padStart(2, '0');
        let hhWheelBusy = false;

        function hhFillWheel(ul, values, selected) {
            ul.innerHTML = '';
            values.forEach(function (v) {
                const li = document.createElement('li');
                li.dataset.v = v;
                li.textContent = v;
                li.addEventListener('click', function () { ul.scrollTo({ top: values.indexOf(v) * HH_ITEM, behavior: 'smooth' }); });
                ul.appendChild(li);
            });
            const idx = Math.max(0, values.indexOf(selected));
            hhWheelBusy = true;
            ul.scrollTop = idx * HH_ITEM;
            setTimeout(function () { hhWheelBusy = false; }, 60);
            hhMarkWheel(ul, idx);
        }
        function hhMarkWheel(ul, idx) {
            ul.querySelectorAll('li').forEach(function (li, i) { li.classList.toggle('sel', i === idx); });
        }
        function hhWheelIndex(ul) {
            return Math.max(0, Math.min(ul.children.length - 1, Math.round(ul.scrollTop / HH_ITEM)));
        }
        function hhHours() { return [...new Set(hhTimeList.map((v) => v.slice(0, 2)))]; }
        function hhMinutesOf(hour) { return hhTimeList.filter((v) => v.startsWith(hour + ':')).map((v) => v.slice(3)); }

        // Lee las ruedas y deja la hora elegida en el formulario.
        function hhCommitWheels(notify) {
            const hourUl = document.getElementById('wheel-hour');
            const minUl = document.getElementById('wheel-min');
            const hour = hourUl.children[hhWheelIndex(hourUl)]?.dataset.v;
            const minutesNow = hhMinutesOf(hour);
            if (!hour || !minutesNow.length) return;
            // Si cambió la hora, los minutos válidos pueden ser otros: se acomoda al más cercano.
            const prevMin = minUl.children[hhWheelIndex(minUl)]?.dataset.v;
            if (minUl.children.length !== minutesNow.length || [...minUl.children].some((li, i) => li.dataset.v !== minutesNow[i])) {
                const nearest = minutesNow.reduce((a, b) => Math.abs(Number(b) - Number(prevMin || 0)) < Math.abs(Number(a) - Number(prevMin || 0)) ? b : a);
                hhFillWheel(minUl, minutesNow, nearest);
            }
            const minute = minUl.children[hhWheelIndex(minUl)]?.dataset.v;
            hhMarkWheel(hourUl, hhWheelIndex(hourUl));
            hhMarkWheel(minUl, hhWheelIndex(minUl));
            const value = hour + ':' + minute;
            const input = document.getElementById('time-select');
            if (input.value !== value) {
                input.value = value;
                if (notify) input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        function hhWheelListener(ul) {
            let timer = null;
            ul.addEventListener('scroll', function () {
                if (hhWheelBusy) return;
                hhMarkWheel(ul, hhWheelIndex(ul));
                clearTimeout(timer);
                timer = setTimeout(function () { hhCommitWheels(true); }, 110);
            });
            ul.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                e.preventDefault();
                ul.scrollBy({ top: (e.key === 'ArrowDown' ? 1 : -1) * HH_ITEM, behavior: 'smooth' });
            });
        }

        function hhBuildTimes() {
            const input = document.getElementById('time-select');
            if (!input) return;
            const dateValue = document.getElementById('date').value;
            const now = new Date();
            const todayIso = now.getFullYear() + '-' + hhPad(now.getMonth() + 1) + '-' + hhPad(now.getDate());
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            const all = [];
            for (let t = 0; t < 24 * 60; t += 15) all.push(hhFromMin(t));
            hhTimeList = (hhAllowedTimes || all).filter((v) => dateValue !== todayIso || hhToMin(v) >= nowMinutes - 5);

            const wheel = document.getElementById('time-wheel');
            const range = document.getElementById('time-range');
            range.classList.remove('err');
            wheel.classList.toggle('empty', !hhTimeList.length);
            if (!hhTimeList.length) {
                range.textContent = hhAllowedTimes ? 'Sin horarios disponibles para esta fecha.' : '';
                document.getElementById('wheel-hour').innerHTML = '';
                document.getElementById('wheel-min').innerHTML = '';
                if (input.value) {
                    input.value = '';
                    document.getElementById('time_hour').value = '';
                    document.getElementById('time_minute').value = '';
                    hhUpdateSummary(); hhCheckAvailability();
                }
                return;
            }
            range.textContent = 'Horarios disponibles: ' + hhTimeList[0] + ' a ' + hhTimeList[hhTimeList.length - 1] + ' · desliza para elegir';

            // Hora inicial: la que estaba elegida si sigue disponible; si no, la primera del día (desde las 10:00).
            let target = input.value && hhTimeList.includes(input.value) ? input.value : (hhTimeList.find((v) => hhToMin(v) >= 600) || hhTimeList[0]);
            const hour = target.slice(0, 2);
            hhFillWheel(document.getElementById('wheel-hour'), hhHours(), hour);
            hhFillWheel(document.getElementById('wheel-min'), hhMinutesOf(hour), target.slice(3));
            const changed = input.value !== target;
            input.value = target;
            if (changed) { input.dispatchEvent(new Event('change', { bubbles: true })); }
        }
        hhWheelListener(document.getElementById('wheel-hour'));
        hhWheelListener(document.getElementById('wheel-min'));

        function hhSyncTime() {
            const value = document.getElementById('time-select').value;
            if (!value) return;
            const [hour, minute] = value.split(':').map(Number);
            document.getElementById('time').value = value;
            document.getElementById('time_hour').value = hour;
            document.getElementById('time_minute').value = minute;
        }

        function hhCheckCouponWindow() {
            if (!hhCouponConstraints) return;
            const warning = document.getElementById('coupon-warning');
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            if (!date || !time) { warning.classList.remove('show'); return; }
            // new Date('YYYY-MM-DDT12:00:00').getDay() da 0=Dom..6=Sáb, igual
            // que allowed_weekdays en el modelo -- por eso el mediodía fijo,
            // para no depender de la zona horaria del navegador.
            const weekday = new Date(date + 'T12:00:00').getDay();
            const weekdayOk = hhCouponConstraints.weekdays.length === 0 || hhCouponConstraints.weekdays.includes(weekday);
            const timeOk = !hhCouponConstraints.timeStart || !hhCouponConstraints.timeEnd || (time >= hhCouponConstraints.timeStart && time <= hhCouponConstraints.timeEnd);
            if (weekdayOk && timeOk) {
                warning.classList.remove('show');
            } else {
                warning.textContent = `⚠️ El cupón aplica solo ${hhCouponConstraints.label} — con la fecha/hora elegida no vas a poder usarlo.`;
                warning.classList.add('show');
            }
        }

        // Antes el cliente solo se enteraba de que no había disponibilidad
        // DESPUÉS de completar nombre/teléfono/email y enviar todo el
        // formulario -- ahora se chequea en vivo apenas elige categoría,
        // fecha, hora y duración, con un debounce para no pegarle al
        // servidor en cada tecla/cambio.
        let hhAvailTimer = null;
        let hhAvailSeq = 0;
        function hhCheckAvailability() {
            const status = document.getElementById('avail-status');
            const catId = document.getElementById('room_category_id').value;
            const date = document.getElementById('date').value;
            const duration = document.getElementById('duration_minutes').value;
            const timeHour = document.getElementById('time_hour').value;
            const timeMinute = document.getElementById('time_minute').value;
            clearTimeout(hhAvailTimer);
            if (!catId || !date || !duration || timeHour === '' || timeMinute === '') {
                status.classList.remove('show');
                return;
            }
            const mySeq = ++hhAvailSeq;
            status.className = 'avail-status show checking';
            status.innerHTML = '<span class="spin"></span> Verificando disponibilidad…';
            hhAvailTimer = setTimeout(() => {
                const params = new URLSearchParams({
                    room_category_id: catId, date, duration_minutes: duration,
                    time_hour: timeHour, time_minute: timeMinute,
                });
                const roomId = document.querySelector('input[name="room_id"]').value;
                if (roomId) params.set('room_id', roomId);
                fetch('{{ route('catalog.reserve.availability') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.ok ? r.json() : Promise.reject())
                    .then((data) => {
                        if (mySeq !== hhAvailSeq) return;
                        if (data.available) {
                            status.className = 'avail-status show ok';
                            status.textContent = '✓ Hay disponibilidad para ese horario';
                        } else {
                            status.className = 'avail-status show no';
                            status.textContent = '✗ No hay disponibilidad para ese horario — prueba otra fecha, hora o duración';
                        }
                    })
                    .catch(() => { if (mySeq === hhAvailSeq) status.classList.remove('show'); });
            }, 450);
        }

        function hhUpdateSummary() {
            const cat = document.getElementById('room_category_id');
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            const duration = document.getElementById('duration_minutes').selectedOptions[0]?.textContent;
            const summary = document.getElementById('booking-summary');
            if (!cat.value || !date || !time || !duration) return;
            const formatted = new Date(date + 'T12:00:00').toLocaleDateString('es-CL', {day:'2-digit', month:'2-digit', year:'numeric'});
            summary.innerHTML = `<strong>${cat.selectedOptions[0].textContent} · ${duration}</strong><small>${formatted} a las ${time} · disponibilidad se confirma al enviar</small>`;
        }

        function hhUpdateExtrasTotal() {
            let total = 0;
            document.querySelectorAll('[data-extra-toggle]:checked').forEach((input) => { total += Number(input.dataset.extraPrice || 0); });
            const el = document.getElementById('extras-total');
            if (el) el.textContent = total ? `Extras seleccionados: + $${total.toLocaleString('es-CL')}` : 'Sin extras seleccionados';
        }

        document.getElementById('reserve-form').addEventListener('submit', hhSyncTime);
        document.getElementById('reserve-form').addEventListener('submit', function (e) {
            if (!document.getElementById('time-select').value) {
                e.preventDefault();
                const range = document.getElementById('time-range');
                range.textContent = 'Elige la hora de llegada.';
                range.classList.add('err');
                document.getElementById('time-wheel').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        // Un solo envío: sin esto, un doble toque creaba dos reservas en dos habitaciones.
        (function () {
            const form = document.getElementById('reserve-form');
            const btn = form.querySelector('button.submit');
            let sending = false;
            form.addEventListener('submit', function (e) {
                if (sending) { e.preventDefault(); return; }
                sending = true;
                if (btn) { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Enviando…'; }
            });
            // Al volver atrás desde la confirmación, el botón debe quedar usable otra vez.
            window.addEventListener('pageshow', function () {
                sending = false;
                if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Solicitar reserva'; }
            });
        })();
        ['room_category_id','date','time-select','duration_minutes'].forEach(id => document.getElementById(id).addEventListener('change', () => { hhSyncTime(); hhUpdateSummary(); hhCheckCouponWindow(); hhCheckAvailability(); }));
        hhBuildTimes();
        // Si el formulario vuelve con una hora ya elegida (por un error al enviar), se deja puesta.
        (function () {
            const oh = document.getElementById('time_hour').value;
            if (oh !== '') {
                document.getElementById('time-select').value = String(parseInt(oh, 10)).padStart(2, '0') + ':' + String(parseInt(document.getElementById('time_minute').value || '0', 10)).padStart(2, '0');
            }
        })();
        hhBuildTimes();
        hhLoadTimes();
        document.getElementById('date').addEventListener('change', hhLoadTimes);
        document.getElementById('room_category_id').addEventListener('change', hhLoadTimes);
        hhUpdateDurations();
        hhUpdateSummary();
        hhCheckCouponWindow();
        hhCheckAvailability();
        document.querySelectorAll('[data-extra-toggle]').forEach((input) => input.addEventListener('change', hhUpdateExtrasTotal));
        hhUpdateExtrasTotal();
    </script>
</body>
</html>
