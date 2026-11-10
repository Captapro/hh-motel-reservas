<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Packs — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 900px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom:2px; }
        .sub { color:#999; font-size: 13px; margin-bottom: 18px; }
        input[type=search] { width:100%; max-width:420px; background:#111; border:1px solid #3a3a3a; color:#f1f1f1; padding:10px 13px; border-radius:8px; font-size:14px; margin-bottom:16px; }
        input[type=search]:focus { outline:none; border-color:#ff7918; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:12px; }
        .pack { background:#1c1c1c; border:1px solid #333; border-radius:12px; padding:14px 16px; }
        .pack.out { opacity:.5; }
        .pack.hide { display:none; }
        .pack-head { display:flex; justify-content:space-between; align-items:baseline; gap:10px; }
        .pack-name { font-weight:700; font-size:15px; color:#fff; }
        .pack-price { font-weight:800; font-size:16px; color:#ff7918; white-space:nowrap; }
        .pack-desc { color:#aaa; font-size:12.5px; margin:6px 0 0; line-height:1.4; }
        ul { margin:10px 0 0; padding-left:18px; color:#ddd; font-size:13px; line-height:1.55; }
        .stock { display:inline-block; margin-top:10px; font-size:11.5px; font-family:ui-monospace,monospace; padding:2px 9px; border-radius:20px; background:#222; color:#8fe0ad; }
        .stock.low { color:#f0b84b; } .stock.none { color:#e88a9a; }
        .empty { color:#777; font-size:14px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
        <h1>Packs disponibles</h1>
        <p class="sub">Qué incluye cada uno y cuántos se pueden armar hoy. Se venden desde la reserva, en "Agregar al consumo".</p>
        <input type="search" id="pack-search" placeholder="🔍 Buscar pack…" autocomplete="off">

        @if ($combos->isEmpty())
            <p class="empty">No hay packs activos.</p>
        @else
            <div class="grid">
                @foreach ($combos as $combo)
                    @php $avail = $combo->availableCount(); @endphp
                    <div class="pack {{ $combo->isOutOfStock() ? 'out' : '' }}" data-name="{{ mb_strtolower($combo->name.' '.$combo->itemsLabel()) }}">
                        <div class="pack-head">
                            <span class="pack-name">{{ $combo->name }}</span>
                            <span class="pack-price">${{ number_format($combo->price, 0, ',', '.') }}</span>
                        </div>
                        @if ($combo->description)<p class="pack-desc">{{ $combo->description }}</p>@endif
                        <ul>
                            @foreach ($combo->items as $item)
                                <li>{{ $item->label() }}</li>
                            @endforeach
                        </ul>
                        <span class="stock {{ $avail === null ? '' : ($avail <= 0 ? 'none' : ($avail <= 3 ? 'low' : '')) }}">
                            {{ $avail === null ? 'Sin límite de stock' : ($avail <= 0 ? 'Agotado' : $avail.' disponibles') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <script>
        document.getElementById('pack-search')?.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.pack').forEach(el => el.classList.toggle('hide', q && !el.dataset.name.includes(q)));
        });
    </script>
</body>
</html>
