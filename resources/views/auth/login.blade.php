<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="dark">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión — HH Motel</title>
    {{-- Quien comparte la dirección raíz del sitio (app.hhmotel.cl) cae acá: sin esto no sale imagen ni texto en WhatsApp o redes. --}}
    @include('partials.social-meta')
    <style>
        :root { --hh-accent: #ff7918; --hh-accent-hover: #ee6909; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { width:100%; max-width:380px; background:#1a1a1a; border:1px solid #2a2a2a; border-radius:14px; padding:32px 28px; }
        .brand { font-size:13px; font-weight:800; letter-spacing:.1em; color:var(--hh-accent); margin-bottom:6px; }
        h1 { font-size:21px; margin:0 0 22px; letter-spacing:-.02em; }
        label { display:block; font-size:12.5px; color:#aaa; margin:14px 0 6px; }
        input[type=email], input[type=password] { width:100%; background:#111; border:1px solid #3a3a3a; color:#f4f5f7; padding:11px 12px; border-radius:8px; font-size:15px; }
        input:focus { outline:none; border-color:var(--hh-accent); box-shadow:0 0 0 3px #ff791833; }
        .remember { display:flex; align-items:center; gap:8px; margin-top:14px; font-size:13px; color:#bbb; }
        .remember input { width:auto; }
        button { width:100%; margin-top:22px; background:var(--hh-accent); color:#21170e; border:none; padding:13px; border-radius:9px; font-size:15px; font-weight:750; cursor:pointer; }
        button:hover { background:var(--hh-accent-hover); }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:11px 13px; border-radius:8px; margin-bottom:16px; font-size:13.5px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">HH MOTEL</div>
        <h1>Acceso interno</h1>

        @if ($errors->any())
            <div class="errors">
                @foreach (array_unique($errors->all()) as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>

            <label class="remember"><input type="checkbox" name="remember" checked> Mantener sesión iniciada</label>

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
