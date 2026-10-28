<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="dark">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $kind === 'recibir' ? 'Recibir turno' : 'Entregar turno' }} — HH Motel</title>
    <style>
        :root { --hh-accent: #ff7918; --hh-accent-hover: #ee6909; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin:0; padding:24px 16px 40px; }
        .card { max-width:560px; margin:0 auto; background:#1a1a1a; border:1px solid #2a2a2a; border-radius:14px; padding:28px 26px; }
        .brand { font-size:13px; font-weight:800; letter-spacing:.1em; color:var(--hh-accent); margin-bottom:6px; }
        h1 { font-size:22px; margin:0 0 6px; letter-spacing:-.02em; }
        .lead { color:#aaa; font-size:14px; line-height:1.5; margin:0 0 20px; }
        h2 { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:#999; margin:24px 0 10px; }
        label.big { display:block; font-size:13px; color:#ccc; margin-bottom:6px; }
        input[type=number], textarea { width:100%; background:#111; border:1px solid #3a3a3a; color:#f4f5f7; padding:11px 12px; border-radius:8px; font-size:16px; font-family:inherit; }
        input:focus, textarea:focus { outline:none; border-color:var(--hh-accent); box-shadow:0 0 0 3px #ff791833; }
        .money { position:relative; }
        .money span { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#888; font-size:16px; }
        .money input { padding-left:26px; font-size:20px; font-weight:700; font-variant-numeric:tabular-nums; }
        .prod { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:9px 0; border-top:1px solid #262626; }
        .prod:first-of-type { border-top:none; }
        .prod .name { font-size:14.5px; }
        .prod input { width:96px; text-align:center; }
        .empty { color:#777; font-size:13.5px; }
        .hint { color:#888; font-size:12.5px; margin-top:6px; line-height:1.5; }
        .accept { display:flex; gap:10px; align-items:flex-start; margin-top:20px; font-size:14px; color:#ddd; line-height:1.45; }
        .accept input { margin-top:3px; width:18px; height:18px; flex:none; }
        button.go { width:100%; margin-top:18px; background:var(--hh-accent); color:#21170e; border:none; padding:14px; border-radius:9px; font-size:16px; font-weight:750; cursor:pointer; }
        button.go:hover { background:var(--hh-accent-hover); }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:11px 13px; border-radius:8px; margin-bottom:16px; font-size:13.5px; line-height:1.45; }
        .foot { display:flex; justify-content:space-between; align-items:center; margin-top:18px; font-size:13px; }
        .foot a, .foot button { color:#888; background:none; border:none; font:inherit; cursor:pointer; text-decoration:none; padding:0; }
        .foot a:hover, .foot button:hover { color:#ddd; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">HH MOTEL</div>
        @if ($kind === 'recibir')
            <h1>Recibir turno</h1>
            <p class="lead">Hola {{ auth()->user()->name }}. Antes de empezar, cuenta la caja chica y los productos. Al aceptar, lo que recibes queda a tu cargo.</p>
        @else
            <h1>Entregar turno</h1>
            <p class="lead">Antes de salir, cuenta la caja chica y los productos que dejas.</p>
        @endif

        @if ($errors->any())
            <div class="errors">
                @foreach (array_unique($errors->all()) as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('shift.store', $kind) }}" id="handover-form">
            @csrf
            <h2>Caja chica</h2>
            <label class="big" for="counted_cash">Efectivo que hay en la caja</label>
            <div class="money"><span>$</span><input type="number" id="counted_cash" name="counted_cash" min="0" step="1" inputmode="numeric" value="{{ old('counted_cash') }}" required autofocus></div>
            <p class="hint">Cuenta billetes y monedas. Escribe solo números, sin puntos.</p>

            <h2>Productos</h2>
            @forelse ($products as $product)
                <div class="prod">
                    <label class="name" for="p{{ $product->id }}">{{ $product->name }}</label>
                    <input type="number" id="p{{ $product->id }}" name="counts[{{ $product->id }}]" min="0" step="1" inputmode="numeric" value="{{ old('counts.'.$product->id) }}" required>
                </div>
            @empty
                <p class="empty">No hay productos con control de stock.</p>
            @endforelse

            <h2>Notas</h2>
            <textarea name="notes" rows="3" placeholder="Si algo no cuadra, explica qué pasó (ej: faltan 2 bebidas, se pagó un proveedor)">{{ old('notes') }}</textarea>

            <label class="accept">
                <input type="checkbox" required>
                <span>{{ $kind === 'recibir' ? 'Conté la caja y los productos, y acepto el turno.' : 'Conté la caja y los productos, y entrego el turno.' }}</span>
            </label>

            <button type="submit" class="go">{{ $kind === 'recibir' ? 'Acepto el turno' : 'Entregar turno y salir' }}</button>
        </form>

        <div class="foot">
            @if ($kind === 'entregar')
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route(auth()->user()->homeRoute()) }}">← Volver sin salir</a>
            @else
                <span></span>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">{{ $kind === 'recibir' ? 'No soy yo, salir' : 'Salir sin entregar' }}</button>
            </form>
        </div>
    </div>
    <script>
        // Evita el doble envío si la conexión está lenta.
        document.getElementById('handover-form').addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            var b = this.querySelector('button.go');
            setTimeout(function () { b.disabled = true; b.textContent = 'Guardando…'; }, 0);
            setTimeout(function () { b.disabled = false; }, 20000);
        });
    </script>
</body>
</html>
