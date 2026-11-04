<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111111">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="HH Admin">
    <link rel="manifest" href="{{ asset('manifest-admin.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png').'?v=2' }}">
    <title>HH Admin</title>
    <style>
        :root { color-scheme:light; }
        * { box-sizing:border-box; }
        body { margin:0; background:#f4f4f2; color:#1f2937; font-family:-apple-system,'Segoe UI',sans-serif; min-height:100vh; padding-bottom:30px; }
        .top { position:sticky; top:0; z-index:5; display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; background:#111; color:#ff9a4d; font-size:14px; font-weight:700; }
        .top a, .top button { background:none; border:none; padding:0; color:#ff9a4d; font:inherit; font-size:13px; font-weight:500; cursor:pointer; text-decoration:none; }
        .top form { margin:0; }
        .top div { display:flex; gap:14px; align-items:center; }
        .wrap { max-width:1100px; margin:0 auto; padding:14px 16px; }
        h1 { font-size:24px; margin:4px 0 12px; letter-spacing:-.02em; }
        .banner, .errors { padding:11px 14px; border-radius:10px; margin-bottom:12px; font-size:14px; font-weight:600; }
        .banner { background:#fff3e6; border:1px solid #ff7918; color:#9a3412; }
        .errors { background:#fde8e8; border:1px solid #e88a8a; color:#991b1b; }
        .stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; }
        @media (max-width:560px) { .stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        .stat { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; }
        .stat b { display:block; font-size:21px; }
        .stat span { font-size:11.5px; color:#6b7280; }
        .mod { border-radius:16px; overflow:hidden; margin-top:14px; background:#fff; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .mod-h { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 14px; font-size:14px; font-weight:800; color:#fff; }
        .mod-h b { min-width:26px; padding:1px 8px; border-radius:999px; background:rgba(255,255,255,.22); text-align:center; font-size:13px; }
        .mod-b { padding:10px; }
        .mod-orange .mod-h { background:#ff7918; color:#21170e; }
        .mod-orange .mod-h b { background:rgba(0,0,0,.14); }
        .mod-blue .mod-h { background:#0a5cff; }
        .mod-dark .mod-h { background:#111; color:#ffb379; }
        .mod-amber .mod-h { background:#a8530f; }
        .mod-gray .mod-h { background:#4b5563; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:9px; }
        .card { display:flex; justify-content:space-between; align-items:center; gap:10px; background:#f7f7f5; border:1px solid #e5e7eb; border-radius:14px; padding:12px 14px; }
        .name { font-size:16px; font-weight:800; }
        .meta { font-size:12.5px; color:#6b7280; }
        .money { font-size:18px; font-weight:800; white-space:nowrap; }
        .empty { grid-column:1 / -1; color:#9ca3af; font-size:14px; padding:4px; margin:0; }
        .note { background:#fff7ef; border:1px solid #ffd2ad; border-left:5px solid #ff7918; border-radius:10px; padding:10px 12px; margin-bottom:9px; }
        .note .meta { color:#7c4a1e; font-weight:600; }
        .note p { margin:3px 0 0; font-size:14.5px; line-height:1.4; white-space:pre-wrap; overflow-wrap:anywhere; }
        .mod-b > .note:last-child { margin-bottom:0; }
        label { display:block; font-size:13px; font-weight:600; color:#374151; margin:0 0 4px; }
        select, textarea { width:100%; border:1px solid #d1d5db; border-radius:9px; background:#fff; color:#1f2937; font:16px -apple-system,'Segoe UI',sans-serif; padding:10px; }
        textarea { min-height:78px; resize:vertical; margin-bottom:9px; display:block; }
        select { margin-bottom:9px; }
        .check { display:flex; align-items:center; gap:8px; font-weight:600; margin-bottom:10px; }
        .check input { width:20px; height:20px; accent-color:#ff7918; }
        .go { width:100%; min-height:50px; border:none; border-radius:11px; background:#ff7918; color:#21170e; font:800 16px -apple-system,'Segoe UI',sans-serif; cursor:pointer; }
        .sent { margin-top:12px; }
        .sent h3 { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; }
        .notify { margin-top:14px; border-radius:14px; overflow:hidden; background:#fff; border:1px solid #e5e7eb; }
        .notify summary { display:flex; align-items:center; justify-content:center; min-height:50px; background:#ff7918; color:#21170e; font-size:16px; font-weight:800; cursor:pointer; list-style:none; }
        .notify summary::-webkit-details-marker { display:none; }
        .toast-box { position:fixed; left:12px; right:12px; top:56px; z-index:50; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
        .toast { max-width:440px; margin:0 auto; width:100%; padding:11px 14px; border-radius:12px; background:#111; color:#fff; font-size:14px; box-shadow:0 6px 20px rgba(0,0,0,.28); }
        .toast b { display:block; color:#ffb379; font-size:12.5px; }
        .push { max-width:1100px; margin:10px auto 0; padding:0 16px; }
        .push button { width:100%; min-height:46px; border:1px solid #d1d5db; border-radius:11px; background:#fff; color:#1f2937; font:700 15px -apple-system,'Segoe UI',sans-serif; cursor:pointer; }
        .push p { margin:0; padding:10px 12px; border-radius:10px; background:#fff3e6; color:#9a3412; font-size:13px; font-weight:600; }
        .link { display:inline-block; margin-top:9px; color:#c2410c; font-size:13px; font-weight:700; text-decoration:none; }
    </style>
</head>
<body>
    @php
        $clp = fn (int $amount) => '$'.number_format($amount, 0, ',', '.');
        $hm = fn ($at) => $at->timezone('America/Santiago')->format('H:i');
        $when = function ($at) use ($hm) {
            $local = $at->copy()->timezone('America/Santiago');

            return ($local->isToday() ? '' : ($local->isTomorrow() ? 'Mañana ' : $local->format('d/m').' ')).$hm($at);
        };
    @endphp
    <div class="top">
        <span>HH Admin</span>
        <div>
            <button type="button" id="sound-test">Sonido</button>
            <a href="{{ route('rooms.board') }}">Sistema</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Salir</button></form>
        </div>
    </div>
    <div class="push" id="push" hidden></div>
    <div class="wrap">
        <h1>Hoy en el motel</h1>

        @if (session('status'))<div class="banner">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif

        <div class="stats">
            <div class="stat"><b>{{ $clp($sales['Hoy']['total']) }}</b><span>venta de hoy</span></div>
            <div class="stat"><b>{{ $occupied->count() }}</b><span>ocupadas</span></div>
            <div class="stat"><b>{{ $upcoming->count() }}</b><span>próximas reservas</span></div>
            <div class="stat"><b>{{ $freeCount }}</b><span>listas</span></div>
        </div>

        <details class="notify" id="notify" @if ($errors->any()) open @endif>
            <summary>Avisar al personal</summary>
            <div class="mod-b">
                <form method="POST" action="{{ route('admin_app.notify') }}">
                    @csrf
                    <label for="audience">Para</label>
                    <select id="audience" name="audience" required>
                        @foreach (\App\Models\StaffNotice::AUDIENCES as $key => $label)
                            <option value="{{ $key }}" @selected(old('audience') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label for="body">Aviso</label>
                    <textarea id="body" name="body" maxlength="500" required placeholder="Ej: revisar el Playroom 205 antes de las 22:00">{{ old('body') }}</textarea>
                    <label class="check"><input type="checkbox" name="urgent" value="1" @checked(old('urgent'))> Urgente (con sonido)</label>
                    <button class="go" type="submit">Enviar aviso</button>
                </form>
                @if ($notices->isNotEmpty())
                    <div class="sent">
                        <h3>Últimos avisos</h3>
                        @foreach ($notices as $notice)
                            @php $delivery = $notice->delivery(); @endphp
                            <div class="note">
                                <div class="meta">@if ($notice->urgent)Urgente · @endif{{ \App\Models\StaffNotice::AUDIENCES[$notice->audience] }} · {{ $notice->created_at->timezone('America/Santiago')->format('d/m H:i') }}</div>
                                <p>{{ $notice->body }}</p>
                                <div class="meta" style="margin-top:5px;">
                                    Leído: {{ $delivery['read']->isEmpty() ? 'nadie aún' : $delivery['read']->join(', ') }}
                                    @if ($delivery['missing']->isNotEmpty())<br>Falta: {{ $delivery['missing']->join(', ') }}@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>

        <section class="mod mod-blue">
            <div class="mod-h"><span>Ocupadas</span><b>{{ $occupied->count() }}</b></div>
            <div class="mod-b grid">
            @forelse ($occupied as $entry)
                <div class="card">
                    <span class="name">{{ $entry['room']->name }}</span>
                    <span class="meta">@if ($entry['status']['occupancy'] === 'ocupada' && $entry['status']['eta_minutes'] !== null && $entry['status']['eta_minutes'] >= 0)Libera en {{ \App\Support\TimeFormat::minutes($entry['status']['eta_minutes']) }}@else Ocupada @endif</span>
                </div>
            @empty
                <p class="empty">Ninguna ocupada ahora.</p>
            @endforelse
            </div>
        </section>

        <section class="mod mod-orange">
            <div class="mod-h"><span>Próximas reservas</span><b>{{ $upcoming->count() }}</b></div>
            <div class="mod-b grid">
            @forelse ($upcoming as $booking)
                <div class="card">
                    <div>
                        <div class="name">{{ $when($booking->starts_at) }} · {{ $booking->room?->name }}</div>
                        <div class="meta">{{ $booking->customer?->name }}</div>
                    </div>
                    <span class="meta">{{ $booking->balanceDue() > 0 ? 'Debe '.$clp((int) $booking->balanceDue()) : 'Pagada' }}</span>
                </div>
            @empty
                <p class="empty">Sin reservas próximas.</p>
            @endforelse
            </div>
        </section>

        <section class="mod mod-dark">
            <div class="mod-h"><span>Ventas</span></div>
            <div class="mod-b">
                <div class="grid">
                @foreach ($sales as $label => $sale)
                    <div class="card">
                        <div>
                            <div class="name">{{ $label }}</div>
                            <div class="meta">{{ $sale['count'] }} {{ $sale['count'] === 1 ? 'estadía' : 'estadías' }}</div>
                        </div>
                        <span class="money">{{ $clp($sale['total']) }}</span>
                    </div>
                @endforeach
                    <div class="card">
                        <div>
                            <div class="name">Cobrado hoy</div>
                            <div class="meta">Pagos recibidos hoy</div>
                        </div>
                        <span class="money">{{ $clp($cashToday) }}</span>
                    </div>
                </div>
                <a class="link" href="{{ route('sales.daily') }}">Ver ventas del día →</a>
            </div>
        </section>

        <section class="mod mod-dark">
            <div class="mod-h"><span>Novedades de hoy</span><b>{{ $notes->count() }}</b></div>
            <div class="mod-b">
            @forelse ($notes as $note)
                <div class="note">
                    <div class="meta">@if ($note->urgent)Urgente · @endif{{ $note->category }}@if ($note->room) · {{ $note->room->name }}@endif · {{ $note->authorName() }} · {{ $hm($note->created_at) }}</div>
                    <p>{{ $note->body }}</p>
                </div>
            @empty
                <p class="empty">Sin novedades por ahora.</p>
            @endforelse
            </div>
        </section>

        <section class="mod mod-amber">
            <div class="mod-h"><span>En aseo</span><b>{{ $aseo->count() }}</b></div>
            <div class="mod-b grid">
            @forelse ($aseo as $entry)
                <div class="card">
                    <span class="name">{{ $entry['room']->name }}</span>
                    <span class="meta">{{ $entry['room']->aseo_reported_at ? 'Falta confirmar' : 'En aseo' }}</span>
                </div>
            @empty
                <p class="empty">Ninguna en aseo.</p>
            @endforelse
            </div>
        </section>

        <section class="mod mod-gray">
            <div class="mod-h"><span>Fuera de servicio</span><b>{{ $out->count() }}</b></div>
            <div class="mod-b grid">
            @forelse ($out as $entry)
                <div class="card">
                    <div>
                        <div class="name">{{ $entry['room']->name }}</div>
                        <div class="meta">{{ \App\Services\Rooms\RoomClosingLog::STATUS_LABELS[$entry['room']->operational_status] }}@if ($entry['room']->operational_note) · {{ $entry['room']->operational_note }}@endif</div>
                    </div>
                </div>
            @empty
                <p class="empty">Ninguna fuera de servicio.</p>
            @endforelse
            </div>
        </section>
    </div>
    <div class="toast-box" id="toasts" aria-live="polite"></div>
    @include('partials.staff-notice-alert')
    <script>
        (function () {
            const pulseUrl = @json(route('admin_app.pulse'));
            let mark = @json($pulse);
            let audio = null;

            // El celular solo deja sonar después de un primer toque en la app.
            const unlock = function () {
                if (!audio) audio = new (window.AudioContext || window.webkitAudioContext)();
                if (audio.state === 'suspended') audio.resume();
            };
            document.addEventListener('pointerdown', unlock);
            document.addEventListener('keydown', unlock);

            let sound = null;
            fetch(@json(asset('sounds/cha-ching.mp3'))).then(function (response) { return response.arrayBuffer(); }).then(function (data) { sound = data; }).catch(function () {});

            let decoded = null;
            const cash = function () {
                if (!audio || audio.state !== 'running' || !sound) return;
                const play = function (buffer) {
                    decoded = buffer;
                    const source = audio.createBufferSource();
                    const volume = audio.createGain();
                    source.buffer = buffer;
                    volume.gain.value = .15;
                    source.connect(volume);
                    volume.connect(audio.destination);
                    source.start();
                };
                if (decoded) { play(decoded); return; }
                audio.decodeAudioData(sound.slice(0), play);
            };

            document.getElementById('sound-test').addEventListener('click', function () {
                unlock();
                setTimeout(cash, 60);
            });

            // Actualiza sin recargar la página: recargar volvería a silenciar el celular.
            const refresh = function () {
                const notify = document.getElementById('notify');
                if ((notify && notify.open) || document.querySelector('.hh-notice')) return;
                fetch(window.location.href, { credentials: 'same-origin' }).then(function (response) {
                    if (response.redirected || !response.ok) { window.location.reload(); return null; }
                    return response.text();
                }).then(function (html) {
                    if (!html) return;
                    const next = new DOMParser().parseFromString(html, 'text/html').querySelector('.wrap');
                    if (next) document.querySelector('.wrap').innerHTML = next.innerHTML;
                }).catch(function () {});
            };

            const toast = function (booking) {
                const box = document.createElement('div');
                const title = document.createElement('b');
                box.className = 'toast';
                title.textContent = 'Nueva reserva · ' + booking.origin;
                box.appendChild(title);
                box.appendChild(document.createTextNode(booking.room + ' · ' + booking.at + ' · $' + booking.amount.toLocaleString('es-CL')));
                document.getElementById('toasts').appendChild(box);
                setTimeout(function () { box.remove(); }, 12000);
            };

            const pulse = function () {
                fetch(pulseUrl + '?desde=' + mark.booking, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (latest) {
                        if (!latest) return;
                        const fresh = latest.booking > mark.booking || latest.payment > mark.payment;
                        mark = latest;
                        (latest.new || []).forEach(toast);
                        if (fresh) { cash(); refresh(); }
                    }).catch(function () {});
            };

            // Notificaciones con el celular bloqueado. En iPhone solo existen con la app instalada.
            const pushKey = @json(config('services.webpush.public_key'));
            const pushBox = document.getElementById('push');
            const message = function (text) {
                const p = document.createElement('p');
                p.textContent = text;
                pushBox.replaceChildren(p);
                pushBox.hidden = false;
            };
            const keyBytes = function (key) {
                const raw = atob((key + '='.repeat((4 - key.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from(raw, function (c) { return c.charCodeAt(0); });
            };
            const save = function (subscription) {
                return fetch(@json(route('admin_app.push')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify(subscription.toJSON()),
                });
            };
            if (pushKey) {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    message('Para recibir notificaciones, instala HH Admin en la pantalla de inicio y ábrela desde su ícono.');
                } else {
                    navigator.serviceWorker.register('/sw-admin.js', { scope: '/panel' }).then(function () {
                        return navigator.serviceWorker.ready;
                    }).then(function (registration) {
                        return registration.pushManager.getSubscription().then(function (current) {
                            if (current) { save(current); return; }
                            if (Notification.permission === 'denied') {
                                message('Las notificaciones están bloqueadas. Actívalas en los ajustes del celular.');
                                return;
                            }
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.textContent = 'Activar notificaciones';
                            button.addEventListener('click', function () {
                                Notification.requestPermission().then(function (answer) {
                                    if (answer !== 'granted') { message('No se activaron las notificaciones.'); return null; }
                                    return registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(pushKey) });
                                }).then(function (subscription) {
                                    if (!subscription) return;
                                    return save(subscription).then(function () {
                                        message('Notificaciones activadas en este celular.');
                                        setTimeout(function () { pushBox.hidden = true; }, 4000);
                                    });
                                }).catch(function () { message('No se pudieron activar. Inténtalo de nuevo.'); });
                            });
                            pushBox.replaceChildren(button);
                            pushBox.hidden = false;
                        });
                    }).catch(function () {});
                }
            }

            setInterval(pulse, 20000);
            setInterval(refresh, 60000);
        })();
    </script>
</body>
</html>
