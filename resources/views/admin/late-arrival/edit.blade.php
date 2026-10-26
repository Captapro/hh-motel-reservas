@extends('admin.layout')
@section('title', 'Atrasos')
@section('content')
    <style>
        .hint { color:#888; font-size:12px; margin-top:4px; line-height:1.4; }
        .la-example { margin-top:18px; padding:12px 14px; border-radius:8px; background:#1c2433; border:1px solid #3b5b8f; color:#cfe0ff; font-size:13px; line-height:1.55; }
        .la-example b { color:#fff; }
    </style>

    <h1>Atrasos al check-in</h1>
    <p class="sub">Qué pasa con el tiempo de la estadía cuando el cliente llega después de la hora reservada. Recepción ve el resultado en la pantalla de check-in antes de confirmarlo. La salida nunca se corre más allá del cierre ni encima de la siguiente reserva de la habitación.</p>

    <form method="POST" action="{{ route('admin.late_arrival.update') }}">
        @csrf
        @method('PUT')

        <div class="row2">
            <div>
                <label>Tolerancia (minutos)</label>
                <input type="number" name="tolerance_minutes" id="la-tol" min="0" max="240" step="5" value="{{ old('tolerance_minutes', $setting->tolerance_minutes) }}" required>
                <div class="hint">Hasta este atraso el contador parte de cero: tiempo completo.</div>
            </div>
            <div>
                <label>Si se pasa de la tolerancia</label>
                <select name="mode" id="la-mode">
                    @foreach (\App\Models\LateArrivalSetting::MODES as $value => $label)
                        <option value="{{ $value }}" @selected(old('mode', $setting->mode) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="la-example" id="la-example"></div>

        <div class="actions" style="margin-top:20px;">
            <button class="btn" type="submit">Guardar</button>
        </div>
    </form>

    <script>
        (function () {
            const clock = (m) => String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
            function paint() {
                const tol = Number(document.getElementById('la-tol').value) || 0;
                const all = document.getElementById('la-mode').value === 'todo';
                // Reserva de 3 h a las 20:00 (salida 23:00).
                const start = 20 * 60, end = 23 * 60;
                const line = (delay) => {
                    const credit = delay <= tol ? delay : (all ? 0 : tol);
                    const lost = delay - credit;
                    return 'Llega a las ' + clock(start + delay) + ' (' + delay + ' min tarde): '
                        + (lost === 0 ? '<b>tiempo completo</b>' : '<b>se descuentan ' + lost + ' min</b>')
                        + ', sale a las <b>' + clock(end + credit) + '</b>.';
                };
                const small = Math.max(5, Math.min(tol, 10));
                document.getElementById('la-example').innerHTML = '<b>Ejemplo:</b> reserva de 3 h a las 20:00 (salida 23:00).<br>'
                    + line(small) + '<br>' + line(40);
            }
            ['la-tol', 'la-mode'].forEach((id) => document.getElementById(id).addEventListener('input', paint));
            paint();
        })();
    </script>
@endsection
