<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — Panel de mucamas — HH Motel</title>
    <style>
        :root {
            color-scheme: light;
            --hh-accent: #ff7918;
            --hh-accent-hover: #ee6909;
            --hh-canvas: #f5f5f4;
            --hh-surface: #353638;
        }
        * { box-sizing: border-box; }
        body { background:var(--hh-canvas); color:#202124; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; line-height: 1.45; }
        .wrap { max-width: 380px; margin: 0 auto; padding: 40px 18px 50px; }
        .brand { font-size: 13px; font-weight: 800; letter-spacing: .1em; color: var(--hh-accent); text-align:center; margin-bottom: 20px; }
        .card { background:var(--hh-surface); color:#f5f5f5; border-radius:14px; padding:24px; }
        h1 { font-size: 19px; margin: 0 0 18px; text-align:center; }
        label { display:block; font-size:12.5px; color:#c1c3c7; margin:0 0 8px; }
        select, input { width:100%; background:#202123; border:1px solid #62656b; color:#f4f5f7; padding:13px 12px; border-radius:8px; font-size:16px; min-height:48px; margin-bottom:16px; }
        input[name="pin"] { letter-spacing:.3em; font-size:20px; text-align:center; }
        button.submit { width:100%; background:var(--hh-accent); color:#21170e; border:none; padding:15px; border-radius:9px; font-size:15.5px; font-weight:800; cursor:pointer; }
        button.submit:hover { background:var(--hh-accent-hover); }
        .banner.error { background:#3a1c1c; color:#f3b8b8; border-radius:9px; padding:12px 14px; margin-bottom:16px; font-size:13.5px; }
        .empty { color:#c1c3c7; font-size:13.5px; text-align:center; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">HH MOTEL</div>
        <div class="card">
            <h1>Entrar como mucama</h1>

            @if ($errors->any())
                @foreach (array_unique($errors->all()) as $error)
                    <div class="banner error">{{ $error }}</div>
                @endforeach
            @endif

            @if ($staff->isEmpty())
                <p class="empty">No hay mucamas activas cargadas todavía -- avisale a recepción para que las agregue en Personal (con su PIN).</p>
            @else
                <form method="POST" action="{{ route('mucamas.login.store') }}">
                    @csrf
                    <input type="hidden" name="next" value="{{ old('next', $next) }}">
                    <label for="staff_id">¿Quién eres?</label>
                    <select id="staff_id" name="staff_id" required>
                        <option value="" selected disabled>Elige tu nombre</option>
                        @foreach ($staff as $person)
                            <option value="{{ $person->id }}" @selected(old('staff_id') == $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                    <label for="pin">PIN</label>
                    <input type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" id="pin" name="pin" placeholder="••••" required autofocus>
                    <button class="submit" type="submit">Entrar →</button>
                </form>
            @endif
        </div>
    </div>
</body>
</html>
