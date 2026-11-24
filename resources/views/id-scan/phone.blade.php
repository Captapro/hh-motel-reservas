<!doctype html>
<html lang="es" style="background:#000;color:#fff">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Escanear carnet — HH Motel</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin:0; height:100%; background:#000; color:#fff; font:16px/1.4 -apple-system,"Segoe UI",sans-serif; }
        #app { display:flex; flex-direction:column; height:100%; }
        #msg { padding:14px 16px; background:rgba(0,0,0,.8); text-align:center; font-weight:700; min-height:56px; }
        #stage { position:relative; flex:1; min-height:0; overflow:hidden; background:#000; }
        video { width:100%; height:100%; object-fit:cover; }
        .frame { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(88%,560px); aspect-ratio:1.586; border:3px solid #ff7918; border-radius:16px; box-shadow:0 0 0 9999px rgba(0,0,0,.45); pointer-events:none; }
        #bar { padding:14px 16px calc(14px + env(safe-area-inset-bottom)); background:rgba(0,0,0,.9); display:flex; gap:12px; align-items:center; justify-content:center; }
        button { font:800 17px -apple-system,"Segoe UI",sans-serif; border:0; border-radius:999px; padding:16px 22px; cursor:pointer; }
        #go { flex:1; max-width:340px; background:#ff7918; color:#fff; }
        #go:disabled { opacity:.55; }
        .center { display:flex; align-items:center; justify-content:center; height:100%; padding:28px; text-align:center; font-size:18px; }
    </style>
</head>
<body>
@if (! $valid)
    <div class="center">Este código ya venció o no existe.<br>Genera uno nuevo en el computador de recepción.</div>
@else
    <div id="app">
        <div id="msg">Encuadra el carnet del titular dentro del marco</div>
        <div id="stage">
            <video id="video" playsinline muted autoplay></video>
            <div class="frame"></div>
        </div>
        <div id="bar"><button type="button" id="go">📸 Escanear carnet</button></div>
    </div>
    <script>
        (function () {
            const msg = document.getElementById('msg'), go = document.getElementById('go'), video = document.getElementById('video');
            const say = (t, c) => { msg.textContent = t; msg.style.color = c || '#fff'; };
            let reads = 0, busy = false;

            async function start() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { say('Este navegador no deja abrir la cámara. Prueba con Chrome o Safari.', '#ffb4b4'); go.disabled = true; return; }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
                    try { await stream.getVideoTracks()[0].applyConstraints({ advanced: [{ focusMode: 'continuous' }] }); } catch (e) {}
                    video.srcObject = stream;
                    video.play().catch(() => {});
                } catch (e) {
                    say('No pude abrir la cámara. Dale permiso a la cámara en tu navegador y recarga.', '#ffb4b4');
                    go.disabled = true;
                }
            }

            function shrink(w, h, max) {
                const scale = Math.min(1, max / Math.max(w, h));
                const c = document.createElement('canvas');
                c.width = Math.round(w * scale); c.height = Math.round(h * scale);
                c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
                return new Promise((res, rej) => c.toBlob(b => (b ? res(b) : rej(new Error('imagen'))), 'image/jpeg', 0.85));
            }

            go.addEventListener('click', async function () {
                if (busy || !video.videoWidth) return;
                busy = true; go.disabled = true;
                say('Leyendo el carnet…', '#ffb379');
                try {
                    const blob = await shrink(video.videoWidth, video.videoHeight, 1600);
                    const form = new FormData();
                    form.append('photo', blob, 'carnet.jpg');
                    form.append('_token', '{{ csrf_token() }}');
                    const r = await fetch('{{ route('id_scan.upload', $token) }}', { method: 'POST', body: form, headers: { 'Accept': 'application/json' } });
                    const data = await r.json().catch(() => null);
                    if (r.ok && data && data.ok) {
                        reads += 1;
                        say('✓ ' + (data.name || 'Carnet leído') + ' — ya está en el computador. Pon el siguiente carnet (acompañante) o cierra esta pantalla.', '#9be39b');
                    } else if (r.status === 410) {
                        say((data && data.error) || 'La sesión venció. Genera otro QR en el computador.', '#ffb4b4');
                        go.disabled = true; busy = false; return;
                    } else {
                        say((data && (data.error || data.message)) || 'No se pudo leer el carnet. Intenta de nuevo con más luz.', '#ffb4b4');
                    }
                } catch (e) {
                    say('No se pudo leer el carnet. Intenta de nuevo.', '#ffb4b4');
                }
                busy = false; go.disabled = false;
            });

            start();
        })();
    </script>
@endif
</body>
</html>
