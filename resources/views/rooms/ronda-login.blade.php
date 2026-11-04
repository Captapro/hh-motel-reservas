<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.pwa-head', ['appName' => 'HH Ronda', 'manifest' => 'manifest-ronda.webmanifest'])
    <title>Entrar a la ronda — HH Motel</title>
    <style>
        :root { color-scheme:light; }
        * { box-sizing:border-box; }
        body { margin:0; background:#f4f4f2; color:#1f2937; font-family:-apple-system,'Segoe UI',sans-serif; min-height:100vh; }
        .top { padding:12px 16px; background:#111; color:#ff9a4d; font-size:14px; font-weight:700; }
        .wrap { max-width:420px; margin:0 auto; padding:24px 16px; }
        h1 { font-size:24px; margin:0 0 4px; letter-spacing:-.02em; }
        .lead { margin:0 0 16px; color:#6b7280; font-size:14px; }
        .box { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px; }
        label { display:block; font-size:13px; font-weight:600; color:#374151; margin:12px 0 5px; }
        label:first-of-type { margin-top:0; }
        select, input { width:100%; min-height:48px; border:1px solid #d1d5db; border-radius:9px; background:#fff; color:#1f2937; font:16px -apple-system,'Segoe UI',sans-serif; padding:10px 12px; }
        input { letter-spacing:.3em; font-size:20px; text-align:center; }
        .hint { margin:10px 0 0; padding:9px 11px; border-radius:9px; background:#fff3e6; color:#9a3412; font-size:13px; font-weight:600; }
        .errors { padding:11px 14px; border-radius:10px; margin-bottom:12px; font-size:14px; font-weight:600; background:#fde8e8; border:1px solid #e88a8a; color:#991b1b; }
        button { width:100%; min-height:52px; margin-top:14px; border:none; border-radius:11px; background:#ff7918; color:#21170e; font:800 17px -apple-system,'Segoe UI',sans-serif; cursor:pointer; }
        .alt { display:block; margin-top:16px; text-align:center; color:#6b7280; font-size:13px; }
        .empty { color:#6b7280; font-size:14px; margin:0; }
    </style>
</head>
<body>
    <div class="top">HH MOTEL · Ronda de turno</div>
    <div class="wrap">
        <h1>Entrar a la ronda</h1>
        <p class="lead">Elige tu nombre e ingresa tu PIN.</p>

        @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif

        <div class="box">
            @if ($hosts->isEmpty())
                <p class="empty">No hay anfitriones activos.</p>
            @else
                <form method="POST" action="{{ route('ronda.login.store') }}">
                    @csrf
                    <label for="user_id">¿Quién eres?</label>
                    <select id="user_id" name="user_id" required>
                        <option value="" selected disabled>Elige tu nombre</option>
                        @foreach ($hosts as $host)
                            <option value="{{ $host->id }}" data-new="{{ $host->ronda_pin ? '0' : '1' }}" @selected(old('user_id') == $host->id)>{{ $host->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint" id="new-hint" hidden>Aún no tienes PIN. Crea uno de 4 dígitos: lo usarás siempre para entrar.</p>
                    <label for="pin" id="pin-label">PIN</label>
                    <input type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" id="pin" name="pin" placeholder="••••" autocomplete="off" required>
                    <div id="repeat" hidden>
                        <label for="pin_confirmation">Repite tu PIN</label>
                        <input type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" id="pin_confirmation" name="pin_confirmation" placeholder="••••" autocomplete="off">
                    </div>
                    <button type="submit" id="submit">Entrar</button>
                </form>
            @endif
        </div>
        <a class="alt" href="{{ route('login') }}">Entrar con correo y clave</a>
    </div>
    <script>
        (function () {
            const select = document.getElementById('user_id');
            if (!select) return;
            const sync = function () {
                const option = select.options[select.selectedIndex];
                const isNew = !!option && option.dataset.new === '1';
                document.getElementById('new-hint').hidden = !isNew;
                document.getElementById('repeat').hidden = !isNew;
                document.getElementById('pin_confirmation').required = isNew;
                document.getElementById('pin-label').textContent = isNew ? 'Crea tu PIN' : 'PIN';
                document.getElementById('submit').textContent = isNew ? 'Crear PIN y entrar' : 'Entrar';
            };
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
</body>
</html>
