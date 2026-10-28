<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Reservar — HH Motel</title>
    @include('partials.social-meta', ['shareTitle' => 'Reservar — HH Motel Santiago', 'shareDescription' => 'Elige tu playroom, fecha y duración, y confirma tu reserva en línea en HH Motel Santiago.'])
    @include('catalog._app-head')
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
        .wrap { max-width: 520px; margin: 0 auto; padding: 18px 16px calc(40px + var(--safe-b)); }
        a.back { color:#62656c; text-decoration:none; font-size:13px; display:inline-block; margin-bottom: 18px; }
        .brand { font-size: 13px; font-weight: 800; letter-spacing: .1em; color: var(--hh-accent); margin-bottom: 8px; }
        h1 { font-size: 25px; margin: 0 0 6px; letter-spacing: -.03em; }
        p.sub { color:#62656c; font-size: 13.5px; margin: 0 0 24px; }
        .card { background:var(--hh-surface); color:#f5f5f5; border-radius:12px; box-shadow: 0 3px 10px #1011120b; padding: 22px 22px 26px; }
        .hint-box { background:#1c2f1c; border:1px solid #2e5a2e; color:#8fe0ad; border-radius:9px; padding:12px 14px; font-size:12.5px; margin-bottom:22px; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        .steps { display:flex; gap:8px; margin: 0 0 18px; font-size:11px; font-weight:700; color:#8a8d93; text-transform:uppercase; letter-spacing:.06em; }
        .steps button { flex:1; padding:8px 4px; border:0; border-bottom:2px solid #4a4d53; background:none; color:#8a8d93; font:inherit; text-transform:inherit; letter-spacing:inherit; text-align:center; cursor:default; }
        .steps button.done { color:#c1c3c7; border-color:#8a8d93; cursor:pointer; }
        .steps button.on { color:var(--hh-accent); border-color:var(--hh-accent); }
        .step[hidden], [data-only-step][hidden] { display:none !important; }
        .step-title { font-size:17px; font-weight:800; margin:4px 0 2px; color:#fff; }
        .step-back { display:inline-block; margin-top:14px; background:none; border:0; padding:6px 0; color:#c1c3c7; font-size:13px; text-decoration:underline; cursor:pointer; }
        .recap { display:flex; justify-content:space-between; align-items:center; gap:10px; background:#202123; border:1px solid #4a4d53; border-radius:9px; padding:10px 12px; font-size:13px; color:#e4e5e7; margin-bottom:6px; }
        .recap button { flex:none; background:none; border:0; color:#ff9a4d; font-size:12.5px; font-weight:700; text-decoration:underline; cursor:pointer; padding:0; }
        .step-error { display:none; margin-top:12px; padding:10px 12px; border-radius:8px; background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; font-size:13px; }
        .step-error.show { display:block; }
        label { display:block; font-size: 12.5px; color:#c1c3c7; margin: 16px 0 6px; }
        input, select { width:100%; box-sizing:border-box; background:#202123; border:1px solid #62656b; color:#f4f5f7; padding:11px 12px; border-radius:8px; font-size:15px; min-height:46px; }
        input[type="date"], input[type="time"] { color-scheme: dark; }
        /* En iOS/Safari los campos de fecha y hora tienen un ancho propio y se
           salían del rectángulo: se les quita el estilo nativo para que
           respeten el 100% del contenedor (el selector de fecha/hora del
           teléfono se abre igual al tocarlos). */
        input[type="date"], input[type="time"] { -webkit-appearance:none; appearance:none; display:block; width:100%; max-width:100%; min-width:0; margin:0; }
        .card, .card form, .card label, .card > * { min-width:0; }
        .people-stepper { display:grid; grid-template-columns:60px 1fr 60px; gap:8px; align-items:stretch; }
        .people-stepper button { min-height:54px; border-radius:12px; border:1px solid #4a4d53; background:#26272a; color:#f4f5f7; font-size:28px; font-weight:700; line-height:1; cursor:pointer; -webkit-tap-highlight-color:transparent; touch-action:manipulation; }
        .people-stepper button:active:not(:disabled) { background:#ff7918; border-color:#ff7918; color:#21170e; }
        .people-stepper button:disabled { opacity:.3; cursor:not-allowed; }
        .people-stepper input { text-align:center; font-size:24px; font-weight:800; min-height:54px; -moz-appearance:textfield; appearance:textfield; }
        .people-stepper input::-webkit-inner-spin-button, .people-stepper input::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
        .tp { display:flex; align-items:center; justify-content:center; gap:10px; padding:12px; border:1px solid #4a4d53; border-radius:12px; background:#1b1c1e; }
        .tp-col { display:grid; grid-template-rows:auto auto auto; gap:6px; width:96px; }
        .tp-col button { min-height:46px; border-radius:10px; border:1px solid #4a4d53; background:#26272a; color:#f4f5f7; font-size:26px; font-weight:700; line-height:1; cursor:pointer; -webkit-tap-highlight-color:transparent; touch-action:manipulation; user-select:none; }
        .tp-col button:active:not(:disabled) { background:#ff7918; border-color:#ff7918; color:#21170e; }
        .tp-col button:disabled { opacity:.25; cursor:not-allowed; }
        .tp-col output { display:flex; align-items:center; justify-content:center; min-height:62px; border-radius:10px; background:#202123; border:2px solid #62656b; font-size:34px; font-weight:800; font-variant-numeric:tabular-nums; color:#f4f5f7; }
        .tp.set .tp-col output { border-color:var(--hh-accent); }
        .tp-colon { font-size:34px; font-weight:800; color:#8a8d93; }
        .tp-note { text-align:center; font-size:12.5px; color:#ff9a4d; font-weight:600; margin-top:6px; min-height:16px; }
        .tp-empty { flex:1; text-align:center; font-size:13px; color:#8a8d93; padding:10px 0; }
        .tp.missing { border-color:#e5747f; box-shadow:0 0 0 3px rgba(229,116,127,.2); }
        .time-range { font-size:12px; color:#8a8d93; margin-top:6px; min-height:16px; }
        .time-range.err { color:#f3b8b8; }
        .time-parts { display:grid; grid-template-columns:1fr 1fr 1fr; gap:7px; }
        input:focus, select:focus { outline:none; border-color:var(--hh-accent); box-shadow: 0 0 0 3px #ff791833; }
        .row2 { display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap:12px; }
        .field-note { display:block; margin-top:6px; font-size:11.5px; color:#8a8d93; line-height:1.4; }
        .honey-field { position:absolute; left:-9999px; top:-9999px; }
        button.submit { position:sticky; bottom:calc(10px + var(--safe-b)); z-index:30; box-shadow:0 10px 28px rgba(0,0,0,.45); min-height:56px; width:100%; margin-top:26px; background:var(--hh-accent); color:#21170e; border:none; padding:14px; border-radius:9px; font-size:15px; font-weight:750; cursor:pointer; }
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
        .avail-status.short { background:#2e2410; border:1px solid #c98a1b; color:#f3d9a4; display:block; line-height:1.45; }
        .avail-status.short b { color:#fff; }
        .short-accept { display:flex; gap:9px; align-items:center; margin-top:8px; padding:10px 12px; border-radius:8px; border:1px solid #c98a1b; color:#fff; font-weight:650; font-size:13.5px; cursor:pointer; }
        .short-accept[hidden] { display:none; }
        .short-accept input { width:18px; height:18px; flex:none; }
        .avail-status .spin { width:11px; height:11px; border-radius:50%; border:2px solid currentColor; border-top-color:transparent; animation:hh-spin .7s linear infinite; flex:none; }
        @keyframes hh-spin { to { transform:rotate(360deg); } }
        .extras { margin-top:14px; padding-top:4px; }
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
    @include('catalog._app-bar', ['appBack' => route('catalog.index'), 'appTitle' => 'Reservar'])
    <div class="wrap">
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
        @php
            $hasExtras = $catalogCombos->isNotEmpty() || $categoryUpsells->isNotEmpty();
            $stepNames = $hasExtras ? ['Estadía', 'Extras', 'Tus datos'] : ['Estadía', 'Tus datos'];
            // Si el formulario vuelve con errores, se abre el paso donde está el problema.
            $dataFields = ['first_name', 'last_name', 'phone', 'email', 'birth_date'];
            $startStep = 1;
            if ($errors->any()) {
                $startStep = collect($errors->keys())->every(fn ($k) => in_array($k, $dataFields, true)) ? count($stepNames) : 1;
            }
        @endphp
        <div class="steps" id="steps" aria-label="Pasos de la reserva">
            @foreach ($stepNames as $i => $stepName)
                <button type="button" data-goto="{{ $i + 1 }}">{{ $i + 1 }}. {{ $stepName }}</button>
            @endforeach
        </div>
        <div class="hint-box" data-only-step="1">Horario: lunes a jueves 10:30 a 03:00 · viernes a domingo 10:30 a 22:30 corrido.</div>

        @if ($selectedCoupon)
            <div class="coupon-applied" data-only-step="1">
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

            <div class="step" data-step="1">
            <label for="room_category_id">Elige tu Playroom</label>
            <select id="room_category_id" name="room_category_id" required onchange="hhUpdateDurations()">
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('room_category_id', $selectedCategoryId) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>

            <label for="date">Fecha</label>
            <input type="date" id="date" name="date" min="{{ now()->toDateString() }}" value="{{ old('date', $prefill['date'] ?? now()->toDateString()) }}" required>

            {{-- Hora: hora y minutos con + / −, que solo recorren los horarios disponibles de ese día. --}}
            <label>Hora de llegada</label>
            <div class="tp" id="time-picker">
                <div class="tp-col">
                    <button type="button" data-tp="h" data-d="1" aria-label="Hora siguiente">+</button>
                    <output id="tp-h" aria-label="Hora">--</output>
                    <button type="button" data-tp="h" data-d="-1" aria-label="Hora anterior">−</button>
                </div>
                <div class="tp-colon">:</div>
                <div class="tp-col">
                    <button type="button" data-tp="m" data-d="1" aria-label="Más minutos">+</button>
                    <output id="tp-m" aria-label="Minutos">--</output>
                    <button type="button" data-tp="m" data-d="-1" aria-label="Menos minutos">−</button>
                </div>
            </div>
            <div class="tp-note" id="tp-note"></div>
            <div class="time-range" id="time-range"></div>
            <input type="hidden" id="time-select" value="">
            <input type="hidden" id="time" value="">
            <input type="hidden" name="time_hour" id="time_hour" value="{{ old('time_hour', $prefill['time']['hour'] ?? '') }}">
            <input type="hidden" name="time_minute" id="time_minute" value="{{ old('time_minute', $prefill['time']['minute'] ?? '') }}">

            <label for="duration_minutes">¿Cuánto tiempo quieres quedarte?</label>
            <select id="duration_minutes" name="duration_minutes" required></select>
            <div class="avail-status" id="avail-status" aria-live="polite"></div>
            <label class="short-accept" id="short-accept" hidden>
                <input type="checkbox" name="short_stay_accept" value="1" id="short-accept-input" @checked(old('short_stay_accept'))>
                <span id="short-accept-text">Acepto salir a la hora de cierre</span>
            </label>

            <label for="guests_count">Cantidad de personas</label>
            <div class="people-stepper" id="people-stepper">
                <button type="button" data-d="-1" aria-label="Una persona menos">−</button>
                <input type="number" id="guests_count" name="guests_count" min="1" max="10" value="{{ old('guests_count', 2) }}" required inputmode="numeric" aria-live="polite">
                <button type="button" data-d="1" aria-label="Una persona más">+</button>
            </div>

            <div class="step-error" id="step1-error" role="alert"></div>
            <button class="submit" type="button" data-next>Continuar →</button>
            </div>

            @if ($hasExtras)
                <div class="step" data-step="2" hidden>
                <div class="recap"><span data-recap></span><button type="button" data-goto="1">Cambiar</button></div>
                <section class="extras" aria-labelledby="extras-title">
                    <h2 id="extras-title">Aprovecha esta oportunidad exclusiva</h2>
                    <p>Extras y combos con precio preferencial al reservar online. Agrégalos ahora y disfruta más tu experiencia.</p>
                    <div class="extra-list">
                        @foreach ($catalogCombos as $combo)
                            <label class="extra-item">
                                <input type="checkbox" name="combo_quantities[{{ $combo->id }}]" data-combo-id="{{ $combo->id }}" data-choices="{{ json_encode($combo->choicesSpec()) }}" data-choices-public value="1" data-extra-price="{{ $combo->price }}" data-extra-toggle>
                                <span class="extra-copy"><span class="extra-badge">OPORTUNIDAD ONLINE</span><span class="extra-name">{{ $combo->name }}</span><span class="extra-detail">{{ $combo->description ?: ($combo->itemsLabel() ?: 'Combo especial') }}</span></span>
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
                <button class="submit" type="button" data-next>Continuar →</button>
                <button type="button" class="step-back" data-goto="1">← Volver</button>
                </div>
            @endif

            <div class="step" data-step="{{ count($stepNames) }}" hidden>
            <div class="recap"><span data-recap></span><button type="button" data-goto="1">Cambiar</button></div>
            <div class="step-title">Tus datos</div>
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
            <small class="field-note">Te escribiremos por WhatsApp para confirmar tu reserva y avisarte si queda algo pendiente.</small>

            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="tucorreo@ejemplo.com">

            @if ($selectedCoupon && $selectedCoupon->min_age)
                <label for="birth_date">Fecha de nacimiento <span style="color:#9edaff; font-weight:400;">— para aplicar el cupón ({{ $selectedCoupon->min_age }}+ años)</span></label>
                <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}" required>
            @endif

            <div class="summary" id="booking-summary" aria-live="polite"><strong>Resumen de tu reserva</strong><small>Selecciona Playroom, fecha, hora y duración para ver el detalle.</small></div>
            <button class="submit" type="submit">Solicitar reserva</button>
            <button type="button" class="step-back" data-goto="{{ count($stepNames) - 1 }}">← Volver</button>
            <p class="legal">Al reservar aceptas presentar tu documento de identidad al llegar. HH se reserva el derecho de admisión.</p>
            </div>
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
            const prevValue = '{{ old('duration_minutes', $prefill['duration'] ?? '') }}';
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
            hhTimesLoading = true;
            hhBuildTimes();
            if (date && category) {
                try {
                    const r = await fetch('{{ route('catalog.reserve.times') }}?' + new URLSearchParams({ date: date, room_category_id: category }), { headers: { 'Accept': 'application/json' } });
                    if (r.ok) hhAllowedTimes = (await r.json()).times;
                } catch (e) { /* se usa la lista completa */ }
            }
            hhTimesLoading = false;
            hhBuildTimes();
        }
        // Selector de hora con botones: solo aparecen las horas del horario de atención de ese día
        // (cada 15 minutos), así no se puede elegir una hora que después el sistema rechace.
        let hhTimeList = [];
        let hhTimesLoading = false;
        const hhToMin = (v) => { const p = v.split(':').map(Number); return p[0] * 60 + p[1]; };
        const hhFromMin = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
        function hhTimeOk(value) {
            return !!value && (!hhAllowedTimes || hhTimeList.includes(value));
        }
        function hhOpeningText() {
            if (!hhTimeList.length) return hhAllowedTimes ? 'Sin horarios disponibles para esta fecha.' : '';
            // Tramos continuos (ej. 00:00–02:00 y 10:30–23:45) para que se entienda el horario.
            const runs = [];
            let start = hhTimeList[0], prev = hhTimeList[0];
            for (let i = 1; i < hhTimeList.length; i++) {
                if (hhToMin(hhTimeList[i]) - hhToMin(prev) > 15) { runs.push(start + ' a ' + prev); start = hhTimeList[i]; }
                prev = hhTimeList[i];
            }
            runs.push(start + ' a ' + prev);
            return 'Horario disponible: ' + runs.join(' y ');
        }
        function hhBuildTimes() {
            const input = document.getElementById('time-select');
            if (!input) return;
            const dateValue = document.getElementById('date').value;
            const now = new Date();
            const todayIso = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            const all = [];
            for (let t = 0; t < 24 * 60; t += 15) all.push(hhFromMin(t));
            hhTimeList = (hhAllowedTimes || all).filter((v) => dateValue !== todayIso || hhToMin(v) >= nowMinutes - 5);
            hhValidateTime(false);
            hhRenderPicker();
        }
        // Elige una hora: actualiza el campo y avisa al resto del formulario (resumen, disponibilidad).
        function hhSetTime(value) {
            const input = document.getElementById('time-select');
            input.value = value;
            document.getElementById('time-picker').classList.remove('missing');
            input.dispatchEvent(new Event('change', { bubbles: true }));
            hhRenderPicker();
        }
        // + / − de hora: salta a la hora disponible siguiente/anterior, con los mismos minutos si se puede.
        // + / − de minutos: avanza de 15 en 15 por la lista (pasa a la hora siguiente al llegar a :45).
        // El primer toque, sin hora elegida, pone la primera hora del día (no la madrugada) o la última con −.
        function hhStepTime(part, d) {
            if (!hhTimeList.length || hhTimesLoading) return;
            const value = document.getElementById('time-select').value;
            const i = hhTimeList.indexOf(value);
            if (i < 0) { hhSetTime(d > 0 ? (hhTimeList.find((v) => Number(v.slice(0, 2)) >= 6) || hhTimeList[0]) : hhTimeList[hhTimeList.length - 1]); return; }
            if (part === 'm') {
                const next = hhTimeList[i + d];
                if (next) hhSetTime(next);
                return;
            }
            const hours = [];
            hhTimeList.forEach((v) => { const h = v.slice(0, 2); if (!hours.includes(h)) hours.push(h); });
            const h = hours[hours.indexOf(value.slice(0, 2)) + d];
            if (!h) return;
            const inHour = hhTimeList.filter((v) => v.slice(0, 2) === h);
            const same = inHour.find((v) => v.slice(3) === value.slice(3));
            // Sin los mismos minutos, el más cercano dentro de esa hora.
            hhSetTime(same || inHour.reduce((a, b) => Math.abs(hhToMin(b) - hhToMin(value)) < Math.abs(hhToMin(a) - hhToMin(value)) ? b : a));
        }
        document.querySelectorAll('[data-tp]').forEach((b) => b.addEventListener('click', () => hhStepTime(b.dataset.tp, Number(b.dataset.d))));
        function hhRenderPicker() {
            const picker = document.getElementById('time-picker');
            const value = document.getElementById('time-select').value;
            const i = hhTimeList.indexOf(value);
            const empty = hhTimesLoading || !hhTimeList.length;
            let msg = picker.querySelector('.tp-empty');
            if (empty) {
                if (!msg) { msg = document.createElement('div'); msg.className = 'tp-empty'; picker.appendChild(msg); }
                msg.textContent = hhTimesLoading ? 'Cargando horarios…' : 'No quedan horarios para esta fecha. Prueba otro día.';
            } else if (msg) {
                msg.remove();
            }
            picker.querySelectorAll('.tp-col, .tp-colon').forEach((el) => { el.hidden = empty; });
            picker.classList.toggle('set', i >= 0);
            document.getElementById('tp-h').textContent = i >= 0 ? value.slice(0, 2) : '--';
            document.getElementById('tp-m').textContent = i >= 0 ? value.slice(3) : '--';
            const first = hhTimeList[0], last = hhTimeList[hhTimeList.length - 1];
            picker.querySelectorAll('[data-tp]').forEach((b) => {
                const up = Number(b.dataset.d) > 0;
                if (i < 0) { b.disabled = empty; return; }
                b.disabled = b.dataset.tp === 'm'
                    ? (up ? value === last : value === first)
                    : (up ? value.slice(0, 2) === last.slice(0, 2) : value.slice(0, 2) === first.slice(0, 2));
            });
            // Las horas de 00 a 05 son la madrugada de la fecha elegida: se avisa para que no se confunda.
            document.getElementById('tp-note').textContent = i >= 0 && Number(value.slice(0, 2)) < 6 ? 'De madrugada (después de la medianoche)' : '';
        }
        // Si la hora elegida ya no está disponible (cambió la fecha o la categoría), se borra y se pide otra.
        function hhValidateTime(fromUser) {
            const input = document.getElementById('time-select');
            const range = document.getElementById('time-range');
            range.classList.remove('err');
            if (input.value && !hhTimesLoading && !hhTimeOk(input.value)) {
                input.value = '';
                document.getElementById('time').value = '';
                document.getElementById('time_hour').value = '';
                document.getElementById('time_minute').value = '';
                range.classList.add('err');
                range.textContent = 'La hora que tenías no está disponible ese día. Elige otra.';
                hhUpdateSummary(); hhCheckAvailability();
            } else {
                range.textContent = hhTimesLoading ? '' : hhOpeningText();
            }
        }

        function hhSyncTime() {
            const value = document.getElementById('time-select').value;
            if (!value || !hhTimeOk(value)) return;
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
                hhShortStay(null);
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
                        hhShortStay(data.available ? data.short_stay : null);
                        if (data.available && data.short_stay) {
                            // Cerca del cierre: se vende hasta la hora de cierre, con precio menor.
                            const ss = data.short_stay;
                            const fmt = (n) => '$' + Number(n).toLocaleString('es-CL');
                            status.className = 'avail-status show short';
                            status.innerHTML = '⏱ <b>Estadía corta por cierre.</b> A esa hora quedan ' + ss.minutes_label + ' para el cierre: '
                                + 'sales a las <b>' + ss.closes_at + '</b> y pagas <b>' + fmt(ss.price) + '</b> en vez de ' + fmt(ss.full_price) + '.';
                        } else if (data.available) {
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

        // Muestra (y exige) la casilla "Acepto salir a las HH:MM" solo cuando aplica.
        var hhShortClose = null;
        function hhShortStay(ss) {
            const box = document.getElementById('short-accept');
            const input = document.getElementById('short-accept-input');
            hhShortClose = ss ? ss.closes_at : null;
            box.hidden = !ss;
            input.required = !!ss;
            if (ss) document.getElementById('short-accept-text').textContent = 'Acepto salir a las ' + ss.closes_at + ' (hora de cierre)';
            else input.checked = false;
            hhUpdateSummary();
        }

        function hhUpdateSummary() {
            const cat = document.getElementById('room_category_id');
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            const duration = document.getElementById('duration_minutes').selectedOptions[0]?.textContent;
            const summary = document.getElementById('booking-summary');
            if (!cat.value || !date || !time || !duration) return;
            const formatted = new Date(date + 'T12:00:00').toLocaleDateString('es-CL', {day:'2-digit', month:'2-digit', year:'numeric'});
            summary.innerHTML = `<strong>${cat.selectedOptions[0].textContent} · ${duration}</strong><small>${formatted} a las ${time}${hhShortClose ? ' · salida a las ' + hhShortClose : ''} · disponibilidad se confirma al enviar</small>`;
        }

        function hhUpdateExtrasTotal() {
            let total = 0;
            document.querySelectorAll('[data-extra-toggle]:checked').forEach((input) => { total += Number(input.dataset.extraPrice || 0); });
            const el = document.getElementById('extras-total');
            if (el) el.textContent = total ? `Extras seleccionados: + $${total.toLocaleString('es-CL')}` : 'Sin extras seleccionados';
        }

        // Personas: botones − y + (1 a 10), sin teclear.
        (function () {
            const input = document.getElementById('guests_count');
            const buttons = document.querySelectorAll('#people-stepper button');
            const paint = function () {
                const v = Number(input.value) || 1;
                buttons.forEach(function (b) { b.disabled = Number(b.dataset.d) < 0 ? v <= Number(input.min) : v >= Number(input.max); });
            };
            buttons.forEach(function (b) {
                b.addEventListener('click', function () {
                    const next = Math.min(Number(input.max), Math.max(Number(input.min), (Number(input.value) || 1) + Number(b.dataset.d)));
                    input.value = next;
                    paint();
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
            input.addEventListener('input', paint);
            paint();
        })();
        document.getElementById('reserve-form').addEventListener('submit', hhSyncTime);
        document.getElementById('reserve-form').addEventListener('submit', function (e) {
            if (!hhTimeOk(document.getElementById('time-select').value)) {
                e.preventDefault();
                const range = document.getElementById('time-range');
                range.textContent = 'Elige la hora de llegada.';
                range.classList.add('err');
                const picker = document.getElementById('time-picker');
                picker.classList.add('missing');
                picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        // Un solo envío: sin esto, un doble toque creaba dos reservas en dos habitaciones.
        (function () {
            const form = document.getElementById('reserve-form');
            const btn = form.querySelector('button[type="submit"]');
            let sending = false;
            form.addEventListener('submit', function (e) {
                if (sending) { e.preventDefault(); return; }
                // Si otra validación frenó el envío (ej. la hora), no se bloquea
                // el botón: antes quedaba en "Enviando…" sin haber enviado nada.
                if (e.defaultPrevented) return;
                sending = true;
                if (btn) { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Enviando…'; }
                // Red de seguridad: si la página no avanzó, se puede volver a intentar.
                setTimeout(function () {
                    sending = false;
                    if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Solicitar reserva'; }
                }, 20000);
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

    <script>
        // Reserva en pasos (estadía → extras → datos), todo en el mismo formulario: cambiar de paso
        // solo muestra/oculta secciones. El botón "atrás" del teléfono vuelve al paso anterior.
        (function () {
            const form = document.getElementById('reserve-form');
            const steps = Array.from(form.querySelectorAll('.step'));
            const last = steps.length;
            let current = 1;
            const fmtDate = (iso) => { const [y, m, d] = iso.split('-').map(Number); return new Date(y, m - 1, d).toLocaleDateString('es-CL', { weekday: 'short', day: 'numeric', month: 'short' }); };

            function recap() {
                const cat = document.getElementById('room_category_id');
                const dur = document.getElementById('duration_minutes');
                const date = document.getElementById('date').value;
                const time = document.getElementById('time-select').value;
                const guests = document.getElementById('guests_count').value;
                const text = [cat.selectedOptions[0]?.textContent, date ? fmtDate(date) : '', time, dur.selectedOptions[0]?.textContent, guests + (guests === '1' ? ' persona' : ' personas')].filter(Boolean).join(' · ');
                form.querySelectorAll('[data-recap]').forEach((el) => { el.textContent = text; });
            }

            function show(n, push) {
                current = Math.min(Math.max(1, n), last);
                steps.forEach((st) => { st.hidden = Number(st.dataset.step) !== current; });
                document.querySelectorAll('[data-only-step]').forEach((el) => { el.hidden = Number(el.dataset.onlyStep) !== current; });
                document.querySelectorAll('#steps [data-goto]').forEach((b) => {
                    const k = Number(b.dataset.goto);
                    b.classList.toggle('on', k === current);
                    b.classList.toggle('done', k < current);
                });
                recap();
                if (push) history.pushState({ hhStep: current }, '');
                document.querySelector('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function fail(msg, el) {
                const box = document.getElementById('step1-error');
                box.textContent = msg;
                box.classList.add('show');
                (el || box).scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            // Antes de seguir, el paso 1 tiene que estar completo y con disponibilidad.
            function validStep1() {
                document.getElementById('step1-error').classList.remove('show');
                if (!hhTimeOk(document.getElementById('time-select').value)) {
                    const picker = document.getElementById('time-picker');
                    const range = document.getElementById('time-range');
                    picker.classList.add('missing');
                    range.textContent = 'Elige la hora de llegada con los botones + / −.';
                    range.classList.add('err');
                    picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                for (const id of ['room_category_id', 'date', 'duration_minutes', 'guests_count']) {
                    const el = document.getElementById(id);
                    if (!el.checkValidity()) { el.reportValidity(); return false; }
                }
                const status = document.getElementById('avail-status');
                if (status.classList.contains('no')) return fail('No hay disponibilidad para ese horario. Prueba otra fecha, hora o duración.', status);
                if (status.classList.contains('checking')) return fail('Estamos revisando la disponibilidad, espera un segundo y vuelve a intentar.');
                const accept = document.getElementById('short-accept');
                if (!accept.hidden && !document.getElementById('short-accept-input').checked) return fail('Marca la casilla para aceptar la hora de salida.', accept);
                return true;
            }

            // Los combos "a elección" tienen que quedar elegidos antes de pasar a los datos.
            function validExtras() {
                const pending = Array.from(steps[1]?.querySelectorAll('.cc-box') || []).filter((b) => !b.querySelector('.cc-toggle.cc-done'));
                if (!pending.length) return true;
                pending.forEach((b) => b.classList.add('cc-missing', 'cc-open'));
                pending[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            form.querySelectorAll('[data-next]').forEach((b) => b.addEventListener('click', () => {
                if (current === 1 && !validStep1()) return;
                if (current === 2 && last === 3 && !validExtras()) return;
                show(current + 1, true);
            }));
            document.querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => {
                const k = Number(b.dataset.goto);
                if (k < current) show(k, true);
            }));
            window.addEventListener('popstate', (e) => show(e.state?.hhStep || 1, false));
            ['room_category_id', 'date', 'time-select', 'duration_minutes', 'guests_count'].forEach((id) => document.getElementById(id).addEventListener('change', () => document.getElementById('step1-error').classList.remove('show')));

            history.replaceState({ hhStep: {{ $startStep }} }, '');
            show({{ $startStep }}, false);
            window.scrollTo(0, 0);
        })();
    </script>
@include('partials.combo-choices')
</body>
</html>
