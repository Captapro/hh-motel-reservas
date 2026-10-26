@extends('admin.layout')
@section('title', 'Estadía corta por cierre')
@section('content')
    <style>
        .hint { color:#888; font-size:12px; margin-top:4px; line-height:1.4; }
        .ss-example { margin-top:18px; padding:12px 14px; border-radius:8px; background:#2e2410; border:1px solid #c98a1b; color:#f3d9a4; font-size:13px; line-height:1.5; }
        .ss-example b { color:#fff; }
    </style>

    <h1>Estadía corta por cierre</h1>
    <p class="sub">Cuando queda menos tiempo para cerrar que la duración mínima que se vende en una categoría, se puede vender igual con salida a la hora de cierre y un precio menor. Estas reglas valen para recepción y para el catálogo en línea.</p>

    <form method="POST" action="{{ route('admin.short_stay.update') }}">
        @csrf
        @method('PUT')

        <label>Estado</label>
        <select name="enabled">
            <option value="1" @selected(old('enabled', $setting->enabled))>Activa</option>
            <option value="0" @selected(! old('enabled', $setting->enabled))>Apagada (no se ofrece en ningún lado)</option>
        </select>

        <div class="row2">
            <div>
                <label>En recepción</label>
                <select name="reception_enabled">
                    <option value="1" @selected(old('reception_enabled', $setting->reception_enabled))>Se ofrece</option>
                    <option value="0" @selected(! old('reception_enabled', $setting->reception_enabled))>No se ofrece</option>
                </select>
            </div>
            <div>
                <label>En el catálogo en línea</label>
                <select name="catalog_enabled">
                    <option value="1" @selected(old('catalog_enabled', $setting->catalog_enabled))>Se ofrece</option>
                    <option value="0" @selected(! old('catalog_enabled', $setting->catalog_enabled))>No se ofrece</option>
                </select>
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Tiempo mínimo para el cierre (minutos)</label>
                <input type="number" name="min_minutes" id="ss-min" min="5" max="600" step="5" value="{{ old('min_minutes', $setting->min_minutes) }}" required>
                <div class="hint">Con menos tiempo que esto ya no se vende.</div>
            </div>
            <div>
                <label>Tiempo máximo para el cierre (minutos)</label>
                <input type="number" name="max_minutes" id="ss-max" min="5" max="720" step="5" value="{{ old('max_minutes', $setting->max_minutes) }}" required>
                <div class="hint">Solo se ofrece si quedan esto o menos. 90 = hora y media.</div>
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Cómo se calcula el precio</label>
                <select name="pricing_mode" id="ss-mode">
                    @foreach (\App\Models\ShortStaySetting::MODES as $value => $label)
                        <option value="{{ $value }}" @selected(old('pricing_mode', $setting->pricing_mode) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label id="ss-percent-label">Porcentaje (%)</label>
                <input type="number" name="percent" id="ss-percent" min="1" max="100" value="{{ old('percent', $setting->percent) }}" required>
                <div class="hint" id="ss-percent-hint"></div>
            </div>
        </div>

        <div class="ss-example" id="ss-example"></div>

        <div class="actions" style="margin-top:20px;">
            <button class="btn" type="submit">Guardar</button>
        </div>
    </form>

    <script>
        (function () {
            const $ = (id) => document.getElementById(id);
            const fmt = (n) => '$' + Number(n).toLocaleString('es-CL');
            const hm = (m) => (m >= 60 ? Math.floor(m / 60) + 'h' + (m % 60 ? ' ' + (m % 60) + 'min' : '') : m + ' min');
            function paint() {
                const min = Number($('ss-min').value) || 0;
                const max = Number($('ss-max').value) || 0;
                const pct = Number($('ss-percent').value) || 0;
                const prop = $('ss-mode').value === 'proporcional';
                $('ss-percent-label').textContent = prop ? 'Precio mínimo (% del precio)' : 'Precio (% del precio)';
                $('ss-percent-hint').textContent = prop ? 'Piso: nunca cobra menos que este porcentaje.' : 'Siempre se cobra este porcentaje, quede el tiempo que quede.';
                // Ejemplo con una tarifa mínima de 3 h a $30.000 y el máximo de tiempo configurado.
                const full = 30000, dur = 180, left = Math.min(max, dur - 1);
                const floor = Math.ceil(full * pct / 100 / 100) * 100;
                const price = prop ? Math.min(full, Math.max(Math.ceil(full * left / dur / 100) * 100, floor)) : Math.min(full, floor);
                $('ss-example').innerHTML = '<b>Ejemplo:</b> categoría con mínimo de 3 h a ' + fmt(full) + ', cierre a las 03:00. '
                    + 'Se ofrece si el cliente llega entre las ' + hhClock(max) + ' y las ' + hhClock(min) + ' (quedan entre ' + hm(min) + ' y ' + hm(max) + '). '
                    + 'Llegando a las ' + hhClock(left) + ' paga <b>' + fmt(price) + '</b> y sale a las 03:00.';
            }
            function hhClock(minutesBeforeClose) {
                const t = (3 * 60 - minutesBeforeClose + 24 * 60) % (24 * 60);
                return String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
            }
            ['ss-min', 'ss-max', 'ss-percent', 'ss-mode'].forEach((id) => $(id).addEventListener('input', paint));
            paint();
        })();
    </script>
@endsection
