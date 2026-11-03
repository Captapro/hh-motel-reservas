<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <title>Credencial {{ $booking->code }} — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin:0; }
        .wrap { max-width:460px; margin:0 auto; padding:28px 20px 50px; text-align:center; }
        h1 { font-size:22px; margin:0 0 4px; }
        .sub { color:#9a9a9a; font-size:14px; margin:0 0 20px; }
        .card { text-align:left; background:#1c1c1c; border:1px solid #333; border-radius:14px; padding:14px 18px; margin-bottom:16px; font-size:15px; line-height:1.45; }
        .card b { color:#e8c76f; }
        .ok { background:#0f2b22; border:1px solid #1c9169; color:#6ee7b7; border-radius:12px; padding:14px 16px; font-size:15px; font-weight:600; margin-bottom:16px; }
        .err { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; border-radius:12px; padding:12px 14px; font-size:14px; margin-bottom:16px; }
        label.shot { display:block; background:#ff7918; color:#111; font-weight:800; font-size:18px; padding:18px; border-radius:14px; cursor:pointer; }
        input[type=file] { display:none; }
        img.prev { width:100%; border-radius:12px; margin:14px 0; display:none; }
        button { width:100%; background:#1c9169; color:#fff; border:none; font-weight:800; font-size:17px; padding:16px; border-radius:14px; display:none; }
        .muted { color:#777; font-size:13px; margin-top:18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Foto del documento</h1>
        <p class="sub">{{ $booking->code }} · {{ $booking->room->name }} · {{ $booking->customer->name }}</p>

        @if (session('status'))
            <div class="ok">✅ {{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        @if (! $required)
            <div class="card">Esta reserva no tiene un cupón que pida documento. No hay que sacar foto.</div>
        @else
            <div class="card">
                Cupón <b>{{ $booking->coupon_code_snapshot }}</b>: pide {{ $booking->coupon->verification_note ?: 'un documento que lo respalde' }}.
                Saca una foto donde se lea el nombre y la vigencia.
            </div>
            @if ($proof && ! session('status'))
                <div class="ok">Ya hay una foto guardada ({{ $proof->created_at->timezone('America/Santiago')->format('H:i') }}). Puedes sacar otra si no se lee bien.</div>
            @endif
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
                @csrf
                <label class="shot" for="photo">📷 Sacar foto</label>
                <input type="file" id="photo" name="photo" accept="image/*" capture="environment">
                <img class="prev" id="prev" alt="Vista previa">
                <button type="submit" id="send">Guardar foto</button>
            </form>
            <p class="muted">La foto se borra sola a los 30 días.</p>
        @endif
    </div>
    <script>
        document.getElementById('photo')?.addEventListener('change', function () {
            if (!this.files.length) return;
            const prev = document.getElementById('prev');
            prev.src = URL.createObjectURL(this.files[0]);
            prev.style.display = 'block';
            document.getElementById('send').style.display = 'block';
            document.querySelector('label.shot').textContent = '📷 Sacar otra';
        });
    </script>
</body>
</html>
