<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Conversaciones — HH Motel</title>
    <style>
        :root { --bg:#efeeea; --panel:#fff; --soft:#efeeea; --line:#e2e0da; --ink:#141414; --muted:#6f6e69; --accent:#ff7918; --accent-soft:#fff0e4; --blue:#0a5cff; --warn:#8a5a00; --warn-bg:#fff8e6; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:-apple-system, "Segoe UI", sans-serif; }
        button, input, textarea { font-family:inherit; }
        h2, h3 { margin:0; }
        .cv-notice { margin:14px 18px 0; background:var(--warn-bg); color:var(--warn); border-radius:12px; padding:10px 14px; font-size:13.5px; }
        .cv { display:grid; grid-template-columns:290px minmax(0,1fr) 270px; gap:12px; padding:14px 18px 16px; height:calc(100vh - 80px); min-height:460px; }
        .cv-panel { background:var(--panel); border-radius:20px; display:flex; flex-direction:column; min-height:0; min-width:0; overflow:hidden; }
        .cv-filters { padding:18px 16px 12px; display:flex; flex-direction:column; gap:10px; }
        .cv-filters h2 { font-size:22px; font-weight:800; }
        .cv-filters input { width:100%; background:var(--soft); border:1px solid var(--line); border-radius:12px; padding:10px 12px; font-size:14px; color:var(--ink); }
        .cv-chips { display:flex; gap:6px; flex-wrap:wrap; }
        .cv-chip { background:var(--panel); color:#3e3d3a; border:1px solid var(--line); border-radius:20px; padding:7px 12px; font-size:12.5px; cursor:pointer; }
        .cv-chip.is-on { background:var(--soft); border-color:#a9a7a0; color:var(--ink); font-weight:600; }
        .cv-round { width:38px; height:38px; padding:0; display:grid; place-items:center; font-size:11.5px; font-weight:700; }
        .cv-dot { width:24px; height:24px; flex:none; border-radius:50%; display:grid; place-items:center; color:#fff; }
        .cv-dot svg { width:14px; height:14px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
        .cv-dot.whatsapp { background:#12a150; } .cv-dot.instagram { background:#d6246e; } .cv-dot.facebook { background:#1877f2; } .cv-dot.email { background:#6f6e69; } .cv-dot.none { background:#b5b3ac; }
        .cv-list { overflow-y:auto; flex:1; padding:0 10px 10px; }
        .cv-item { display:flex; gap:10px; align-items:flex-start; width:100%; text-align:left; background:none; border:1px solid transparent; border-radius:14px; color:var(--ink); padding:10px; cursor:pointer; }
        .cv-item:hover { background:#f7f6f3; }
        .cv-item.is-on { background:var(--soft); border-color:#cfcdc6; }
        .cv-item-body { min-width:0; flex:1; }
        .cv-item-top { display:flex; justify-content:space-between; gap:8px; font-size:14px; font-weight:700; }
        .cv-item-top span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cv-time { color:var(--muted); font-size:11px; font-weight:400; flex:none; }
        .cv-prev { color:var(--muted); font-size:12.5px; margin-top:3px; display:flex; gap:6px; justify-content:space-between; }
        .cv-prev > span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cv-badge { background:var(--accent); width:9px; height:9px; border-radius:50%; flex:none; align-self:center; }
        .cv-head { padding:16px 20px 12px; border-bottom:1px solid var(--line); }
        .cv-head-title { display:flex; align-items:center; gap:10px; }
        .cv-head-title strong { font-size:20px; font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cv-head-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:10px; }
        .cv-pill { border-radius:20px; padding:6px 11px; font-size:12px; font-weight:600; background:var(--soft); color:#3e3d3a; }
        .cv-pill.open { background:#e4f4ea; color:#0b6b35; }
        .cv-pill.warn { background:var(--warn-bg); color:var(--warn); }
        .cv-pill.human { background:var(--accent-soft); color:#a84a06; }
        .cv-btn { background:var(--panel); color:var(--ink); border:1px solid #cfcdc6; border-radius:12px; padding:8px 13px; font-size:13px; font-weight:600; cursor:pointer; }
        .cv-btn[hidden] { display:none; }
        .cv-actions { display:flex; align-items:center; gap:8px; flex:none; }
        #cv-close { flex:none; white-space:nowrap; }
        #cv-close.is-primary { background:#0a5cff; border-color:#0a5cff; color:#fff; font-size:13.5px; font-weight:800; padding:8px 16px; box-shadow:0 2px 8px rgba(10,92,255,.35); }
        .cv-msgs { flex:1; overflow-y:auto; padding:18px 20px; display:flex; flex-direction:column; gap:10px; }
        .cv-msg { max-width:72%; background:var(--soft); color:var(--ink); border-radius:16px; padding:10px 13px; font-size:14.5px; line-height:1.35; white-space:pre-wrap; overflow-wrap:anywhere; align-self:flex-start; }
        .cv-msg.mine { align-self:flex-end; background:var(--accent); color:#fff; }
        .cv-msg small { display:block; color:var(--muted); font-size:10.5px; margin-top:4px; }
        .cv-msg.mine small { color:#fff; opacity:.85; }
        .cv-msg a { color:var(--blue); display:block; font-size:13px; }
        .cv-msg.mine a { color:#fff; }
        .cv-reply { border-top:1px solid var(--line); padding:12px 16px; display:flex; gap:10px; align-items:stretch; }
        .cv-reply[hidden], .cv-blocked[hidden], .cv-error[hidden] { display:none; }
        .cv-reply textarea { flex:1; resize:none; height:46px; background:var(--soft); border:1px solid var(--line); border-radius:14px; padding:12px 14px; font-size:14.5px; color:var(--ink); }
        .cv-send { background:var(--accent); color:#fff; border:0; border-radius:14px; padding:0 20px; font-size:14px; font-weight:700; cursor:pointer; }
        .cv-send:disabled { opacity:.5; }
        .cv-attach { width:46px; flex:none; background:var(--soft); border:1px solid var(--line); border-radius:14px; font-size:18px; cursor:pointer; }
        .cv-file { display:flex; align-items:center; gap:8px; margin:10px 16px 0; padding:7px 12px; background:var(--soft); border-radius:12px; font-size:13px; }
        .cv-file[hidden] { display:none; }
        .cv-file span { flex:1; min-width:0; overflow-wrap:anywhere; }
        .cv-file button { background:none; border:0; cursor:pointer; color:var(--muted); font-size:14px; }
        /* Con los botones de emoji, dictado y nota de voz: si no cabe, la caja de texto pasa a su propia línea. */
        .cv-reply { position:relative; flex-wrap:wrap; }
        .cv-tools { display:flex; gap:10px; }
        .cv-write { display:flex; gap:10px; flex:1 1 300px; min-width:0; }
        .cv-write textarea { flex:1 1 auto; min-width:0; }
        .cv-attach[hidden] { display:none; }
        .cv-attach.cv-labeled { width:auto; padding:0 12px; font-size:13px; font-weight:700; color:var(--ink); white-space:nowrap; }
        .cv-send { min-height:46px; }
        @media (max-width:600px) { .cv-lbl { display:none; } .cv-attach.cv-labeled { width:38px; padding:0; font-size:16px; } .cv-tools, .cv-write { gap:6px; } }
        .cv-attach.is-on { background:#fee2e2; border-color:#ef4444; color:#b91c1c; font-size:13px; font-weight:800; width:auto; padding:0 12px; }
        .cv-emojis { position:absolute; left:16px; bottom:66px; z-index:6; display:grid; grid-template-columns:repeat(8, 38px); gap:2px; padding:8px; background:var(--panel); border:1px solid var(--line); border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,.15); }
        .cv-emojis[hidden] { display:none; }
        .cv-emojis button { width:38px; height:38px; font-size:21px; background:none; border:0; border-radius:9px; cursor:pointer; }
        .cv-emojis button:hover { background:var(--soft); }
        .cv-file audio { height:32px; max-width:230px; }
        .cv-file audio[hidden] { display:none; }
        @media (max-width:600px) { .cv-attach { width:38px; font-size:16px; } .cv-reply { gap:6px; padding:10px; } .cv-emojis { left:8px; grid-template-columns:repeat(6, 38px); } }
        .cv-msg audio { display:block; width:240px; max-width:100%; margin-top:6px; }
        .cv-blocked { border-top:1px solid var(--line); background:var(--warn-bg); padding:13px 18px; font-size:13.5px; color:var(--warn); }
        .cv-empty { color:var(--muted); font-size:13.5px; padding:26px 14px; text-align:center; }
        .cv-side { padding:18px 16px; overflow-y:auto; font-size:14px; }
        .cv-side h3 { font-size:20px; font-weight:800; margin-bottom:10px; }
        .cv-side dl { margin:0 0 12px; }
        .cv-side dt { color:var(--muted); font-size:12px; margin-top:12px; }
        .cv-side dd { margin:4px 0 0; background:var(--soft); border-radius:12px; padding:9px 12px; overflow-wrap:anywhere; }
        .cv-side dd.cv-stay { background:#fff0e4; color:#a84a06; font-weight:700; }
        .cv-side a { color:#a84a06; font-weight:600; text-decoration:none; }
        .cv-side label { display:block; color:var(--muted); font-size:12px; margin-top:12px; }
        .cv-side input { display:block; width:100%; margin-top:4px; background:var(--soft); border:1px solid transparent; border-radius:12px; padding:9px 12px; font-size:14px; color:var(--ink); }
        .cv-side input:focus { outline:none; border-color:var(--accent); background:#fff; }
        .cv-side .cv-save { width:100%; margin-top:12px; padding:10px; }
        .cv-side .cv-team { width:100%; margin-top:8px; }
        .cv-side .cv-saved { color:var(--muted); font-size:12.5px; margin-top:6px; min-height:16px; }
        .cv-item-tag { font-size:10px; font-weight:700; color:#3e3d3a; background:var(--soft); border-radius:6px; padding:1px 5px; margin-left:5px; }
        .cv-item-tag.is-room { background:#0a5cff; color:#fff; }
        .cv-side .note { color:var(--muted); font-size:12.5px; }
        .cv-error { background:var(--warn-bg); color:var(--warn); font-size:13px; padding:9px 16px; }
        #cv-back { display:none; }
        /* El aviso de atención va arriba aquí, para no tapar la caja de respuesta. */
        #hh-attention-bar { bottom:auto; top:10px; }
        @media (max-width:1040px) { .cv { grid-template-columns:290px minmax(0,1fr); } .cv-info { display:none; } }
        @media (max-width:760px) {
            .cv { grid-template-columns:minmax(0,1fr); padding:10px; }
            .cv.is-open .cv-inbox, .cv:not(.is-open) .cv-chat { display:none; }
            #cv-back { display:inline-block; }
            .cv-msg { max-width:86%; }
        }
    </style>
</head>
<body>

    @include('partials.navbar')

    @unless ($configured)
        <div class="cv-notice">La conexión con GHL no está configurada en este ambiente.</div>
    @endunless

    <div class="cv" id="cv">
        <section class="cv-panel cv-inbox" aria-label="Bandeja">
            <div class="cv-filters">
                <h2>Conversaciones</h2>
                <input id="cv-q" type="search" placeholder="Nombre o teléfono" aria-label="Buscar conversación" maxlength="80">
                <div class="cv-chips" id="cv-channels">
                    <button type="button" class="cv-chip is-on" data-channel="">Todos</button>
                    <button type="button" class="cv-chip cv-round" data-channel="whatsapp" aria-label="WhatsApp" title="WhatsApp"></button>
                    <button type="button" class="cv-chip cv-round" data-channel="instagram" aria-label="Instagram" title="Instagram"></button>
                    <button type="button" class="cv-chip cv-round" data-channel="facebook" aria-label="Facebook" title="Facebook"></button>
                    <button type="button" class="cv-chip cv-round" data-channel="email" aria-label="Correo" title="Correo"></button>
                </div>
                <div class="cv-chips" id="cv-views">
                    <button type="button" class="cv-chip" data-view="por_atender">Por atender</button>
                    <button type="button" class="cv-chip" data-view="esperando">Esperando cliente</button>
                    <button type="button" class="cv-chip is-on" data-view="todas">Todas</button>
                    @if ($seesTeam)
                        <button type="button" class="cv-chip" data-view="equipo">Equipo</button>
                    @endif
                </div>
            </div>
            <div id="cv-list-error" class="cv-error" hidden></div>
            <div class="cv-list" id="cv-list"><div class="cv-empty">Cargando…</div></div>
        </section>

        <section class="cv-panel cv-chat" aria-label="Conversación">
            <div class="cv-head">
                <div class="cv-head-title">
                    <button type="button" class="cv-btn" id="cv-back" aria-label="Volver a la bandeja">←</button>
                    <span id="cv-channel-dot"></span>
                    <strong id="cv-name">Elige una conversación</strong>
                </div>
                <div class="cv-head-row">
                    <span id="cv-state"></span>
                    <div class="cv-actions">
                        <button type="button" class="cv-btn" id="cv-cata" hidden></button>
                        <button type="button" class="cv-btn" id="cv-close" hidden></button>
                    </div>
                </div>
            </div>
            <div id="cv-chat-error" class="cv-error" hidden></div>
            <div class="cv-msgs" id="cv-msgs"></div>
            <div class="cv-blocked" id="cv-blocked" hidden></div>
            <div class="cv-file" id="cv-file-chip" hidden>
                <span id="cv-file-name"></span>
                <audio id="cv-file-audio" controls preload="metadata" hidden></audio>
                <button type="button" id="cv-file-clear" aria-label="Quitar archivo">✕</button>
            </div>
            <form class="cv-reply" id="cv-form" hidden>
                <input type="file" id="cv-file" hidden accept=".jpg,.jpeg,.png,.mp4,.mp3,.m4a,.aac,.amr,.ogg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                <div class="cv-tools">
                <button class="cv-attach" id="cv-attach" type="button" aria-label="Adjuntar archivo" title="Imagen, documento, audio o video · máximo 5 MB">📎</button>
                <button class="cv-attach" id="cv-emoji" type="button" aria-label="Emojis" aria-expanded="false" title="Emojis">😊</button>
                <button class="cv-attach cv-labeled" id="cv-dictate" type="button" aria-label="Dictar: hablas y se escribe como texto" title="Dictar: hablas y se escribe como texto" hidden>🗣️<span class="cv-lbl"> Dictar</span></button>
                </div>
                <div class="cv-emojis" id="cv-emojis" role="menu" hidden></div>
                <div class="cv-write">
                <textarea id="cv-text" placeholder="Escribe tu respuesta…" maxlength="2000" aria-label="Respuesta"></textarea>
                <button class="cv-attach cv-labeled" id="cv-record" type="button" aria-label="Grabar nota de voz" title="Grabar nota de voz y enviarla como audio (máx. 2 minutos)" hidden>🎙️<span class="cv-lbl"> Audio</span></button>
                <button class="cv-send" id="cv-send" type="submit">Enviar</button>
                </div>
            </form>
        </section>

        <aside class="cv-panel cv-info" aria-label="Ficha">
            <div class="cv-side" id="cv-side"><div class="cv-empty">Sin conversación abierta</div></div>
        </aside>
    </div>

    <script>
    (() => {
        const urls = {
            list: @json(route('admin.conversations.list')),
            base: @json(url('/admin/conversaciones')),
        };
        const csrf = @json(csrf_token());
        const seesTeam = @json($seesTeam);
        const labels = { whatsapp: 'WhatsApp', instagram: 'Instagram', facebook: 'Facebook', email: 'Correo' };
        const icons = {
            whatsapp: '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>',
            instagram: '<rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="3.5"/>',
            facebook: '<path d="M14 21v-8h3l.5-3.5H14V7.5c0-1 .4-1.7 1.8-1.7H18V3h-2.8C12.4 3 10.8 4.7 10.8 7.3v2.2H8V13h2.8v8"/>',
            email: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            none: '<circle cx="12" cy="12" r="3"/>',
        };
        const $ = id => document.getElementById(id);
        const dot = channel => {
            const node = document.createElement('span');
            node.className = 'cv-dot ' + (channel || 'none');
            node.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true">${icons[channel] || icons.none}</svg>`;
            return node;
        };
        document.querySelectorAll('.cv-round').forEach(b => b.append(dot(b.dataset.channel)));
        const state = { view: 'todas', channel: '', q: '', open: null, contact: '', next: null, seen: '', sending: false };
        // Lo ya visto se muestra al instante y se actualiza por detrás.
        const threads = new Map();
        const loading = new Map();

        const el = (tag, cls, text) => {
            const node = document.createElement(tag);
            if (cls) node.className = cls;
            if (text !== undefined) node.textContent = text;
            return node;
        };

        async function api(url, options = {}) {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, ...options });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.error || data.message || 'No se pudo completar. Intenta de nuevo.');
            return data;
        }

        function showError(id, message) {
            $(id).hidden = !message;
            $(id).textContent = message || '';
        }

        function renderItem(c) {
            const item = el('button', 'cv-item' + (c.id === state.open ? ' is-on' : ''));
            item.type = 'button';
            item.dataset.id = c.id;
            const body = el('div', 'cv-item-body');
            const top = el('div', 'cv-item-top');
            const title = el('span', '', c.name);
            if (c.team) title.append(el('span', 'cv-item-tag', 'Equipo'));
            if (c.closed) title.append(el('span', 'cv-item-tag', 'De alta'));
            if (c.in_room) title.append(el('span', 'cv-item-tag is-room', `En ${c.in_room}`));
            top.append(title, el('span', 'cv-time', c.when));
            const prev = el('div', 'cv-prev');
            prev.append(el('span', '', c.preview));
            if (c.pending) {
                const mark = el('span', 'cv-badge');
                mark.title = 'Por atender';
                prev.append(mark);
            }
            body.append(top, prev);
            item.append(dot(c.channel), body);
            item.addEventListener('click', () => openConversation(c));
            item.addEventListener('pointerenter', () => { if (!threads.has(c.id)) fetchThread(c.id, c.contact_id).catch(() => {}); });
            return item;
        }

        async function loadList(more = false) {
            const params = new URLSearchParams({ vista: state.view });
            if (state.channel) params.set('canal', state.channel);
            if (state.q) params.set('q', state.q);
            if (more && state.next) params.set('antes', state.next);
            try {
                const data = await api(urls.list + '?' + params);
                showError('cv-list-error', '');
                const list = $('cv-list');
                const top = list.scrollTop;
                if (!more) list.replaceChildren();
                list.querySelector('.cv-more')?.remove();
                data.items.forEach(c => list.append(renderItem(c)));
                if (!list.querySelector('.cv-item')) list.replaceChildren(el('div', 'cv-empty', 'No hay conversaciones con ese filtro.'));
                state.next = data.next;
                if (data.next) {
                    const button = el('button', 'cv-chip cv-more', 'Cargar más');
                    button.type = 'button';
                    button.style.margin = '8px auto';
                    button.style.display = 'block';
                    button.addEventListener('click', () => loadList(true));
                    list.append(button);
                }
                if (!more) list.scrollTop = top;
            } catch (error) {
                showError('cv-list-error', error.message);
            }
        }

        function renderThread(data) {
            const box = $('cv-msgs');
            const stick = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
            box.replaceChildren(...data.messages.map(m => {
                const msg = el('div', 'cv-msg' + (m.mine ? ' mine' : ''), m.body);
                m.attachments.forEach(url => {
                    if (/\.(ogg|oga|opus|mp3|m4a|aac|wav)(\?|$)/i.test(url)) {
                        const audio = el('audio');
                        audio.controls = true;
                        audio.preload = 'none';
                        audio.src = url;
                        msg.append(audio);
                    }
                    const link = el('a', '', 'Ver adjunto');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    msg.append(link);
                });
                msg.append(el('small', '', m.source === 'workflow' ? `${m.when} · Mensaje automático · si lleva botón, solo se ve en WhatsApp` : m.when));
                return msg;
            }));
            if (!data.messages.length) box.append(el('div', 'cv-empty', 'Sin mensajes para mostrar.'));
            if (stick) box.scrollTop = box.scrollHeight;

            const pills = [];
            if (data.window_left_minutes !== null) {
                const left = data.window_left_minutes;
                pills.push(left > 0
                    ? el('span', 'cv-pill open', `Ventana abierta · quedan ${left >= 60 ? Math.floor(left / 60) + ' h' : left + ' min'}`)
                    : el('span', 'cv-pill warn', 'Ventana cerrada'));
            }
            pills.push(data.cata.active ? el('span', 'cv-pill', 'Cata activa') : el('span', 'cv-pill human', data.cata.reason));
            $('cv-state').replaceChildren(...pills);
            $('cv-state').style.display = 'contents';
            $('cv-channel-dot').replaceChildren(dot(data.channel));

            const cata = $('cv-cata');
            const noBot = data.contact?.tags.includes('hh_no_bot');
            cata.hidden = !data.contact;
            cata.textContent = noBot ? 'Prender Cata' : 'Apagar Cata';
            cata.dataset.on = noBot ? '1' : '0';

            const close = $('cv-close');
            close.hidden = false;
            close.textContent = data.closed ? 'Reabrir' : 'Dar de alta';
            close.dataset.on = data.closed ? '0' : '1';
            close.classList.toggle('is-primary', !data.closed);

            $('cv-form').hidden = !data.can_reply;
            $('cv-blocked').hidden = data.can_reply;
            $('cv-blocked').textContent = data.reply_blocked || '';
            renderSide(data);
        }

        function renderSide(data) {
            const side = $('cv-side');
            // Mientras se edita la ficha, la actualización automática no la pisa.
            if (side.contains(document.activeElement) && document.activeElement.tagName === 'INPUT') return;
            const list = el('dl');
            const row = (label, value) => { if (value) list.append(el('dt', '', label), el('dd', '', value)); };
            row('Teléfono', data.contact?.phone);
            const form = el('form');
            const input = (label, name, value, type = 'text') => {
                const wrap = el('label', '', label);
                const field = el('input');
                field.name = name;
                field.type = type;
                field.value = value || '';
                field.maxLength = name === 'email' ? 120 : 80;
                wrap.append(field);
                form.append(wrap);
            };
            if (data.contact) {
                input('Nombre', 'first_name', data.contact.first_name);
                input('Apellido', 'last_name', data.contact.last_name);
                input('Correo', 'email', data.contact.email, 'email');
                const save = el('button', 'cv-send cv-save', 'Guardar');
                save.type = 'submit';
                const saved = el('div', 'cv-saved');
                form.append(save, saved);
                form.addEventListener('submit', event => saveContact(event, form, save, saved));
            }
            const content = [el('h3', '', 'Ficha'), form, list];
            if (data.contact && seesTeam) {
                const team = el('button', 'cv-btn cv-team', data.team ? 'Quitar de Equipo' : 'Marcar como Equipo');
                team.type = 'button';
                team.addEventListener('click', () => toggleTeam(team, !data.team));
                content.push(team);
            }
            if (data.customer) {
                if (data.customer.current) {
                    list.prepend(el('dd', 'cv-stay', `${data.customer.current.state} · ${data.customer.current.date}`));
                    list.prepend(el('dt', '', 'Estado actual'));
                }
                row('Reservas en HH', String(data.customer.bookings));
                if (data.customer.recent.length) {
                    list.append(el('dt', '', 'Últimas reservas'));
                    data.customer.recent.forEach(booking => {
                        const dd = el('dd');
                        const link = el('a', '', booking.code);
                        link.href = booking.url;
                        dd.append(link, ` · ${booking.date} · ${booking.state}`);
                        list.append(dd);
                    });
                }
                if (data.customer.url) {
                    const profile = el('a', '', 'Ver cliente en HH');
                    profile.href = data.customer.url;
                    content.push(profile);
                }
            } else {
                content.push(el('div', 'note', 'Sin ficha de cliente en HH con este teléfono.'));
            }
            side.replaceChildren(...content);
        }

        async function saveContact(event, form, button, saved) {
            event.preventDefault();
            const id = state.open;
            button.disabled = true;
            saved.textContent = '';
            try {
                await api(`${urls.base}/${id}/ficha`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
                saved.textContent = '✓ Guardado en GHL';
                document.activeElement?.blur();
                threads.delete(id);
                refreshThread();
                loadList();
            } catch (error) {
                saved.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        }

        async function toggleTeam(button, on) {
            const id = state.open;
            button.disabled = true;
            try {
                await api(`${urls.base}/${id}/equipo`, { method: 'POST', body: JSON.stringify({ on }) });
                await refreshThread();
                loadList();
            } catch (error) {
                showError('cv-chat-error', error.message);
                button.disabled = false;
            }
        }

        function fetchThread(id, contact) {
            if (loading.has(id)) return loading.get(id);
            const query = contact ? '?contacto=' + encodeURIComponent(contact) : '';
            const request = api(`${urls.base}/${id}${query}`)
                .then(data => { threads.set(id, data); return data; })
                .finally(() => loading.delete(id));
            loading.set(id, request);
            return request;
        }

        function show(data, scroll) {
            const signature = JSON.stringify(data);
            if (signature !== state.seen) {
                state.seen = signature;
                renderThread(data);
            }
            if (scroll) $('cv-msgs').scrollTop = $('cv-msgs').scrollHeight;
        }

        async function refreshThread(scroll = false) {
            const id = state.open;
            if (!id) return;
            try {
                const data = await fetchThread(id, state.contact);
                if (id !== state.open) return;
                showError('cv-chat-error', '');
                show(data, scroll);
            } catch (error) {
                if (id === state.open) showError('cv-chat-error', error.message);
            }
        }

        function openConversation(c) {
            state.open = c.id;
            state.contact = c.contact_id;
            state.seen = '';
            showError('cv-chat-error', '');
            $('cv-file').value = '';
            $('cv-file-chip').hidden = true;
            $('cv').classList.add('is-open');
            $('cv-name').textContent = c.name;
            $('cv-state').replaceChildren();
            $('cv-channel-dot').replaceChildren(dot(c.channel));
            document.querySelectorAll('.cv-item').forEach(i => i.classList.toggle('is-on', i.dataset.id === c.id));
            if (threads.has(c.id)) {
                show(threads.get(c.id), true);
            } else {
                $('cv-msgs').replaceChildren(el('div', 'cv-empty', 'Cargando…'));
                $('cv-form').hidden = true;
                $('cv-blocked').hidden = true;
                $('cv-cata').hidden = true;
                $('cv-close').hidden = true;
            }
            refreshThread(true);
        }

        function pick(container, button, apply) {
            container.querySelectorAll('.cv-chip').forEach(b => b.classList.toggle('is-on', b === button));
            apply();
            state.next = null;
            $('cv-list').scrollTop = 0;
            loadList();
        }

        $('cv-views').addEventListener('click', e => {
            const b = e.target.closest('[data-view]');
            if (b) pick($('cv-views'), b, () => { state.view = b.dataset.view; });
        });
        $('cv-channels').addEventListener('click', e => {
            const b = e.target.closest('[data-channel]');
            if (b) pick($('cv-channels'), b, () => { state.channel = b.dataset.channel; });
        });

        let typing;
        $('cv-q').addEventListener('input', e => {
            clearTimeout(typing);
            typing = setTimeout(() => { state.q = e.target.value.trim(); state.next = null; loadList(); }, 350);
        });

        $('cv-back').addEventListener('click', () => { state.open = null; $('cv').classList.remove('is-open'); });

        $('cv-text').addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('cv-form').requestSubmit(); }
        });

        const clearFile = () => {
            $('cv-file').value = '';
            $('cv-file-chip').hidden = true;
            const audio = $('cv-file-audio');
            if (audio.src) URL.revokeObjectURL(audio.src);
            audio.removeAttribute('src');
            audio.hidden = true;
        };
        $('cv-attach').addEventListener('click', () => $('cv-file').click());
        $('cv-file-clear').addEventListener('click', clearFile);
        $('cv-file').addEventListener('change', () => {
            const file = $('cv-file').files[0];
            if (!file) return clearFile();
            if (file.size > 5 * 1024 * 1024) {
                clearFile();
                return showError('cv-chat-error', 'El archivo pesa más de 5 MB.');
            }
            showError('cv-chat-error', '');
            $('cv-file-name').textContent = file.name;
            $('cv-file-chip').hidden = false;
        });

        // ===== Emojis, dictado y notas de voz =====
        const insertText = (text) => {
            const box = $('cv-text');
            const start = box.selectionStart ?? box.value.length;
            const end = box.selectionEnd ?? box.value.length;
            box.value = (box.value.slice(0, start) + text + box.value.slice(end)).slice(0, 2000);
            const at = Math.min(start + text.length, box.value.length);
            box.focus();
            box.setSelectionRange(at, at);
        };

        const EMOJIS = ['😊','😉','😍','🥰','😘','😂','🤭','😏','🔥','❤️','💕','✨','🙌','👏','👍','🙏','👋','🥂','🍾','🎉','🎁','💋','😈','🛁','🛏️','🌙','📍','🕐','📅','💳','✅','❌','⚠️','📸','🎶','😅'];
        const emojiBox = $('cv-emojis');
        emojiBox.innerHTML = EMOJIS.map(e => `<button type="button" role="menuitem">${e}</button>`).join('');
        const toggleEmojis = (open) => {
            emojiBox.hidden = !open;
            $('cv-emoji').setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        $('cv-emoji').addEventListener('click', () => toggleEmojis(emojiBox.hidden));
        emojiBox.addEventListener('click', e => {
            const b = e.target.closest('button');
            if (b) insertText(b.textContent);
        });
        document.addEventListener('click', e => {
            if (!emojiBox.hidden && !e.target.closest('#cv-emojis, #cv-emoji')) toggleEmojis(false);
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') toggleEmojis(false); });

        // Dictado: el navegador transcribe y el texto queda en la caja para revisarlo antes de enviar.
        const Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
        let dictation = null;
        if (Speech) {
            const button = $('cv-dictate');
            button.hidden = false;
            const stopDictation = () => {
                if (!dictation) return;
                const current = dictation;
                dictation = null;
                current.stop();
                button.classList.remove('is-on');
                button.innerHTML = '🗣️<span class="cv-lbl"> Dictar</span>';
            };
            button.addEventListener('click', () => {
                if (dictation) return stopDictation();
                const rec = new Speech();
                rec.lang = 'es-CL';
                rec.continuous = true;
                rec.interimResults = false;
                rec.onresult = (event) => {
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        if (!event.results[i].isFinal) continue;
                        const said = event.results[i][0].transcript.trim();
                        if (!said) continue;
                        const box = $('cv-text');
                        const before = box.value.slice(0, box.selectionStart ?? box.value.length);
                        insertText((before && !/\s$/.test(before) ? ' ' : '') + said);
                    }
                };
                rec.onerror = (event) => {
                    if (event.error === 'not-allowed' || event.error === 'service-not-allowed') showError('cv-chat-error', 'El navegador no dio permiso para usar el micrófono.');
                    stopDictation();
                };
                rec.onend = () => { if (dictation === rec) { try { rec.start(); } catch (e) { stopDictation(); } } };
                dictation = rec;
                button.classList.add('is-on');
                button.textContent = '⏹ Dictando';
                showError('cv-chat-error', '');
                try { rec.start(); } catch (e) { stopDictation(); }
            });
        }

        // Nota de voz: se graba, se escucha y se envía como audio. Chrome graba webm y el
        // servidor lo pasa a ogg (el formato de notas de voz de WhatsApp); Safari graba m4a.
        const VOICE_TYPES = [['audio/ogg;codecs=opus', 'ogg'], ['audio/mp4', 'm4a'], ['audio/webm;codecs=opus', 'webm'], ['audio/webm', 'webm']];
        const voiceType = window.MediaRecorder && navigator.mediaDevices?.getUserMedia
            ? VOICE_TYPES.find(([type]) => MediaRecorder.isTypeSupported(type)) : null;
        if (voiceType) {
            const button = $('cv-record');
            button.hidden = false;
            let recorder = null, chunks = [], started = 0, timer = null;
            const clock = () => {
                const s = Math.floor((Date.now() - started) / 1000);
                button.textContent = `⏹ ${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
                if (s >= 120) recorder?.stop();
            };
            button.addEventListener('click', async () => {
                if (recorder) return recorder.stop();
                let stream;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                } catch (e) {
                    return showError('cv-chat-error', 'El navegador no dio permiso para usar el micrófono.');
                }
                showError('cv-chat-error', '');
                clearFile();
                chunks = [];
                recorder = new MediaRecorder(stream, { mimeType: voiceType[0] });
                recorder.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
                recorder.onstop = () => {
                    clearInterval(timer);
                    stream.getTracks().forEach(t => t.stop());
                    recorder = null;
                    button.classList.remove('is-on');
                    button.innerHTML = '🎙️<span class="cv-lbl"> Audio</span>';
                    const seconds = Math.round((Date.now() - started) / 1000);
                    if (!chunks.length || seconds < 1) return;
                    const blob = new Blob(chunks, { type: voiceType[0].split(';')[0] });
                    if (blob.size > 5 * 1024 * 1024) return showError('cv-chat-error', 'La nota de voz quedó muy larga (más de 5 MB).');
                    const file = new File([blob], `nota-de-voz.${voiceType[1]}`, { type: blob.type });
                    const list = new DataTransfer();
                    list.items.add(file);
                    $('cv-file').files = list.files;
                    $('cv-file-name').textContent = `🎙️ Nota de voz · ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
                    const audio = $('cv-file-audio');
                    audio.src = URL.createObjectURL(blob);
                    audio.hidden = false;
                    $('cv-file-chip').hidden = false;
                };
                recorder.start();
                started = Date.now();
                button.classList.add('is-on');
                clock();
                timer = setInterval(clock, 500);
            });
        }

        $('cv-form').addEventListener('submit', async e => {
            e.preventDefault();
            const message = $('cv-text').value.trim();
            const file = $('cv-file').files[0];
            if ((!message && !file) || !state.open || state.sending) return;
            state.sending = true;
            $('cv-send').disabled = true;
            try {
                const body = new FormData();
                body.append('message', message);
                if (file) body.append('file', file);
                const response = await fetch(`${urls.base}/${state.open}/responder`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.error || data.message || 'No se pudo enviar. Intenta de nuevo.');
                $('cv-text').value = '';
                clearFile();
                showError('cv-chat-error', '');
                await refreshThread(true);
                loadList();
            } catch (error) {
                showError('cv-chat-error', error.message);
            } finally {
                state.sending = false;
                $('cv-send').disabled = false;
            }
        });

        $('cv-close').addEventListener('click', async e => {
            const button = e.currentTarget;
            button.disabled = true;
            try {
                await api(`${urls.base}/${state.open}/alta`, { method: 'POST', body: JSON.stringify({ on: button.dataset.on === '1' }) });
                await refreshThread();
                loadList();
            } catch (error) {
                showError('cv-chat-error', error.message);
            } finally {
                button.disabled = false;
            }
        });

        $('cv-cata').addEventListener('click', async e => {
            const button = e.currentTarget;
            button.disabled = true;
            try {
                await api(`${urls.base}/${state.open}/cata`, { method: 'POST', body: JSON.stringify({ on: button.dataset.on === '1' }) });
                await refreshThread();
            } catch (error) {
                showError('cv-chat-error', error.message);
            } finally {
                button.disabled = false;
            }
        });

        // La bandeja llena lo que queda bajo la barra del sistema, sin mover la página.
        const fit = () => {
            const height = window.innerHeight - $('cv').getBoundingClientRect().top - window.scrollY;
            $('cv').style.height = Math.max(460, height) + 'px';
        };
        fit();
        window.addEventListener('resize', fit);

        async function openNotice(id, param = 'aviso') {
            try {
                const data = await api(urls.list + '?' + param + '=' + encodeURIComponent(id));
                if (data.items.length) {
                    openConversation(data.items[0]);
                } else {
                    showError('cv-chat-error', 'No encontré una conversación con ese cliente en GHL.');
                }
            } catch (error) {
                showError('cv-chat-error', error.message);
            }
        }

        async function openPending(id, view) {
            const chip = document.querySelector(`#cv-views [data-view="${view}"]`);
            if (chip) pick($('cv-views'), chip, () => { state.view = view; });
            try {
                const data = await api(urls.list + '?vista=' + view);
                const item = data.items.find(i => i.id === id);
                if (item) openConversation(item);
            } catch (error) {
                showError('cv-chat-error', error.message);
            }
        }

        const pendingOpen = @json($pendingOpen);
        if (pendingOpen) {
            openPending(pendingOpen, @json($pendingTeam) ? 'equipo' : 'por_atender');
        } else {
            loadList();
        }
        const notice = @json($notice);
        if (notice) openNotice(notice);
        const pendingBooking = @json($pendingBooking);
        if (pendingBooking) openNotice(pendingBooking, 'reserva');
        setInterval(() => {
            if (document.hidden) return;
            refreshThread();
            if (!state.next || $('cv-list').scrollTop === 0) loadList();
        }, 15000);
    })();
    </script>
</body>
</html>
