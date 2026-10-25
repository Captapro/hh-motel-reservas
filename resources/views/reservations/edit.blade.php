<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Modificar {{ $booking->code }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 520px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 20px; letter-spacing: .04em; margin-bottom: 4px; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 14px; margin-bottom: 6px; }
        .sub { color:#999; font-size: 13px; margin-bottom: 24px; }
        label { display:block; font-size: 12.5px; color:#bbb; margin: 16px 0 6px; }
        input, select, textarea { width:100%; box-sizing:border-box; background:#1c1c1c; border:1px solid #333; color:#fff; padding:10px 12px; border-radius:8px; font-size:15px; font-family:inherit; }
        .row { display:flex; gap:10px; }
        .row > div { flex:1; }
        button { width:100%; margin-top:26px; background:#ff7918; color:#fff; border:none; padding:14px; border-radius:8px; font-size:15px; font-weight:600; letter-spacing:.02em; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        .hint { font-size: 11.5px; color:#777; margin-top:4px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-size:13.5px; color:#999; }
        .card b { color:#eee; }
        a.back { display:block; text-align:center; margin-top: 16px; color:#999; font-size: 13px; text-decoration:none; }
        .time-row { display:flex; gap:6px; }
        .time-row select { flex: 1 1 0; width:auto; text-align:center; }
        .time-quick { display:flex; gap:6px; margin-top:6px; }
        .time-quick button { width:auto; flex:1; margin-top:0; background:#1c1c1c; border:1px solid #333; color:#ccc; padding:7px 4px; border-radius:6px; font-size:11.5px; font-weight:500; cursor:pointer; }
        .time-quick button:hover { border-color:#ff7918; color:#ff7918; }
        .cat-quick { display:flex; gap:6px; margin:0 0 8px; flex-wrap:wrap; }
        .cat-quick button { width:auto; margin-top:0; background:#1c1c1c; border:1px solid #333; color:#888; padding:6px 13px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
        .cat-quick button[data-cat="todas"] { color:#ccc; }
        .cat-quick button[data-cat="go"]   { color:#5b9dd9; }
        .cat-quick button[data-cat="lite"] { color:#4ecdc4; }
        .cat-quick button[data-cat="plus"] { color:#b088e8; }
        .cat-quick button[data-cat="max"]  { color:#f2994a; }
        .cat-quick button[data-cat="new-lite"] { color:#e8c76f; background:#2a2410; border-color:#6b5a1e; }
        .cat-quick button:hover { border-color: currentColor; }
        .cat-quick button.active { border-color: currentColor; background:#242424; }
        .cat-quick button[data-cat="new-lite"].active { background:#3a3216; }
        .dur-quick { display:flex; gap:6px; flex-wrap:wrap; }
        .dur-quick button { flex:1; min-width:52px; width:auto; margin-top:0; background:#1c1c1c; border:1.5px solid #333; color:#999; padding:9px 4px; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; }
        .dur-quick button.dur-60 { color:#4ecdc4; } .dur-quick button.dur-120 { color:#5b9dd9; }
        .dur-quick button.dur-180 { color:#b088e8; } .dur-quick button.dur-360 { color:#f2994a; }
        .dur-quick button.dur-720 { color:#ff7918; }
        .dur-quick button:hover { border-color: currentColor; }
        .dur-quick button.active { color:#fff; border-color: transparent; }
        .dur-quick button.dur-60.active { background:#4ecdc4; } .dur-quick button.dur-120.active { background:#5b9dd9; }
        .dur-quick button.dur-180.active { background:#b088e8; } .dur-quick button.dur-360.active { background:#f2994a; }
        .dur-quick button.dur-720.active { background:#ff7918; }
        /* Dos columnas en pantalla ancha: habitación y duración | fecha, hora y personas */
        .page-inner { max-width: 1100px; }
        .edit-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 0 32px; align-items:start; }
        .edit-col > label:first-child { margin-top:0; }
        button.save { width:auto; min-width:300px; padding:12px 28px; margin-top:22px; }
        @media (max-width: 860px) { .edit-grid { grid-template-columns: 1fr; } button.save { width:100%; } }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <h1>Modificar reserva</h1>
    <div class="code">{{ $booking->code }}</div>
    <p class="sub">{{ $booking->customer->name }} — el precio se recalcula con la tarifa vigente para el nuevo horario.</p>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        Actualmente: <b>{{ $booking->room->name }}</b> · {{ $booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i') }} · {{ $booking->duration_minutes / 60 }} h · precio final <b>${{ number_format($booking->price_final, 0, ',', '.') }}</b>
        @if ($booking->checked_in_at)
            <div class="hint" style="margin-top:8px;">Check-in real: <strong>{{ $booking->checked_in_at->timezone('America/Santiago')->format('d/m/Y H:i') }}</strong> — si el huésped llegó antes, ajusta la fecha/hora de abajo para que coincida.</div>
        @endif
    </div>

    <form method="POST" action="{{ route('reservations.update', $booking->code) }}">
        @csrf
        @method('PUT')

        <div class="edit-grid">
            <div class="edit-col">
        <label>Habitación</label>
        @php $roomCats = $rooms->pluck('category')->unique('id')->sortBy('display_order')->values(); @endphp
        @if ($roomCats->count() > 1)
            <div class="cat-quick" id="room-cat-quick">
                <button type="button" data-cat="todas" onclick="hhFilterRooms('todas')">Todas</button>
                @foreach ($roomCats as $cat)
                    <button type="button" data-cat="{{ Str::slug($cat->name) }}" onclick="hhFilterRooms('{{ Str::slug($cat->name) }}')">{{ $cat->name === 'NEW LITE' ? '✨ '.$cat->name : $cat->name }}</button>
                @endforeach
            </div>
        @endif
        <select name="room_id" id="room-select" required>
            @foreach ($rooms as $room)
                @php
                    $occ = $room->current_status['occupancy'];
                    $etaMin = $room->current_status['eta_minutes'];
                    $statusLabel = match(true) {
                        $occ === 'ocupada' && $etaMin >= 0 => 'Ocupada, libera en '.\App\Support\TimeFormat::minutes($etaMin),
                        $occ === 'ocupada' => 'Ocupada (pasó su horario)',
                        default => 'Libre ahora',
                    };
                @endphp
                <option value="{{ $room->id }}" data-cat="{{ Str::slug($room->category->name) }}" @selected(old('room_id', $booking->room_id) == $room->id)>
                    {{ $room->name }} — {{ $room->category->name }} · {{ $statusLabel }}
                </option>
            @endforeach
        </select>
        <div class="hint">El estado es el de <strong>ahora mismo</strong> — si reprogramas para más adelante, puede estar libre igual aunque diga ocupada.</div>

        <label>Duración <span class="hint" style="margin:0;">— según la categoría</span></label>
        @php
            $selDur = (int) old('duration_minutes', $booking->duration_minutes);
            $durLabels = [60 => '1 h', 120 => '2 h', 180 => '3 h', 360 => '6 h', 720 => '12 h'];
            $allDurs = collect($categoryDurations)->flatten()->map(fn ($m) => (int) $m)->unique()->sort()->values();
        @endphp
        <input type="hidden" name="duration_minutes" id="duration-select" value="{{ $selDur }}" required>
        <div class="dur-quick" id="dur-quick">
            @foreach ($allDurs as $mins)
                <button type="button" data-mins="{{ $mins }}" class="dur-{{ $mins }} {{ $selDur === $mins ? 'active' : '' }}" onclick="hhSetDuration({{ $mins }})">{{ $durLabels[$mins] ?? ($mins / 60).' h' }}</button>
            @endforeach
        </div>

            </div>
            <div class="edit-col">
        <div class="row">
            <div>
                <label>Fecha</label>
                <input type="date" name="date" id="date-input" value="{{ old('date', $booking->starts_at->timezone('America/Santiago')->toDateString()) }}" required>
                <div class="time-quick">
                    <button type="button" onclick="hhSetDate(0)">Hoy</button>
                    <button type="button" onclick="hhSetDate(1)">Mañana</button>
                    <button type="button" onclick="hhSetDate(2)">+2 días</button>
                    <button type="button" onclick="hhSetDate(3)">+3 días</button>
                </div>
            </div>
            <div>
                <label>Hora</label>
                @php
                    $oldHour = old('time_hour', $booking->starts_at->timezone('America/Santiago')->format('G'));
                    $oldMinute = old('time_minute', (int) floor($booking->starts_at->timezone('America/Santiago')->minute / 5) * 5);
                @endphp
                <div class="time-row">
                    <select name="time_hour" id="time-hour" required>
                        @for ($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}" @selected((int) $oldHour === $h)>{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                    <select name="time_minute" id="time-minute" required>
                        @for ($m = 0; $m < 60; $m += 5)
                            <option value="{{ $m }}" @selected((int) $oldMinute === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="time-quick">
                    <button type="button" onclick="hhSetTime(0)">Ahora</button>
                    <button type="button" onclick="hhSetTime(30)">+30 min</button>
                    <button type="button" onclick="hhSetTime(60)">+1 h</button>
                    <button type="button" onclick="hhSetTime(120)">+2 h</button>
                </div>
            </div>
        </div>

        <label>Personas</label>
        <input type="number" name="guests_count" min="1" max="10" value="{{ old('guests_count', $booking->guests_count) }}" required>

            </div>
        </div>

        <button type="submit" class="save">Recalcular y guardar cambios</button>
    </form>

    <a class="back" href="{{ route('reservations.show', $booking->code) }}">← Volver sin modificar</a>
    </div>
    <script>
        function hhSetTime(addMinutes) {
            const d = new Date(Date.now() + addMinutes * 60000);
            document.getElementById('time-hour').value = String(d.getHours());
            const roundedMin = Math.round(d.getMinutes() / 5) * 5 % 60;
            document.getElementById('time-minute').value = String(roundedMin);
        }
        function hhSetDate(addDays) {
            const d = new Date();
            d.setDate(d.getDate() + addDays);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            document.getElementById('date-input').value = yyyy + '-' + mm + '-' + dd;
        }
        function hhSetDuration(mins) {
            document.getElementById('duration-select').value = mins;
            document.querySelectorAll('#dur-quick button').forEach(b => b.classList.toggle('active', Number(b.dataset.mins) === mins));
        }

        // Muestra solo las duraciones con precio para la categoría elegida.
        const HH_CAT_DURATIONS = @json($categoryDurations);
        function hhSyncDurations() {
            const roomSel = document.getElementById('room-select');
            const opt = roomSel.options[roomSel.selectedIndex];
            const cat = opt ? opt.dataset.cat : null;
            const allowed = (cat && HH_CAT_DURATIONS[cat]) ? HH_CAT_DURATIONS[cat] : [];
            Array.from(document.querySelectorAll('#dur-quick button'))
                .forEach(b => { b.hidden = allowed.length > 0 && !allowed.includes(Number(b.dataset.mins)); });
            const current = Number(document.getElementById('duration-select').value);
            if (allowed.length > 0 && !allowed.includes(current)) hhSetDuration(allowed[0]);
        }

        // Filtro rápido de habitación por categoría.
        (function () {
            const sel = document.getElementById('room-select');
            const bar = document.getElementById('room-cat-quick');
            if (!sel || !bar) return;

            const allOptions = Array.from(sel.options).map(o => ({
                value: o.value, text: o.textContent.trim(), cat: o.dataset.cat, selected: o.selected,
            }));
            const selectedCat = (allOptions.find(o => o.selected) || {}).cat;

            window.hhFilterRooms = function (cat) {
                const keep = sel.value;
                sel.innerHTML = '';
                allOptions
                    .filter(o => cat === 'todas' || o.cat === cat)
                    .forEach(o => {
                        const opt = document.createElement('option');
                        opt.value = o.value;
                        opt.textContent = o.text;
                        opt.dataset.cat = o.cat;
                        sel.appendChild(opt);
                    });
                if (Array.from(sel.options).some(o => o.value === keep)) sel.value = keep;
                bar.querySelectorAll('button').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
                hhSyncDurations();
            };

            sel.addEventListener('change', hhSyncDurations);

            // Arranca en la categoría de la habitación actual de la reserva.
            hhFilterRooms(selectedCat && bar.querySelector('button[data-cat="' + selectedCat + '"]') ? selectedCat : 'todas');
        })();
    </script>
</body>
</html>
