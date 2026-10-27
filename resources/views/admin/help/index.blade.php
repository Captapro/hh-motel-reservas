@extends('admin.layout')
@section('title', 'Asistente de ayuda')
@section('content')
    <style>
        .hp-q { font-weight:600; }
        .hp-a { color:#4b5563; font-size:13px; margin-top:4px; white-space:pre-wrap; max-height:5.6em; overflow:hidden; }
        details.hp-more summary { cursor:pointer; color:#ff7918; font-size:12.5px; margin-top:4px; }
        details.hp-more .hp-a { max-height:none; }
        .hp-meta { font-size:12px; color:#9ca3af; white-space:nowrap; }
        .hp-err { color:#b3202f; }
        .hp-stats { display:flex; gap:24px; font-size:13px; color:#6b7280; margin-bottom:8px; }
        .hp-stats b { color:#111827; font-size:18px; display:block; }
    </style>

    <div class="page-head">
        <div>
            <h1>Asistente de ayuda</h1>
            <p class="sub">El equipo lo abre con el botón <b>Ayuda</b> de cualquier pantalla y le pregunta cómo hacer algo en el sistema. Responde con el manual del sistema y con las reglas de la casa que escribas aquí. No ve ventas, clientes ni claves.</p>
        </div>
    </div>

    <details class="card" @if (! $setting->house_rules) open @endif>
        <summary style="cursor:pointer"><b>Reglas de la casa</b> <span class="sub">· políticas del motel que el asistente debe respetar.@if ($setting->house_rules && $setting->updated_at) Actualizado {{ $setting->updated_at->format('d/m/Y H:i') }}@if ($setting->editor) por {{ $setting->editor->name }}@endif.@endif</span></summary>
        <form method="POST" action="{{ route('admin.help.rules') }}">
            @csrf @method('PUT')
            <textarea name="house_rules" rows="18" maxlength="8000" style="margin-top:10px; font-family:ui-monospace, monospace; font-size:13px;">{{ old('house_rules', $setting->house_rules ?: \App\Models\HelpSetting::TEMPLATE) }}</textarea>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:8px; flex-wrap:wrap;">
                <span class="sub" style="margin:0">Escribe solo lo que el equipo puede saber. No pongas claves de acceso ni datos de clientes.</span>
                <button class="btn" type="submit">Guardar reglas</button>
            </div>
        </form>
    </details>

    <div class="card">
        <div class="cols-h">
            <h2>Preguntas del equipo</h2>
            @if ($total)
                <form method="POST" action="{{ route('admin.help.destroy_all') }}" onsubmit="return confirm('¿Eliminar todo el historial de preguntas? No se puede deshacer.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="danger-btn">Eliminar historial</button>
                </form>
            @endif
        </div>
        <div class="hp-stats"><span><b>{{ $lastWeek }}</b>últimos 7 días</span><span><b>{{ $total }}</b>en total</span></div>
        <p class="sub">Lo que más preguntan es lo que conviene reforzar en el entrenamiento.</p>
        <table>
            <thead><tr><th>Pregunta y respuesta</th><th>Quién</th><th>Pantalla</th><th>Cuándo</th></tr></thead>
            <tbody>
                @forelse ($questions as $q)
                    <tr>
                        <td>
                            <div class="hp-q">{{ $q->question }}</div>
                            @if ($q->status === 'error')
                                <div class="hp-a hp-err">{{ $q->answer }}</div>
                            @else
                                <details class="hp-more"><summary>Ver respuesta</summary><div class="hp-a">{{ $q->answer }}</div></details>
                            @endif
                        </td>
                        <td class="hp-meta">{{ $q->user?->name ?? '—' }}</td>
                        <td class="hp-meta">{{ \Illuminate\Support\Str::limit((string) $q->page, 40) }}</td>
                        <td class="hp-meta">{{ $q->created_at->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="sub">Todavía nadie ha usado el asistente.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($questions->hasPages())
            <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:13px;">
                <span>@if (! $questions->onFirstPage())<a class="link" href="{{ $questions->previousPageUrl() }}">← Más recientes</a>@endif</span>
                <span class="sub" style="margin:0">Página {{ $questions->currentPage() }} de {{ $questions->lastPage() }}</span>
                <span>@if ($questions->hasMorePages())<a class="link" href="{{ $questions->nextPageUrl() }}">Anteriores →</a>@endif</span>
            </div>
        @endif
    </div>
@endsection
