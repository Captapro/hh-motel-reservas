{{-- Selector de mes + botones de exportar. Variables: $first, $prev, $next, $isCurrent, $exports = [[etiqueta, parámetro export]] --}}
<div class="head">
    <div>
        <h1>{{ $heading }} · {{ ucfirst($first->locale('es')->isoFormat('MMMM YYYY')) }}</h1>
        <p class="sub" style="margin:0;">{{ $lead }}</p>
    </div>
    <div class="actions">
        <span class="month">
            <a class="btn" href="{{ route($routeName, ['mes' => $prev] + ($query ?? [])) }}">← Anterior</a>
            @unless ($isCurrent)<a class="btn" href="{{ route($routeName, ['mes' => $next] + ($query ?? [])) }}">Siguiente →</a>@endunless
        </span>
        @foreach ($exports ?? [] as [$label, $param])
            <a class="btn primary" href="{{ route($routeName, ['mes' => $first->format('Y-m'), 'export' => $param] + ($query ?? [])) }}">⬇ {{ $label }}</a>
        @endforeach
        <button class="btn" type="button" onclick="window.print()">Imprimir</button>
    </div>
</div>
