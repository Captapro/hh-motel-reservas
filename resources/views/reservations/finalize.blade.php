<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Finalizar {{ $booking->code }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 520px; margin: 0; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 14px; margin-bottom: 6px; }
        .warn { background:#3a331c; border:1px solid #6b5a1e; color:#e8c76f; padding:12px 14px; border-radius:8px; margin: 16px 0; font-size:14px; }
        .card { background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; }
        .cat-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.04em; margin: 14px 0 8px; }
        .cat-label:first-child { margin-top:0; }
        .prod-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-top:1px solid #262626; }
        .prod-row:first-of-type { border-top:none; }
        .prod-row .name { font-size:14px; }
        .prod-row .price { font-size:12px; color:#888; }
        .prod-row input { width:60px; background:#111; border:1px solid #333; color:#fff; padding:7px; border-radius:6px; text-align:center; }
        .existing { font-size:13px; color:#999; margin-bottom: 4px; }
        label.confirm { display:flex; gap:14px; align-items:center; font-size:15px; margin: 20px 0; line-height:1.45; padding:16px 18px; border:2px solid #777; border-radius:10px; background:#fff; color:#25272b; cursor:pointer; transition:.15s ease; }
        label.confirm:hover { border-color:#ff7918; background:#fff9f4; }
        label.confirm:has(input:checked) { border-color:#ff7918; background:#fff2e7; box-shadow:0 0 0 3px #ff791833; }
        label.confirm input { appearance:none; flex:none; width:25px; height:25px; margin:0; border:2px solid #565a61; border-radius:6px; background:#fff; position:relative; cursor:pointer; }
        label.confirm input:checked { border-color:#ff7918; background:#ff7918; }
        label.confirm input:checked::after { content:'✓'; position:absolute; inset:0; display:grid; place-items:center; color:#21170e; font-size:18px; font-weight:800; }
        label.confirm strong { display:block; font-size:11px; color:#a74600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3px; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:12px 14px; border-radius:8px; margin-bottom: 18px; font-size:14px; }
        button { width:100%; background:#ff7918; color:#fff; border:none; padding:14px; border-radius:8px; font-size:15px; font-weight:600; }
        a.back { display:block; text-align:center; margin-top: 16px; color:#999; font-size: 13px; text-decoration:none; }
        .manual-extras summary { cursor:pointer; list-style:none; font-size:13.5px; color:#999; padding:4px 0; }
        .manual-extras summary::-webkit-details-marker { display:none; }
        .manual-extras summary::before { content:'+ '; color:#ff7918; font-weight:700; }
        .manual-extras[open] summary::before { content:'− '; }
        .manual-extras summary:hover { color:#eee; }
        /* Dos columnas en pantalla ancha: confirmación y acción | consumo de último momento */
        .page-inner { max-width: 1100px; padding-top:14px; }
        .fin-grid { display:grid; grid-template-columns: minmax(0,.8fr) minmax(0,1.2fr); gap: 0 28px; align-items:start; }
        .fin-col > label.confirm:first-child, .fin-col > .card:first-child { margin-top:0; }
        button.save { width:100%; margin-top:14px; }
        .fin-col .manual-extras .card { column-count:2; column-gap:24px; padding:12px 16px; margin-bottom:12px; }
        .fin-col .manual-extras .prod-row { break-inside:avoid; padding:4px 0; gap:8px; }
        .fin-col .manual-extras .prod-row .name { font-size:13px; line-height:1.25; }
        .fin-col .manual-extras .prod-row .price { font-size:11px; }
        .fin-col .manual-extras .prod-row input { width:48px; padding:5px 4px; font-size:13px; }
        @media (max-width: 860px) { .fin-grid { grid-template-columns: minmax(0, 1fr); } .fin-col .manual-extras .card { column-count:1; } }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
    <h1>Finalizar reserva</h1>
    <div class="code">{{ $booking->code }} — {{ $booking->room->name }} · {{ $booking->customer->name }}</div>

    @if ($errors->any())
        <div class="errors">
            @foreach (array_unique($errors->all()) as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="warn">El cliente se retira — no olvides revisar todas las instalaciones del Playroom antes de cerrar.</div>

    <form method="POST" action="{{ route('bookings.finalize.store', $booking->code) }}">
        @csrf

        <div class="fin-grid">
            <div class="fin-col">
        @if ($booking->addons->isNotEmpty())
            <div class="card">
                <div class="cat-label">Ya cargado en esta reserva</div>
                @foreach ($booking->addons as $addon)
                    <div class="existing">{{ $addon->description }} — ${{ number_format($addon->amount, 0, ',', '.') }}</div>
                @endforeach
            </div>
        @endif

        <label class="confirm">
            <input type="checkbox" name="confirm_room_checked" value="1" required>
            <span><strong>Confirmación necesaria</strong>Confirmo que revisé el Playroom, sus instalaciones, artículos y estado general, y está todo OK.</span>
        </label>

                <button type="submit" class="save">Confirmar y finalizar reserva</button>
                <a class="back" href="{{ route('reservations.show', $booking->code) }}">← Volver sin finalizar</a>
            </div>
            <div class="fin-col">
        <details class="manual-extras">
            <summary>Se consumió algo de último momento que falta cargar (combos, bebidas, etc.)</summary>

            @if ($combos->isNotEmpty())
                <div class="card">
                    <div class="cat-label">Combos</div>
                    @foreach ($combos as $combo)
                        <div class="prod-row">
                            <div>
                                <div class="name">{{ $combo->name }}</div>
                                <div class="price">
                                    ${{ number_format($combo->price, 0, ',', '.') }}
                                    @if ($combo->description)
                                        · {{ $combo->description }}
                                    @endif
                                    · {{ $combo->isOutOfStock() ? 'agotado' : '' }}
                                </div>
                            </div>
                            <input type="number" name="combo_quantities[{{ $combo->id }}]" data-combo-id="{{ $combo->id }}" data-choices="{{ json_encode($combo->choicesSpec()) }}" value="0" min="0" max="{{ $combo->availableCount() !== null ? min(20, $combo->availableCount()) : 20 }}" @disabled($combo->isOutOfStock())>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card">
                @foreach ($products->groupBy('category') as $category => $items)
                    <div class="cat-label">{{ $category ?? 'Otros' }}</div>
                    @foreach ($items as $product)
                        <div class="prod-row">
                            <div>
                                <div class="name">{{ $product->name }}</div>
                                <div class="price">
                                    ${{ number_format($product->price, 0, ',', '.') }}
                                    @if ($product->track_inventory)
                                        · {{ $product->isOutOfStock() ? 'agotado' : $product->stock.' disp.' }}
                                    @endif
                                </div>
                            </div>
                            <input type="number" name="quantities[{{ $product->id }}]" value="0" min="0" max="{{ $product->track_inventory ? min(20, $product->stock) : 20 }}" @disabled($product->isOutOfStock())>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </details>

            </div>
        </div>
    </form>

    </div>
@include('partials.combo-choices')
</body>
</html>
