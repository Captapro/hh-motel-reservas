@extends('admin.layout')
@section('title', 'Analista de ventas')
@section('content')
    <style>
        .an-grid { display:grid; grid-template-columns:240px minmax(0,1fr); gap:16px; align-items:start; }
        @media (max-width:900px) { .an-grid { grid-template-columns:minmax(0,1fr); } .an-threads { order:2; } }
        .an-threads a { display:block; padding:8px 10px; border-radius:8px; color:inherit; text-decoration:none; font-size:13px; line-height:1.35; }
        .an-threads a:hover { background:#f3f4f6; }
        .an-threads a.is-current { background:#fff3e8; }
        .an-threads small { display:block; color:#9ca3af; font-size:11.5px; margin-top:2px; }
        .an-chat { display:flex; flex-direction:column; gap:14px; }
        .an-q { align-self:flex-end; max-width:80%; background:#ff7918; color:#fff; padding:10px 14px; border-radius:14px 14px 4px 14px; font-size:14px; white-space:pre-wrap; }
        .an-a { font-size:14.5px; line-height:1.6; }
        .an-a h1, .an-a h2, .an-a h3 { font-size:15.5px; margin:16px 0 6px; }
        .an-a p { margin:0 0 10px; }
        .an-a ul, .an-a ol { margin:0 0 10px; padding-left:22px; }
        .an-a table { margin:8px 0 14px; font-size:13.5px; }
        .an-a th, .an-a td { border:1px solid #e5e7eb; padding:6px 8px; }
        .an-a code { background:#f3f4f6; padding:1px 5px; border-radius:4px; }
        .an-steps { font-size:13px; color:#6b7280; margin:0; padding-left:0; list-style:none; }
        .an-steps li::before { content:'✓ '; color:#16a34a; }
        .an-steps li.now::before { content:''; display:inline-block; width:10px; height:10px; margin-right:6px; border:2px solid #ff7918; border-right-color:transparent; border-radius:50%; animation:an-spin .8s linear infinite; vertical-align:-1px; }
        @keyframes an-spin { to { transform:rotate(360deg); } }
        .an-err { color:#b3202f; font-size:14px; }
        .an-form textarea { min-height:70px; resize:vertical; }
        .an-form .row { display:flex; gap:10px; align-items:center; justify-content:space-between; margin-top:8px; flex-wrap:wrap; }
        .an-chips { display:flex; flex-wrap:wrap; gap:8px; }
        .an-chips button { background:#fff; border:1px solid #e5e7eb; color:#374151; border-radius:20px; padding:7px 12px; font-size:13px; cursor:pointer; text-align:left; }
        .an-chips button:hover { border-color:#ff7918; }
        .an-meta { font-size:11.5px; color:#9ca3af; margin-top:4px; }
        .an-context summary { cursor:pointer; font-size:14px; }
    </style>

    <div class="page-head">
        <div>
            <h1>Analista de ventas</h1>
            <p class="sub">Pregúntale por el comportamiento de las ventas y pídele recomendaciones de precios, ofertas y upsells. Lee los datos del sistema, pero no cambia nada.</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.analyst.index', ['conversacion' => (string) \Illuminate\Support\Str::uuid()]) }}">+ Nueva conversación</a>
    </div>

    @unless ($configured)
        <div class="errors">Falta configurar la clave de la API de Claude (<code>ANTHROPIC_API_KEY</code>) en Render. Sin ella el analista no puede responder.</div>
    @endunless

    <details class="card an-context" @if (! $setting->business_context) open @endif>
        <summary><b>Contexto del negocio</b> <span class="an-meta">· objetivos, costos, competencia, fechas clave y reglas. El analista lo lee en cada pregunta.@if ($setting->business_context && $setting->updated_at) Actualizado {{ $setting->updated_at->format('d/m/Y H:i') }}@if ($setting->editor) por {{ $setting->editor->name }}@endif.@endif</span></summary>
        <form method="POST" action="{{ route('admin.analyst.context') }}">
            @csrf @method('PUT')
            <textarea name="business_context" rows="16" maxlength="8000" style="margin-top:10px; font-family:ui-monospace, monospace; font-size:13px;">{{ old('business_context', $setting->business_context ?: \App\Models\AnalystSetting::TEMPLATE) }}</textarea>
            <div class="row" style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:8px; flex-wrap:wrap;">
                <span class="an-meta">Mientras más concreto (montos, precios de la competencia, metas), más precisas las recomendaciones. No escribas datos de clientes ni claves.</span>
                <button class="btn" type="submit">Guardar contexto</button>
            </div>
        </form>
    </details>

    <div class="an-grid">
        <div class="card an-threads">
            <h2>Conversaciones</h2>
            @forelse ($threads as $t)
                <a href="{{ route('admin.analyst.index', ['conversacion' => $t['thread']]) }}" class="{{ $t['thread'] === $thread ? 'is-current' : '' }}">
                    {{ $t['title'] }}
                    <small>{{ $t['last_at']->format('d/m H:i') }} · {{ $t['total'] }} {{ $t['total'] === 1 ? 'pregunta' : 'preguntas' }}</small>
                </a>
            @empty
                <p class="sub" style="margin:0">Todavía no hay conversaciones.</p>
            @endforelse
        </div>

        <div class="an-chat">
            <div id="an-log" class="an-chat">
                @foreach ($runs as $run)
                    <div class="an-q">{{ $run->question }}</div>
                    <div class="card an-a" data-run="{{ $run->isFinished() ? '' : route('admin.analyst.show', $run) }}">
                        @if ($run->status === 'listo')
                            {!! \App\Http\Controllers\Admin\SalesAnalystController::markdown($run->answer) !!}
                        @elseif ($run->status === 'error')
                            <p class="an-err">{{ $run->error }}</p>
                        @else
                            <ul class="an-steps"><li class="now">Analizando…</li></ul>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($runs->isEmpty())
                <div class="an-chips" id="an-chips">
                    @foreach ([
                        'Dame recomendaciones concretas de precios, ofertas y upsells según los últimos 90 días.',
                        '¿Qué días y horarios tienen más ocupación y cuáles quedan vacíos? ¿Qué harías en cada caso?',
                        '¿Qué categoría y duración dejan más plata? ¿Hay alguna mal preciada?',
                        '¿Están funcionando las ofertas y cupones, o solo regalan descuento?',
                        '¿Cómo vienen los upsells y los consumos? ¿Cómo subo el ticket promedio?',
                        'Compara este mes con el anterior y explícame qué cambió.',
                    ] as $suggestion)
                        <button type="button" data-q="{{ $suggestion }}">{{ $suggestion }}</button>
                    @endforeach
                </div>
            @endif

            <form class="card an-form" id="an-form">
                <textarea id="an-question" maxlength="2000" placeholder="Escribe tu pregunta… (por ejemplo: ¿conviene subir el precio del viernes en la noche?)" @disabled(! $configured)></textarea>
                <div class="row">
                    <span class="an-meta">Cada respuesta tarda entre 30 segundos y 2 minutos.</span>
                    <button class="btn" type="submit" id="an-send" @disabled(! $configured)>Preguntar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const form = document.getElementById('an-form');
        const input = document.getElementById('an-question');
        const send = document.getElementById('an-send');
        const log = document.getElementById('an-log');
        const thread = @json($thread);
        const askUrl = @json(route('admin.analyst.ask'));
        const token = @json(csrf_token());

        function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function render(box, data) {
            if (data.status === 'listo') { box.innerHTML = data.answer_html; return true; }
            if (data.status === 'error') { box.innerHTML = '<p class="an-err">' + esc(data.error || 'Error') + '</p>'; return true; }
            const steps = (data.steps || []).map(s => '<li>' + esc(s) + '</li>').join('');
            box.innerHTML = '<ul class="an-steps">' + steps + '<li class="now">' + (steps ? 'Pensando las recomendaciones…' : 'Analizando…') + '</li></ul>';
            return false;
        }

        function poll(box, url) {
            setBusy(true);
            const tick = () => fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { if (render(box, data)) { setBusy(false); } else { setTimeout(tick, 3000); } })
                .catch(() => setTimeout(tick, 5000));
            tick();
        }

        function setBusy(b) { send.disabled = b; send.textContent = b ? 'Analizando…' : 'Preguntar'; }

        function ask(question) {
            question = question.trim();
            if (!question) return;
            const chips = document.getElementById('an-chips'); if (chips) chips.remove();
            log.insertAdjacentHTML('beforeend', '<div class="an-q">' + esc(question) + '</div><div class="card an-a"><ul class="an-steps"><li class="now">Analizando…</li></ul></div>');
            const box = log.lastElementChild;
            input.value = '';
            setBusy(true);
            box.scrollIntoView({ behavior: 'smooth', block: 'start' });
            fetch(askUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ question, thread }),
            }).then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) { box.innerHTML = '<p class="an-err">' + esc(data.message || 'No se pudo enviar la pregunta.') + '</p>'; setBusy(false); return; }
                if (!history.state) history.replaceState({}, '', '?conversacion=' + thread);
                if (!render(box, data)) poll(box, data.poll_url); else setBusy(false);
            }).catch(() => { box.innerHTML = '<p class="an-err">Sin conexión. Intenta de nuevo.</p>'; setBusy(false); });
        }

        form.addEventListener('submit', e => { e.preventDefault(); if (!send.disabled) ask(input.value); });
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); form.requestSubmit(); } });
        document.querySelectorAll('#an-chips button').forEach(b => b.addEventListener('click', () => { if (!send.disabled) ask(b.dataset.q); }));
        document.querySelectorAll('.an-a[data-run]').forEach(box => { if (box.dataset.run) poll(box, box.dataset.run); });
    })();
    </script>
@endsection
