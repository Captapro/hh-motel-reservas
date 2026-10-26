<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Claves de acceso — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        .topline { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom: 8px; }
        h1 { font-size: 19px; margin:0; }
        .sub { color:#999; font-size: 13px; margin: 4px 0 0; }
        .tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .tools input { background:#1c1c1c; border:1px solid #333; color:#eee; padding:9px 12px; border-radius:7px; font-size:14px; min-width:240px; }
        .btn { background:#1c1c1c; border:1px solid #333; color:#eee; padding:8px 14px; border-radius:7px; font-size:13px; cursor:pointer; text-decoration:none; }
        .btn:hover { border-color:#ff7918; }
        .btn.primary { background:#ff7918; border-color:#ff7918; color:#111; font-weight:600; }
        .btn.small { padding:4px 10px; font-size:12px; }
        .btn.danger { color:#c0392b; }
        .status { background:#16301f; border:1px solid #2f6b45; color:#6fd39a; padding:10px 14px; border-radius:8px; font-size:13px; margin:14px 0 0; }
        .errors { background:#3a1c22; border:1px solid #7a2d2d; color:#e88a9a; padding:10px 14px; border-radius:8px; font-size:13px; margin:14px 0 0; }
        .sec-label { font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#888; margin:30px 0 8px; padding-top:18px; border-top:1px solid #232323; }
        .sec-note { font-size:12.5px; color:#999; margin:0 0 12px; }
        .table-wrap { overflow-x:auto; background:#1c1c1c; border:1px solid #333; border-radius:10px; margin-bottom:14px; }
        table { width:100%; border-collapse: collapse; font-size:13.5px; }
        th { text-align:left; background:#2a2a2a; color:#d6d6d6; font-weight:500; font-size:11px; text-transform:uppercase; padding:9px 10px; border:1px solid #4a4a4a; white-space:nowrap; }
        td { padding:8px 10px; border:1px solid #3a3a3a; background:#1c1c1c; vertical-align:middle; }
        tr.zero td { color:#7d7d7d; }
        .code { font-family: ui-monospace, monospace; font-size:16px; font-weight:700; letter-spacing:.08em; }
        .code.masked { color:#888; letter-spacing:.2em; }
        .muted { color:#888; font-size:12px; }
        .tag { display:inline-block; font-size:10.5px; border:1px solid #444; border-radius:20px; padding:1px 8px; color:#bbb; }
        details summary { cursor:pointer; color:#ff7918; font-size:12px; list-style:none; }
        details summary::-webkit-details-marker { display:none; }
        .edit-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:8px; }
        .edit-form input, .edit-form select { background:#111; border:1px solid #333; color:#eee; padding:6px 8px; border-radius:6px; font-size:13px; }
        .edit-form input[name=code] { font-family: ui-monospace, monospace; width:130px; }
        .add-box { background:#fff; border:1px solid #dddfe1; border-radius:10px; padding:14px; margin-bottom:14px; }
        .add-box .edit-form input, .add-box .edit-form select { background:#fff; border-color:#d1d3d8; color:#202124; }
        .add-box .edit-form { margin-top:0; }
        tr.filtered { display:none; }
        @media print { .tools, details, .hh-rail, .hh-nav-wrap, .add-box, .btn { display:none !important; } }
    </style>
</head>
<body>
@include('partials.navbar')
@php $masked = '••••••'; @endphp
<div class="page-inner">
    <div class="topline">
        <div>
            <h1>Claves de acceso</h1>
            <p class="sub">Chapas digitales de habitaciones, bodegas y otros recintos. Las claves están ocultas: cada vez que alguien las muestra queda registrado.</p>
        </div>
        <div class="tools">
            <input type="search" id="code-search" placeholder="Buscar habitación o recinto…" autocomplete="off">
            <button type="button" class="btn primary" id="reveal-all">Mostrar todas</button>
            <button type="button" class="btn" id="hide-all" hidden>Ocultar</button>
        </div>
    </div>

    @if (session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif

    <div class="sec-label">Habitaciones</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Habitación</th><th>Categoría</th><th>Clave</th><th>Nota</th><th>Actualizada</th><th></th></tr></thead>
        <tbody>
        @foreach ($rooms as ['room' => $room, 'code' => $code])
            <tr class="searchable {{ $code ? '' : 'zero' }}" data-search="{{ Str::lower($room->name.' '.$room->category?->name) }}">
                <td><b>{{ $room->name }}</b></td>
                <td>{{ $room->category?->name }}</td>
                <td>@if ($code)<span class="code masked" data-code-id="{{ $code->id }}">{{ $masked }}</span>@else<span class="muted">Sin clave</span>@endif</td>
                <td class="muted" data-notes-id="{{ $code?->id }}">@if ($code?->notes)…@endif</td>
                <td class="muted">@if ($code){{ $code->updated_at->locale('es')->isoFormat('D MMM YYYY') }}@if ($code->editor) · {{ $code->editor->name }}@endif @endif</td>
                <td>
                    @if ($code)<button type="button" class="btn small reveal-one" data-id="{{ $code->id }}">Ver</button>@endif
                    @if ($canEdit)
                        <details>
                            <summary>{{ $code ? 'Cambiar' : 'Agregar clave' }}</summary>
                            <form class="edit-form" method="POST" action="{{ route('access_codes.room', $room) }}">
                                @csrf @method('PUT')
                                <input name="code" placeholder="Clave" maxlength="100" autocomplete="off">
                                <input name="notes" placeholder="Nota (opcional)" maxlength="500" autocomplete="off">
                                <button class="btn small primary">Guardar</button>
                                @if ($code)<span class="muted">Deja la clave vacía para borrarla.</span>@endif
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>

    <div class="sec-label">Bodegas y otros recintos</div>
    @if ($canEdit)
        <div class="add-box">
            <form class="edit-form" method="POST" action="{{ route('access_codes.store') }}">
                @csrf
                <input name="name" placeholder="Nombre (ej: Bodega de blancos)" maxlength="80" required value="{{ old('name') }}">
                <select name="kind">@foreach ($kinds as $key => $label)<option value="{{ $key }}" @selected(old('kind') === $key)>{{ $label }}</option>@endforeach</select>
                <input name="location" placeholder="Ubicación (opcional)" maxlength="120" value="{{ old('location') }}">
                <input name="code" placeholder="Clave" maxlength="100" required autocomplete="off">
                <input name="notes" placeholder="Nota (opcional)" maxlength="500" autocomplete="off">
                <button class="btn primary">Agregar recinto</button>
            </form>
        </div>
    @endif
    <div class="table-wrap"><table>
        <thead><tr><th>Recinto</th><th>Tipo</th><th>Ubicación</th><th>Clave</th><th>Nota</th><th>Actualizada</th><th></th></tr></thead>
        <tbody>
        @forelse ($places as $place)
            <tr class="searchable" data-search="{{ Str::lower($place->name.' '.$place->location.' '.($kinds[$place->kind] ?? '')) }}">
                <td><b>{{ $place->name }}</b></td>
                <td><span class="tag">{{ $kinds[$place->kind] ?? $place->kind }}</span></td>
                <td>{{ $place->location }}</td>
                <td><span class="code masked" data-code-id="{{ $place->id }}">{{ $masked }}</span></td>
                <td class="muted" data-notes-id="{{ $place->id }}">@if ($place->notes)…@endif</td>
                <td class="muted">{{ $place->updated_at->locale('es')->isoFormat('D MMM YYYY') }}@if ($place->editor) · {{ $place->editor->name }}@endif</td>
                <td>
                    <button type="button" class="btn small reveal-one" data-id="{{ $place->id }}">Ver</button>
                    @if ($canEdit)
                        <details>
                            <summary>Editar</summary>
                            <form class="edit-form" method="POST" action="{{ route('access_codes.update', $place) }}">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $place->name }}" maxlength="80" required>
                                <select name="kind">@foreach ($kinds as $key => $label)<option value="{{ $key }}" @selected($place->kind === $key)>{{ $label }}</option>@endforeach</select>
                                <input name="location" value="{{ $place->location }}" placeholder="Ubicación" maxlength="120">
                                <input name="code" placeholder="Nueva clave" maxlength="100" required autocomplete="off">
                                <input name="notes" placeholder="Nota (opcional)" maxlength="500" autocomplete="off">
                                <button class="btn small primary">Guardar</button>
                            </form>
                            <form method="POST" action="{{ route('access_codes.destroy', $place) }}" onsubmit="return confirm('¿Eliminar {{ e($place->name) }} y su clave?');" style="margin-top:6px;">
                                @csrf @method('DELETE')
                                <button class="btn small danger">Eliminar recinto</button>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <tr class="zero"><td colspan="7">Todavía no hay recintos. @if ($canEdit)Agrega bodegas, sala de máquinas, oficina, portón…@endif</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<script>
(function () {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const masked = @json($masked);

    function show(data) {
        Object.keys(data).forEach(function (id) {
            document.querySelectorAll('[data-code-id="' + id + '"]').forEach(function (el) { el.textContent = data[id].code; el.classList.remove('masked'); });
            document.querySelectorAll('[data-notes-id="' + id + '"]').forEach(function (el) { el.textContent = data[id].notes || ''; });
        });
    }
    function hideAll() {
        document.querySelectorAll('[data-code-id]').forEach(function (el) { el.textContent = masked; el.classList.add('masked'); });
        document.querySelectorAll('[data-notes-id]').forEach(function (el) { if (el.textContent) el.textContent = '…'; });
        document.getElementById('hide-all').hidden = true;
    }
    function reveal(ids) {
        return fetch(@json(route('access_codes.reveal')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ ids: ids }),
        }).then(function (r) { if (!r.ok) throw r; return r.json(); }).then(function (data) {
            show(data);
            document.getElementById('hide-all').hidden = false;
        }).catch(function () { alert('No se pudieron mostrar las claves. Recarga la página e intenta de nuevo.'); });
    }

    document.getElementById('reveal-all').addEventListener('click', function () { reveal([]); });
    document.getElementById('hide-all').addEventListener('click', hideAll);
    document.querySelectorAll('.reveal-one').forEach(function (b) {
        b.addEventListener('click', function () { reveal([Number(b.dataset.id)]); });
    });
    // Se ocultan solas a los 2 minutos, por si la pantalla queda a la vista.
    let timer;
    document.addEventListener('click', function () { clearTimeout(timer); timer = setTimeout(hideAll, 120000); });

    const search = document.getElementById('code-search');
    search.addEventListener('input', function () {
        const q = search.value.trim().toLowerCase();
        document.querySelectorAll('tr.searchable').forEach(function (tr) { tr.classList.toggle('filtered', q !== '' && !tr.dataset.search.includes(q)); });
    });
})();
</script>
</body>
</html>
