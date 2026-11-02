@extends('admin.layout')
@section('title', 'Pantalla TV')
@section('content')
    <style>
        .hint { color:#888; font-size:12px; margin-top:4px; line-height:1.4; }
        .tv-check { display:flex; align-items:center; gap:8px; margin:8px 0; font-size:14px; }
        .tv-check input { width:auto; margin:0; }
        .tv-msg { display:grid; grid-template-columns: 1fr 2fr; gap:8px; align-items:center; margin-bottom:8px; }
        .tv-msg input[type=text] { margin:0; }
        .tv-link { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .tv-link code { background:#1c1c1c; border:1px solid #333; border-radius:6px; padding:6px 10px; font-size:14px; }
        .tv-list { list-style:none; padding:0; margin:0; counter-reset:tv; }
        .tv-row { display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid #2c2d31; border-radius:8px; margin-bottom:6px; background:#17181a; counter-increment:tv; }
        .tv-row::before { content:counter(tv); color:#ff7918; font-weight:800; width:20px; text-align:right; }
        .tv-row.off { opacity:.5; }
        .tv-move { width:auto; margin:0; padding:4px 10px; background:#26272b; border:1px solid #3a3b40; color:#ddd; border-radius:6px; cursor:pointer; }
        @media (max-width: 700px) { .tv-msg { grid-template-columns: 1fr; } }
    </style>

    <h1>Pantalla TV</h1>
    <p class="sub">Lo que pasa en loop en el TV de recepción. Los cambios llegan al TV al terminar la vuelta en curso (máximo 2 minutos).</p>

    <div class="card">
        <div class="tv-link">
            <span>Dirección para abrir en el TV:</span>
            <code>{{ route('tv.show') }}</code>
            <a class="btn" href="{{ route('tv.show') }}" target="_blank" rel="noopener">Ver pantalla ↗</a>
        </div>
        <div class="hint">En el TV, abre esa dirección en el navegador y toca la pantalla una vez para dejarla completa.</div>
    </div>

    <form method="POST" action="{{ route('admin.tv_screen.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <h2 style="font-size:15px; margin:0 0 6px;">Qué se muestra y en qué orden</h2>
            <p class="hint" style="margin:0 0 10px;">Marca lo que quieres en el TV y ordénalo con las flechas. La vuelta sigue este orden de arriba a abajo y vuelve a empezar. Un combo nuevo del catálogo entra solo al final.</p>
            <ol class="tv-list" id="tv-list">
                @foreach ($playlist as $i => $entry)
                    <li class="tv-row{{ $entry['on'] ? '' : ' off' }}">
                        <input type="hidden" name="playlist[{{ $i }}][key]" value="{{ $entry['key'] }}" data-field="key">
                        <label class="tv-check" style="margin:0; flex:1;"><input type="checkbox" name="playlist[{{ $i }}][on]" value="1" data-field="on" @checked($entry['on'])> {{ $entry['label'] }}</label>
                        <button type="button" class="tv-move" data-dir="-1" aria-label="Subir">▲</button>
                        <button type="button" class="tv-move" data-dir="1" aria-label="Bajar">▼</button>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="card">
            <h2 style="font-size:15px; margin:0 0 6px;">Cómo se ven los combos</h2>
            <label class="tv-check"><input type="checkbox" name="show_combo_contents" value="1" @checked(old('show_combo_contents', $setting->show_combo_contents))> Mostrar qué incluye cada combo</label>
            <div class="hint">Sin esto se ve solo el nombre y el precio (más discreto si el TV queda a la vista de gente de paso).</div>
            <label for="combo_cta" style="margin-top:12px;">Frase bajo el precio</label>
            <input type="text" id="combo_cta" name="combo_cta" maxlength="120" value="{{ old('combo_cta', $setting->combo_cta) }}" placeholder="Agrégalo a tu estadía en recepción">
        </div>

        <div class="card">
            <h2 style="font-size:15px; margin:0 0 6px;">Mensajes propios</h2>
            <p class="hint" style="margin:0 0 10px;">Diapositivas con tu texto: promociones, horarios, avisos. Al guardar aparecen en la lista de arriba para encenderlas y ubicarlas. Deja la fila vacía para borrarla.</p>
            @php $messages = old('messages', $setting->messages ?? []); @endphp
            @for ($i = 0; $i < \App\Models\TvScreenSetting::MAX_MESSAGES; $i++)
                <div class="tv-msg">
                    <input type="text" name="messages[{{ $i }}][title]" maxlength="60" placeholder="Título (ej. Lunes a jueves 10% off)" value="{{ $messages[$i]['title'] ?? '' }}">
                    <input type="text" name="messages[{{ $i }}][text]" maxlength="200" placeholder="Texto (opcional)" value="{{ $messages[$i]['text'] ?? '' }}">
                </div>
            @endfor
        </div>

        <div class="card">
            <h2 style="font-size:15px; margin:0 0 6px;">Duración</h2>
            <div class="row2">
                <div>
                    <label for="combo_seconds">Segundos por combo o mensaje</label>
                    <input type="number" id="combo_seconds" name="combo_seconds" min="4" max="120" value="{{ old('combo_seconds', $setting->combo_seconds) }}" required>
                </div>
                <div>
                    <label for="qr_seconds">Segundos por QR</label>
                    <input type="number" id="qr_seconds" name="qr_seconds" min="4" max="120" value="{{ old('qr_seconds', $setting->qr_seconds) }}" required>
                    <div class="hint">Más largo da tiempo para sacar el celular y escanear.</div>
                </div>
            </div>
        </div>

        <div class="actions" style="margin-top:16px;">
            <button class="btn" type="submit">Guardar</button>
        </div>
    </form>

    <script>
        (function () {
            const list = document.getElementById('tv-list');
            // Al mover, se renumeran los name para que el orden llegue tal cual al guardar.
            function renumber() {
                Array.from(list.children).forEach((li, i) => {
                    li.querySelectorAll('[data-field]').forEach((el) => { el.name = 'playlist[' + i + '][' + el.dataset.field + ']'; });
                });
            }
            list.addEventListener('click', (e) => {
                const btn = e.target.closest('.tv-move');
                if (!btn) return;
                const li = btn.closest('li');
                if (btn.dataset.dir === '-1' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
                if (btn.dataset.dir === '1' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
                renumber();
            });
            list.addEventListener('change', (e) => {
                if (e.target.dataset.field === 'on') e.target.closest('li').classList.toggle('off', !e.target.checked);
            });
        })();
    </script>
@endsection
