<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Inspección de Playroom — {{ $room->name }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 980px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom:2px; }
        .sub { color:#999; font-size: 13px; margin-bottom: 22px; }
        a.back-btn { display:inline-block; background:#1c1c1c; border:1px solid #333; color:#ccc; text-decoration:none; padding:8px 13px; border-radius:7px; font-size:12.5px; font-weight:600; margin-bottom:16px; }
        a.back-btn:hover { border-color:#ff7918; color:#ff7918; }

        .last-panel { background:#1c1c1c; color:#ddd; border:1px solid #333; border-radius:10px; padding:14px 16px; margin-bottom:20px; font-size:13px; }
        .last-panel.warn { border-color:#7a2d2d; background:#241419; }
        .last-panel .label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        .last-panel .pill { display:inline-block; font-size:10.5px; padding:2px 8px; border-radius:20px; margin-left:6px; }
        .pill.ok { background:#1c3a2a; color:#6fd39a; }
        .pill.warn { background:#3a1c22; color:#e88a9a; }

        form { background:#1c1c1c; color:#eee; border:1px solid #333; border-radius:12px; padding:18px 20px; }
        label.field-label { display:block; font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
        input[type=text], textarea { width:100%; background:#111; border:1px solid #333; color:#eee; padding:9px 11px; border-radius:8px; font-size:14px; font-family:inherit; }
        input[type=text]:focus, textarea:focus { outline:none; border-color:#ff7918; }
        .field { margin-bottom:16px; }
        .top-fields { display:grid; grid-template-columns:1fr 180px 150px; gap:14px; }
        .section-title { color:#ff9a4a; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin:22px 0 8px; }
        input[type=file], select { width:100%; background:#111; border:1px solid #333; color:#eee; padding:10px 11px; border-radius:8px; font-size:14px; }
        .photo-help { color:#888; font-size:12px; margin-top:5px; }
        .date-readonly { background:#242424!important; color:#aaa!important; }
        @media(max-width:680px){ .top-fields{grid-template-columns: minmax(0, 1fr);} }
        .checklist { border-top:1px solid #292929; padding-top:14px; margin-bottom:16px; }
        .check-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #242424; gap:12px; }
        .check-row:last-child { border-bottom:none; }
        .check-row span { font-size:13.5px; color:#ddd; }
        .toggle { display:flex; gap:6px; flex:none; }
        .toggle input { position:absolute; opacity:0; width:0; height:0; }
        .toggle label { cursor:pointer; padding:6px 12px; border-radius:7px; font-size:12px; font-weight:600; border:1px solid #333; color:#999; }
        .toggle input:checked + label.opt-ok { background:#1c3a2a; border-color:#2e5a2e; color:#6fd39a; }
        .toggle input:checked + label.opt-falla { background:#3a1c22; border-color:#7a2d2d; color:#e88a9a; }
        textarea { min-height:70px; resize:vertical; }
        button.submit-btn { display:block; width:100%; background:#ff7918; border:none; color:#fff; padding:12px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; margin-top:6px; }
        button.submit-btn:hover { background:#df6209; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    @php
        $backRoute = request('ronda')
            ? route('shift_round.index')
            : (request('qr') ? route('rooms.qr.show', $room) : route('rooms.board'));
        $backLabel = request('ronda') ? 'Volver a la ronda' : (request('qr') ? 'Volver a la habitación' : 'Volver al tablero');
    @endphp
    <a class="back-btn" href="{{ $backRoute }}">← {{ $backLabel }}</a>
    <h1>Inspeccionar Playroom {{ $room->name }}</h1>
    <p class="sub">Categoría {{ $room->category->name }} · completa la pauta real de habitación y mobiliario antes de liberarla.</p>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    @if ($lastInspection)
        <div class="last-panel {{ $lastInspection->needs_maintenance ? 'warn' : '' }}">
            <div class="label">Última inspección</div>
            {{ $lastInspection->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }}
            @if ($lastInspection->inspected_by) · {{ $lastInspection->inspected_by }} @endif
            <span class="pill {{ $lastInspection->needs_maintenance ? 'warn' : 'ok' }}">
                {{ $lastInspection->needs_maintenance ? 'Necesitaba mantención' : 'Todo OK' }}
            </span>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('rooms.inspections.store', $room) }}">
        @csrf
        @if (request('ronda'))
            <input type="hidden" name="from_ronda" value="1">
        @endif
        @if (request('qr'))
            <input type="hidden" name="from_qr" value="1">
        @endif
        <div class="top-fields">
            <div class="field"><label class="field-label">Anfitrión</label><input type="text" name="inspected_by" value="{{ old('inspected_by', auth()->user()->name) }}" placeholder="Nombre de quien revisa" required></div>
            <div class="field"><label class="field-label">Turno</label><select name="shift" required><option value="">Seleccionar</option>@foreach(['Mañana','Tarde','Noche','Madrugada'] as $shift)<option value="{{ $shift }}" @selected(old('shift') === $shift)>{{ $shift }}</option>@endforeach</select></div>
            <div class="field"><label class="field-label">Fecha</label><input class="date-readonly" type="text" value="{{ now()->timezone('America/Santiago')->format('d/m/Y') }}" readonly></div>
        </div>

        <div class="section-title">Revisión de la habitación</div>

        <div class="checklist">
            @foreach (\App\Models\RoomInspection::ITEMS as $key => $label)
                @php $old = old('checklist.'.$key); @endphp
                <div class="check-row">
                    <span>{{ $label }}</span>
                    <div class="toggle">
                        <input type="radio" name="checklist[{{ $key }}]" value="ok" id="{{ $key }}_ok" {{ $old !== 'falla' ? 'checked' : '' }}>
                        <label class="opt-ok" for="{{ $key }}_ok">OK</label>
                        <input type="radio" name="checklist[{{ $key }}]" value="falla" id="{{ $key }}_falla" {{ $old === 'falla' ? 'checked' : '' }}>
                        <label class="opt-falla" for="{{ $key }}_falla">Falla</label>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="section-title">Revisión de mobiliario</div>
        @if($room->furniture->isNotEmpty())
            <div class="checklist">
                @foreach($room->furniture as $item)
                    @php $key = 'furniture.'.$item->id; $old = old('checklist.'.$key); @endphp
                    <div class="check-row"><span>{{ $item->icon }} {{ $item->name }} <small style="color:#888">×{{ $item->pivot->quantity }}</small></span><div class="toggle"><input type="radio" name="checklist[furniture][{{ $item->id }}]" value="ok" id="f{{ $item->id }}ok" {{ $old !== 'falla' ? 'checked' : '' }}><label class="opt-ok" for="f{{ $item->id }}ok">OK</label><input type="radio" name="checklist[furniture][{{ $item->id }}]" value="falla" id="f{{ $item->id }}fail" {{ $old === 'falla' ? 'checked' : '' }}><label class="opt-falla" for="f{{ $item->id }}fail">Falla</label></div></div>
                @endforeach
            </div>
        @else
            <div class="last-panel" style="color:#aaa">No hay mobiliario asignado a esta habitación. Puedes configurarlo desde Administración → Habitaciones.</div>
        @endif

        <div class="field"><label class="field-label">Desperfectos encontrados</label><textarea name="defects" placeholder="Ej.: espejo quebrado, luz sin funcionar, mobiliario dañado...">{{ old('defects') }}</textarea></div>

        <div class="field">
            <label class="field-label">Observaciones de la ronda (opcional)</label>
            <textarea name="notes" placeholder="Detalle de lo que falla, si corresponde...">{{ old('notes') }}</textarea>
        </div>
        <div class="field"><label class="field-label">Fotografías del estado</label><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple><div class="photo-help">Puedes adjuntar hasta 6 fotos. Se guardan junto a esta inspección.</div></div>

        <button type="submit" class="submit-btn">Guardar inspección</button>
    </form>
    </div>
</body>
</html>
