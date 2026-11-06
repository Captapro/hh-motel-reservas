@extends('admin.layout')
@section('title', 'Conversaciones')
@section('content')
    <style>
        .cv { --cv-line:#e5e7eb; --cv-muted:#6b7280; --cv-soft:#f3f4f6; color:#111; display:grid; grid-template-columns:300px minmax(0,1fr) 250px; gap:12px; height:calc(100vh - 290px); min-height:460px; }
        .cv-panel { background:#fff; border:1px solid var(--cv-line); border-radius:12px; display:flex; flex-direction:column; min-height:0; min-width:0; overflow:hidden; }
        .cv-filters { padding:10px; border-bottom:1px solid var(--cv-line); display:flex; flex-direction:column; gap:8px; }
        .cv-filters input, .cv-reply textarea { background:#fff; color:#111; border:1px solid #d1d5db; }
        .cv-chips { display:flex; gap:6px; flex-wrap:wrap; }
        button.cv-chip { width:auto; background:var(--cv-soft); color:#374151; border:1px solid transparent; border-radius:20px; padding:4px 10px; font-size:12px; font-weight:500; cursor:pointer; }
        button.cv-chip.is-on { background:#111; color:#fff; }
        .cv-list { overflow-y:auto; flex:1; }
        button.cv-item { display:block; width:100%; text-align:left; background:#fff; border:0; border-bottom:1px solid var(--cv-line); border-radius:0; color:#111; padding:10px 12px; cursor:pointer; font-family:inherit; font-weight:400; }
        button.cv-item:hover { background:var(--cv-soft); }
        button.cv-item.is-on { background:#fff3e8; box-shadow:inset 3px 0 0 #ff7918; }
        .cv-item-top { display:flex; justify-content:space-between; gap:8px; font-size:13.5px; font-weight:600; }
        .cv-item-top span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cv-time { color:var(--cv-muted); font-size:11px; font-weight:400; flex:none; }
        .cv-prev { color:var(--cv-muted); font-size:12.5px; margin-top:3px; display:flex; gap:6px; justify-content:space-between; }
        .cv-prev > span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cv-badge { background:#0a5cff; color:#fff; border-radius:10px; font-size:10.5px; padding:1px 6px; flex:none; }
        .cv-tag { font-size:10.5px; color:#374151; background:var(--cv-soft); border-radius:5px; padding:1px 5px; margin-right:4px; }
        .cv-head { padding:10px 14px; border-bottom:1px solid var(--cv-line); display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
        .cv-head strong { font-size:15px; color:#111; }
        .cv-state { font-size:12px; color:var(--cv-muted); }
        .cv-state.warn { color:#8a5a00; }
        .cv-msgs { flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:8px; background:#fafafa; }
        .cv-msg { max-width:75%; background:#fff; border:1px solid var(--cv-line); color:#111; border-radius:10px; padding:8px 11px; font-size:14px; white-space:pre-wrap; overflow-wrap:anywhere; align-self:flex-start; }
        .cv-msg.mine { align-self:flex-end; background:#0a5cff; border-color:#0a5cff; color:#fff; }
        .cv-msg small { display:block; color:var(--cv-muted); font-size:10.5px; margin-top:3px; }
        .cv-msg.mine small { color:#dbe6ff; }
        .cv-msg a { color:#0a5cff; display:block; font-size:12.5px; }
        .cv-msg.mine a { color:#fff; }
        .cv-reply { border-top:1px solid var(--cv-line); padding:10px; display:flex; gap:8px; align-items:flex-end; }
        .cv-reply[hidden], .cv-blocked[hidden], .cv-error[hidden] { display:none; }
        .cv-reply textarea { resize:none; height:44px; }
        .cv-reply button { width:auto; flex:none; }
        .cv-blocked { border-top:1px solid var(--cv-line); background:#fff8e6; padding:12px 14px; font-size:13px; color:#8a5a00; }
        .cv-empty { color:var(--cv-muted); font-size:13.5px; padding:24px 14px; text-align:center; }
        .cv-side { padding:14px; overflow-y:auto; font-size:13.5px; color:#111; }
        .cv-side h3 { color:#111; }
        .cv-side dt { color:var(--cv-muted); font-size:11px; margin-top:10px; }
        .cv-side dd { margin:2px 0 0; overflow-wrap:anywhere; }
        .cv-error { background:#fff8e6; color:#8a5a00; font-size:13px; padding:8px 12px; }
        button#cv-back { display:none; width:auto; padding:4px 10px; }
        button#cv-cata { width:auto; flex:none; }
        button#cv-cata[hidden] { display:none; }
        @media (max-width:1250px) { .cv { grid-template-columns:280px minmax(0,1fr); } .cv-info { display:none; } }
        @media (max-width:760px) {
            .cv { grid-template-columns:minmax(0,1fr); height:calc(100vh - 150px); }
            .cv.is-open .cv-inbox, .cv:not(.is-open) .cv-chat { display:none; }
            button#cv-back { display:inline-block; }
        }
    </style>

    <div class="page-head">
        <div>
            <h1>Conversaciones</h1>
            <p class="sub">En vivo desde GHL. Abrir una conversación aquí no la marca como leída.</p>
        </div>
    </div>

    @unless ($configured)
        <div class="errors">La conexión con GHL no está configurada en este ambiente.</div>
    @endunless

    <div class="cv" id="cv">
        <section class="cv-panel cv-inbox" aria-label="Bandeja">
            <div class="cv-filters">
                <input id="cv-q" type="search" placeholder="Nombre o teléfono" aria-label="Buscar conversación" maxlength="80">
                <div class="cv-chips" id="cv-views">
                    <button type="button" class="cv-chip is-on" data-view="todas">Todas</button>
                    <button type="button" class="cv-chip" data-view="por_atender">Por atender</button>
                    <button type="button" class="cv-chip" data-view="esperando">Esperando cliente</button>
                </div>
                <div class="cv-chips" id="cv-channels">
                    <button type="button" class="cv-chip is-on" data-channel="">Todos</button>
                    <button type="button" class="cv-chip" data-channel="whatsapp">WhatsApp</button>
                    <button type="button" class="cv-chip" data-channel="instagram">Instagram</button>
                    <button type="button" class="cv-chip" data-channel="facebook">Facebook</button>
                    <button type="button" class="cv-chip" data-channel="email">Correo</button>
                </div>
            </div>
            <div id="cv-list-error" class="cv-error" hidden></div>
            <div class="cv-list" id="cv-list"><div class="cv-empty">Cargando…</div></div>
        </section>

        <section class="cv-panel cv-chat" aria-label="Conversación">
            <div class="cv-head">
                <div>
                    <button type="button" class="btn secondary" id="cv-back">←</button>
                    <strong id="cv-name">Elige una conversación</strong>
                    <div class="cv-state" id="cv-state"></div>
                </div>
                <button type="button" class="btn secondary" id="cv-cata" hidden></button>
            </div>
            <div id="cv-chat-error" class="cv-error" hidden></div>
            <div class="cv-msgs" id="cv-msgs"></div>
            <div class="cv-blocked" id="cv-blocked" hidden></div>
            <form class="cv-reply" id="cv-form" hidden>
                <textarea id="cv-text" placeholder="Escribe tu respuesta…" maxlength="2000" aria-label="Respuesta"></textarea>
                <button class="btn" id="cv-send" type="submit">Enviar</button>
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
        const labels = { whatsapp: 'WhatsApp', instagram: 'Instagram', facebook: 'Facebook', email: 'Correo' };
        const $ = id => document.getElementById(id);
        const state = { view: 'todas', channel: '', q: '', open: null, next: null, seen: '', sending: false };

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
            const top = el('div', 'cv-item-top');
            top.append(el('span', '', c.name), el('span', 'cv-time', c.when));
            const prev = el('div', 'cv-prev');
            const text = el('span');
            if (c.channel) text.append(el('span', 'cv-tag', labels[c.channel]));
            text.append(c.preview);
            prev.append(text);
            if (c.unread > 0) prev.append(el('span', 'cv-badge', c.unread));
            item.append(top, prev);
            item.addEventListener('click', () => openConversation(c));
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
                    button.style.margin = '10px';
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
                    const link = el('a', '', 'Ver adjunto');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    msg.append(link);
                });
                msg.append(el('small', '', m.when));
                return msg;
            }));
            if (!data.messages.length) box.append(el('div', 'cv-empty', 'Sin mensajes para mostrar.'));
            if (stick) box.scrollTop = box.scrollHeight;

            const bits = [];
            if (data.channel) bits.push(labels[data.channel]);
            if (data.window_left_minutes !== null) {
                const left = data.window_left_minutes;
                bits.push(left > 0 ? `Ventana abierta · quedan ${left >= 60 ? Math.floor(left / 60) + ' h' : left + ' min'}` : 'Ventana cerrada');
            }
            bits.push(data.cata.active ? 'Cata activa' : 'Cata: ' + data.cata.reason.toLowerCase());
            $('cv-state').textContent = bits.join(' · ');
            $('cv-state').classList.toggle('warn', !data.can_reply);

            const cata = $('cv-cata');
            const noBot = data.contact?.tags.includes('hh_no_bot');
            cata.hidden = !data.contact;
            cata.textContent = noBot ? 'Prender Cata' : 'Apagar Cata';
            cata.dataset.on = noBot ? '1' : '0';

            $('cv-form').hidden = !data.can_reply;
            $('cv-blocked').hidden = data.can_reply;
            $('cv-blocked').textContent = data.reply_blocked || '';
            renderSide(data);
        }

        function renderSide(data) {
            const side = $('cv-side');
            const list = el('dl');
            const row = (label, value) => { if (value) list.append(el('dt', '', label), el('dd', '', value)); };
            row('Nombre', data.contact?.name);
            row('Teléfono', data.contact?.phone);
            row('Correo', data.contact?.email);
            const content = [el('h3', '', 'Ficha'), list];
            if (data.customer) {
                row('Reservas en HH', String(data.customer.bookings));
                const profile = el('a', 'link', 'Ver cliente en HH');
                profile.href = data.customer.url;
                content.push(profile);
                if (data.customer.last_booking) {
                    const last = data.customer.last_booking;
                    list.append(el('dt', '', 'Última reserva'));
                    const dd = el('dd');
                    const link = el('a', 'link', last.code);
                    link.href = last.url;
                    dd.append(link, ` · ${last.date} · ${last.status}`);
                    list.append(dd);
                }
            } else {
                content.push(el('div', 'cv-state', 'Sin ficha de cliente en HH con este teléfono.'));
            }
            side.replaceChildren(...content);
        }

        async function refreshThread(scroll = false) {
            const id = state.open;
            if (!id) return;
            try {
                const data = await api(`${urls.base}/${id}`);
                if (id !== state.open) return;
                showError('cv-chat-error', '');
                const signature = JSON.stringify(data);
                if (signature !== state.seen) {
                    state.seen = signature;
                    renderThread(data);
                }
                if (scroll) $('cv-msgs').scrollTop = $('cv-msgs').scrollHeight;
            } catch (error) {
                if (id === state.open) showError('cv-chat-error', error.message);
            }
        }

        function openConversation(c) {
            state.open = c.id;
            state.seen = '';
            $('cv').classList.add('is-open');
            $('cv-name').textContent = c.name;
            $('cv-state').textContent = '';
            $('cv-msgs').replaceChildren(el('div', 'cv-empty', 'Cargando…'));
            $('cv-form').hidden = true;
            $('cv-blocked').hidden = true;
            $('cv-cata').hidden = true;
            document.querySelectorAll('.cv-item').forEach(i => i.classList.toggle('is-on', i.dataset.id === c.id));
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

        $('cv-form').addEventListener('submit', async e => {
            e.preventDefault();
            const message = $('cv-text').value.trim();
            if (!message || !state.open || state.sending) return;
            state.sending = true;
            $('cv-send').disabled = true;
            try {
                await api(`${urls.base}/${state.open}/responder`, { method: 'POST', body: JSON.stringify({ message }) });
                $('cv-text').value = '';
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

        loadList();
        setInterval(() => {
            if (document.hidden) return;
            refreshThread();
            if (!state.next || $('cv-list').scrollTop === 0) loadList();
        }, 15000);
    })();
    </script>
@endsection
