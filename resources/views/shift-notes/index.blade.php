<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Novedades — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family:-apple-system, "Segoe UI", sans-serif; margin:0; min-height:100vh; }
        .page-inner { max-width:1500px; margin:0; padding:18px 24px 40px; }
        h1 { font-size:22px; margin:0 0 14px; }
        .banner { background:#2a1a0c; border:1px solid #ff7918; color:#ffd2ad; padding:10px 16px; border-radius:9px; margin-bottom:14px; font-size:14px; font-weight:600; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:10px 12px; border-radius:8px; margin-bottom:14px; font-size:13px; }
        .panel { background:#1c1c1c; border:1px solid #333; border-radius:12px; padding:14px 16px; margin-bottom:14px; }
        .new-grid { display:grid; grid-template-columns:170px 170px minmax(0,1fr); gap:10px; }
        @media (max-width:760px) { .new-grid { grid-template-columns:minmax(0,1fr); } }
        label { display:block; font-size:12px; color:#bbb; margin-bottom:5px; }
        select, textarea, input[type=text] { width:100%; background:#141414; border:1px solid #333; color:#fff; padding:9px 11px; border-radius:7px; font-size:14px; font-family:inherit; }
        textarea { min-height:44px; resize:vertical; }
        .flags { display:flex; flex-wrap:wrap; gap:18px; align-items:center; margin-top:10px; font-size:13.5px; }
        .flags label { display:flex; gap:7px; align-items:center; margin:0; color:#ddd; font-size:13.5px; cursor:pointer; }
        .flags input { width:17px; height:17px; accent-color:#ff7918; margin:0; }
        .btn { background:#ff7918; color:#21170e; border:none; padding:9px 18px; border-radius:7px; font-size:14px; font-weight:700; cursor:pointer; margin-left:auto; }
        .tabs { display:flex; gap:6px; margin-bottom:12px; }
        .tabs a { color:#ccc; text-decoration:none; background:#1c1c1c; border:1px solid #333; padding:7px 13px; border-radius:7px; font-size:13px; font-weight:600; }
        .tabs a.on { background:#ff7918; border-color:#ff7918; color:#21170e; }
        .sheet-wrap { overflow:auto; border:1px solid #e5e7eb; border-radius:8px; }
        table.sheet { width:100%; border-collapse:collapse; font-size:12.5px; }
        table.sheet th { position:sticky; top:0; z-index:1; background:#f3f4f6; color:#4b5563; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; text-align:left; padding:8px 9px; border:1px solid #e5e7eb; white-space:nowrap; }
        table.sheet td { padding:6px 9px; border:1px solid #e5e7eb; white-space:nowrap; background:#fff; color:#1f2937; vertical-align:top; }
        table.sheet td.text { white-space:normal; min-width:280px; max-width:520px; line-height:1.4; }
        .note-text { white-space:pre-wrap; }
        table.sheet td.readers { white-space:normal; min-width:140px; color:#6b7280; }
        table.sheet tr.open td { background:#fff7ed; }
        table.sheet tr.zero td { color:#9ca3af; text-align:center; padding:14px; }
        .tag { display:inline-block; padding:1px 8px; border-radius:999px; background:#f3f4f6; color:#4b5563; font-size:11px; font-weight:700; }
        .tag.hot { background:#ff7918; color:#21170e; }
        .sub { font-size:12px; color:#6b7280; margin-top:4px; white-space:normal; }
        .resolve-box summary { cursor:pointer; color:#c2410c; font-weight:700; }
        .resolve { display:flex; gap:6px; margin-top:6px; }
        .resolve input { width:180px; padding:6px 8px; background:#fff; color:#1f2937; border:1px solid #d1d5db; font-size:12.5px; }
        .resolve button { background:#ff7918; color:#21170e; border:none; border-radius:7px; padding:0 10px; font-weight:700; cursor:pointer; }
        .book-title { font-size:15px; margin:18px 0 8px; }
        .search { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:12px; }
        .search input[type=text] { flex:1 1 220px; width:auto; }
        .search label { display:flex; align-items:center; gap:6px; margin:0; font-size:13px; }
        .search input[type=date] { background:#141414; border:1px solid #333; color:#fff; padding:8px 9px; border-radius:7px; font-family:inherit; }
        .search button { background:#2c2c2c; color:#fff; border:1px solid #444; border-radius:7px; padding:9px 14px; font-weight:600; cursor:pointer; }
        .pages { display:flex; justify-content:center; gap:16px; align-items:center; padding:12px 0; font-size:13px; color:#999; }
        .pages a { color:#ff9a4d; text-decoration:none; font-weight:700; }
        .empty { color:#777; padding:20px 0; text-align:center; font-size:14px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    @php $tz = 'America/Santiago'; @endphp
    <div class="page-inner">
        <h1>Cuaderno de novedades</h1>

        @if (session('status'))<div class="banner">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('shift_notes.store') }}" class="panel">
            @csrf
            <div class="new-grid">
                <div>
                    <label for="note-category">Categoría</label>
                    <select name="category" id="note-category" required>
                        @foreach (\App\Models\ShiftNote::CATEGORIES as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="note-room">Playroom (opcional)</label>
                    <select name="room_id" id="note-room">
                        <option value="">—</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>{{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="note-body">Novedad para el siguiente turno</label>
                    <textarea name="body" id="note-body" maxlength="1000" required>{{ old('body') }}</textarea>
                </div>
            </div>
            <div class="flags">
                <label><input type="checkbox" name="urgent" value="1" @checked(old('urgent'))> Urgente</label>
                <label><input type="checkbox" name="pending" value="1" @checked(old('pending'))> Queda pendiente de resolver</label>
                <button class="btn" type="submit">Registrar novedad</button>
            </div>
        </form>

        <h2 class="book-title">Libro de novedades</h2>
        <div class="tabs">
            @foreach (['todas' => 'Todas', 'pendientes' => 'Pendientes', 'urgentes' => 'Urgentes'] as $key => $label)
                <a href="{{ route('shift_notes.index', ['ver' => $key, 'q' => $search ?: null, 'desde' => $from ?: null, 'hasta' => $to ?: null]) }}" class="{{ $filter === $key ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('shift_notes.index') }}" class="search">
            <input type="hidden" name="ver" value="{{ $filter }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar en el libro" aria-label="Buscar en el libro">
            <label>Desde <input type="date" name="desde" value="{{ $from }}"></label>
            <label>Hasta <input type="date" name="hasta" value="{{ $to }}"></label>
            <button type="submit">Buscar</button>
        </form>

        <div class="sheet-wrap">
            <table class="sheet">
                <thead>
                    <tr><th>Fecha</th><th>Hora</th><th>Categoría</th><th>Playroom</th><th>Novedad</th><th>Registró</th><th>Estado</th><th>Leída por</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($notes as $note)
                        @php $at = $note->created_at->copy()->timezone($tz); @endphp
                        <tr class="{{ $note->isOpen() ? 'open' : '' }}">
                            <td>{{ $at->format('d/m/Y') }}</td>
                            <td>{{ $at->format('H:i') }}</td>
                            <td>{{ $note->category }}</td>
                            <td>{{ $note->room?->name ?? '—' }}</td>
                            <td class="text">
                                @if ($note->urgent)<span class="tag hot">Urgente</span>@endif
                                <span class="note-text">{{ $note->body }}</span>
                                @if ($note->resolved_at)
                                    <div class="sub">Resuelta por {{ $note->resolver?->name ?? '—' }} el {{ $note->resolved_at->timezone($tz)->format('d/m H:i') }}: {{ $note->resolution }}</div>
                                @endif
                            </td>
                            <td>{{ $note->user->name }}</td>
                            <td>
                                @if ($note->isOpen())<span class="tag hot">Pendiente</span>
                                @elseif ($note->resolved_at)<span class="tag">Resuelta</span>
                                @else<span class="tag">Informada</span>@endif
                            </td>
                            <td class="readers">{{ $note->readers->isEmpty() ? '—' : $note->readers->map(fn ($r) => $r->name.' '.\Illuminate\Support\Carbon::parse($r->pivot->read_at)->timezone($tz)->format('d/m H:i'))->implode(' · ') }}</td>
                            <td>
                                @if ($note->isOpen())
                                    <details class="resolve-box">
                                        <summary>Resolver</summary>
                                        <form method="POST" action="{{ route('shift_notes.resolve', $note) }}" class="resolve">
                                            @csrf
                                            <input type="text" name="resolution" maxlength="500" placeholder="Qué se hizo" aria-label="Qué se hizo" required>
                                            <button type="submit">Guardar</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="zero"><td colspan="9">No hay novedades.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($notes->hasPages())
            <div class="pages">
                @if ($notes->previousPageUrl())<a href="{{ $notes->previousPageUrl() }}">← Más nuevas</a>@endif
                <span>Página {{ $notes->currentPage() }} de {{ $notes->lastPage() }}</span>
                @if ($notes->nextPageUrl())<a href="{{ $notes->nextPageUrl() }}">Más antiguas →</a>@endif
            </div>
        @endif
    </div>
</body>
</html>
