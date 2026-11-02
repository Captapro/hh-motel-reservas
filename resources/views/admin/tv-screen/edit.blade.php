@extends('admin.layout')
@section('title', 'Pantalla TV')
@section('content')
    <style>
        .hint { color:#888; font-size:12px; margin-top:4px; line-height:1.4; }
        .tv-check { display:flex; align-items:center; gap:8px; margin:8px 0; font-size:14px; }
        .tv-check input { width:auto; margin:0; }
        .tv-msg { display:grid; grid-template-columns: 1fr 2fr auto; gap:8px; align-items:center; margin-bottom:8px; }
        .tv-msg input[type=text] { margin:0; }
        .tv-link { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .tv-link code { background:#1c1c1c; border:1px solid #333; border-radius:6px; padding:6px 10px; font-size:14px; }
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
            <h2 style="font-size:15px; margin:0 0 6px;">Combos</h2>
            <label class="tv-check"><input type="checkbox" name="show_combos" value="1" @checked(old('show_combos', $setting->show_combos))> Mostrar los combos que están en el catálogo</label>
            <label class="tv-check"><input type="checkbox" name="show_combo_contents" value="1" @checked(old('show_combo_contents', $setting->show_combo_contents))> Mostrar qué incluye cada combo</label>
            <div class="hint">Sin esto se ve solo el nombre y el precio (más discreto si el TV queda a la vista de gente de paso).</div>
            <label for="combo_cta" style="margin-top:12px;">Frase bajo el precio</label>
            <input type="text" id="combo_cta" name="combo_cta" maxlength="120" value="{{ old('combo_cta', $setting->combo_cta) }}" placeholder="Agrégalo a tu estadía en recepción">
        </div>

        <div class="card">
            <h2 style="font-size:15px; margin:0 0 6px;">Códigos QR</h2>
            <label class="tv-check"><input type="checkbox" name="show_reserve_qr" value="1" @checked(old('show_reserve_qr', $setting->show_reserve_qr))> "Reserva desde tu celular" (abre el catálogo)</label>
            <label class="tv-check"><input type="checkbox" name="show_review_qr" value="1" @checked(old('show_review_qr', $setting->show_review_qr))> "Danos tu opinión" (abre la página de opinión)</label>
        </div>

        <div class="card">
            <h2 style="font-size:15px; margin:0 0 6px;">Mensajes propios</h2>
            <p class="hint" style="margin:0 0 10px;">Diapositivas con tu texto: promociones, horarios, avisos. Se intercalan entre los combos. Deja la fila vacía para borrarla.</p>
            @php $messages = old('messages', $setting->messages ?? []); @endphp
            @for ($i = 0; $i < \App\Models\TvScreenSetting::MAX_MESSAGES; $i++)
                <div class="tv-msg">
                    <input type="text" name="messages[{{ $i }}][title]" maxlength="60" placeholder="Título (ej. Lunes a jueves 10% off)" value="{{ $messages[$i]['title'] ?? '' }}">
                    <input type="text" name="messages[{{ $i }}][text]" maxlength="200" placeholder="Texto (opcional)" value="{{ $messages[$i]['text'] ?? '' }}">
                    <label class="tv-check" style="margin:0;"><input type="checkbox" name="messages[{{ $i }}][active]" value="1" @checked(! empty($messages[$i]['active']) || ! isset($messages[$i]))> Mostrar</label>
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
@endsection
