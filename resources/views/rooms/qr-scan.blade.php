<!doctype html>
<html lang="es" style="background:#111;color:#eee">
<head>
    <meta charset="utf-8"><meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Escanear QR — HH Motel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { background:#111; color:#eee; font-family: -apple-system, "Segoe UI", sans-serif; margin: 0; }
        .page-inner { max-width: 480px; margin: 0 auto; padding: 24px 24px 60px; }
        h1 { font-size: 18px; margin-bottom:2px; }
        .sub { color:#999; font-size: 13px; margin-bottom: 18px; }
        a.back-btn { display:inline-block; background:#1c1c1c; border:1px solid #333; color:#ccc; text-decoration:none; padding:8px 13px; border-radius:7px; font-size:12.5px; font-weight:600; margin-bottom:16px; }
        a.back-btn:hover { border-color:#ff7918; color:#ff7918; }
        .scan-box { position:relative; width:100%; aspect-ratio:1/1; background:#000; border-radius:14px; overflow:hidden; border:1px solid #333; }
        .scan-box video { width:100%; height:100%; object-fit:cover; display:block; }
        .scan-frame { position:absolute; inset:12%; border:3px solid #ff7918; border-radius:16px; box-shadow:0 0 0 2000px rgba(0,0,0,.35); pointer-events:none; }
        .status-line { text-align:center; font-size:13px; color:#999; margin-top:14px; min-height:18px; }
        .errors { background:#3a1c1c; border:1px solid #7a2d2d; color:#f3b8b8; padding:14px 16px; border-radius:10px; font-size:13.5px; margin-bottom:16px; }
        .fallback { text-align:center; margin-top:18px; }
        .fallback a { color:#62656c; text-decoration:underline; font-size:12.5px; }
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="page-inner">
        <a class="back-btn" href="{{ route('shift_round.index') }}">← Volver a la ronda</a>
        <h1>Escanear QR del Playroom</h1>
        <p class="sub">Apunta la cámara al código QR pegado en la puerta de la Playroom.</p>

        <div id="errors-box"></div>

        <div class="scan-box">
            <video id="qr-video" autoplay muted playsinline></video>
            <div class="scan-frame"></div>
        </div>
        <div class="status-line" id="status-line">Pidiendo acceso a la cámara…</div>

        <div class="fallback">
            <a href="{{ route('shift_round.index') }}">No tengo cámara disponible -- volver a la lista</a>
        </div>
    </div>

    <canvas id="qr-canvas" style="display:none;"></canvas>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        (function () {
            const video = document.getElementById('qr-video');
            const canvas = document.getElementById('qr-canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const statusLine = document.getElementById('status-line');
            const errorsBox = document.getElementById('errors-box');
            const allowedOrigin = window.location.origin;
            let scanning = false;
            let rafId = null;

            function showError(message) {
                errorsBox.innerHTML = '<div class="errors">' + message + '</div>';
            }

            function tick() {
                if (!scanning) return;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = window.jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
                    if (code && code.data) {
                        handleDecoded(code.data);
                        return;
                    }
                }
                rafId = requestAnimationFrame(tick);
            }

            function handleDecoded(text) {
                scanning = false;
                let url;
                try {
                    url = new URL(text, allowedOrigin);
                } catch (e) {
                    statusLine.textContent = 'Ese código no se pudo leer -- prueba de nuevo.';
                    scanning = true;
                    rafId = requestAnimationFrame(tick);
                    return;
                }

                // Solo seguimos el link si es un QR de Playroom de ESTE sistema --
                // nunca se navega a un dominio o ruta distinta, aunque el QR físico
                // esté dañado o alguien pegue uno que no corresponde.
                if (url.origin !== allowedOrigin || !/^\/qr\/habitacion\/\d+$/.test(url.pathname)) {
                    statusLine.textContent = 'Ese QR no es de un Playroom de HH Motel -- prueba con otro.';
                    scanning = true;
                    rafId = requestAnimationFrame(tick);
                    return;
                }

                statusLine.textContent = 'Confirmando escaneo…';
                const roomId = url.pathname.split('/').pop();
                fetch('/qr/habitacion/' + roomId + '/confirmar-escaneo', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                }).then(function (r) {
                    if (!r.ok) throw new Error('confirm-scan failed');
                    statusLine.textContent = '¡Listo! Abriendo el Playroom…';
                    const finalUrl = new URL(url.pathname, allowedOrigin);
                    @if (request('ronda'))
                        finalUrl.searchParams.set('ronda', '1');
                    @endif
                    window.location.href = finalUrl.toString();
                }).catch(function () {
                    statusLine.textContent = 'No se pudo confirmar el escaneo -- prueba de nuevo.';
                    scanning = true;
                    rafId = requestAnimationFrame(tick);
                });
            }

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function (stream) {
                    video.srcObject = stream;
                    scanning = true;
                    statusLine.textContent = 'Buscando código QR…';
                    rafId = requestAnimationFrame(tick);
                })
                .catch(function (err) {
                    statusLine.textContent = '';
                    showError('No se pudo abrir la cámara (' + (err.message || err.name || 'permiso denegado') + '). Revisa que el navegador tenga permiso de cámara, o usa el link de abajo para ir a la lista.');
                });

            window.addEventListener('pagehide', function () {
                scanning = false;
                if (rafId) cancelAnimationFrame(rafId);
                if (video.srcObject) {
                    video.srcObject.getTracks().forEach(function (t) { t.stop(); });
                }
            });
        })();
    </script>
</body>
</html>
