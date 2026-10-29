<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Mantención — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: none; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 19px; margin:0 0 2px; }
        .sub { color:#999; font-size: 13px; margin: 0 0 24px; }
        .sec-label { font-size: 11px; color:#888; text-transform:uppercase; letter-spacing:.05em; margin: 24px 0 10px; display:flex; align-items:center; gap:8px; }
        .sec-label:first-of-type { margin-top:0; }
        .sec-label .count { background:#2a2a2a; color:#ccc; border-radius:20px; padding:1px 8px; font-size:10.5px; }
        .sec-label.warn .count { background:#3a1c22; color:#e88a9a; }
        .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:12px; }
        .room-card { background:#1c1c1c; border:1px solid #333; border-radius:10px; padding:14px 16px; text-decoration:none; color:#eee; display:block; }
        .room-card:hover { border-color:#666; }
        .room-card.warn { border-color:#7a2d2d; background:#241419; }
        .room-card b { display:block; font-size:14.5px; margin-bottom:2px; }
        .room-card .cat { font-size:11px; color:#999; margin-bottom:8px; }
        .room-card .meta { font-size:12px; color:#aaa; }
        .room-card .meta.warn { color:#e88a9a; }
        .empty { color:#666; font-size:13.5px; padding: 8px 0 4px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    @include('partials.aseo-tabs')
    <h1>Mantención</h1>
    <p class="sub">Solo los Playrooms que tienen una falla pendiente. Toca uno para ver o actualizar la inspección. Las rondas de turno registran las fallas nuevas.</p>

    <div class="sec-label warn">Playrooms que necesitan mantención <span class="count">{{ $needsMaintenance->count() }}</span></div>
    @if ($needsMaintenance->isEmpty())
        <p class="empty">✓ Ningún Playroom necesita mantención por ahora.</p>
    @else
        <div class="grid">
            @foreach ($needsMaintenance as $entry)
                <a class="room-card warn" href="{{ route('rooms.inspections.create', $entry['room']) }}">
                    <b>{{ $entry['room']->name }}</b>
                    <div class="cat">{{ $entry['room']->category->name }}</div>
                    <div class="meta warn">
                        {{ $entry['inspection']->created_at->timezone('America/Santiago')->format('d/m H:i') }}
                        @if ($entry['inspection']->inspected_by) · {{ $entry['inspection']->inspected_by }} @endif
                    </div>
                    @if ($entry['inspection']->notes)
                        <div class="meta warn" style="margin-top:4px;">{{ $entry['inspection']->notes }}</div>
                    @endif
                    @if (! empty($entry['inspection']->photos))
                        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;">
                            @foreach ($entry['inspection']->photos as $i => $photo)
                                <img src="{{ route('rooms.inspections.photo', [$entry['inspection'], $i]) }}" alt="Foto {{ $i + 1 }}" loading="lazy" style="width:64px; height:64px; object-fit:cover; border-radius:6px; border:1px solid #444;">
                            @endforeach
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    </div>
</body>
</html>
