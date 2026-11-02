<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="dark">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar mi contraseña — HH Motel</title>
    <style>
        :root { --hh-accent: #ff7918; --hh-accent-hover: #ee6909; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin:0; padding:24px 16px 40px; }
        .card { max-width:440px; margin:40px auto 0; background:#1a1a1a; border:1px solid #2a2a2a; border-radius:14px; padding:28px 26px; }
        .brand { font-size:13px; font-weight:800; letter-spacing:.1em; color:var(--hh-accent); margin-bottom:6px; }
        h1 { font-size:22px; margin:0 0 6px; letter-spacing:-.02em; }
        .lead { color:#aaa; font-size:14px; line-height:1.5; margin:0 0 20px; }
        label { display:block; font-size:13px; color:#ccc; margin:14px 0 6px; }
        input { width:100%; background:#111; border:1px solid #3a3a3a; color:#f4f5f7; padding:11px 12px; border-radius:8px; font-size:16px; font-family:inherit; }
        input:focus { outline:none; border-color:var(--hh-accent); box-shadow:0 0 0 3px #ff791833; }
        .hint { color:#888; font-size:12.5px; margin-top:6px; }
        button.go { width:100%; margin-top:20px; background:var(--hh-accent); color:#21170e; border:none; padding:14px; border-radius:9px; font-size:16px; font-weight:750; cursor:pointer; }
        button.go:hover { background:var(--hh-accent-hover); }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:11px 13px; border-radius:8px; margin-bottom:16px; font-size:13.5px; line-height:1.45; }
        .ok { background:#0f2b22; border:1px solid #1c9169; color:#d6fff0; padding:11px 13px; border-radius:8px; margin-bottom:16px; font-size:13.5px; }
        .foot { margin-top:18px; font-size:13px; }
        .foot a { color:#888; text-decoration:none; }
        .foot a:hover { color:#ddd; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">HH MOTEL</div>
        <h1>Cambiar mi contraseña</h1>
        <p class="lead">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>

        @if (session('status'))
            <div class="ok">✓ {{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.password.update') }}">
            @csrf
            @method('PUT')
            <label for="current_password">Contraseña actual</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required autofocus>
            <label for="password">Nueva contraseña</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
            <div class="hint">Mínimo 8 caracteres.</div>
            <label for="password_confirmation">Repite la nueva contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            <button class="go" type="submit">Cambiar contraseña</button>
        </form>
        <div class="foot"><a href="{{ route('rooms.board') }}">← Volver al tablero</a></div>
    </div>
</body>
</html>
