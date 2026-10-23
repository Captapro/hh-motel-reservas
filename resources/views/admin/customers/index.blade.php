@extends('admin.layout')
@section('title', 'Clientes')
@section('content')
    <style>
        .seg-chips { display:flex; gap:8px; flex-wrap:wrap; margin: 4px 0 20px; }
        .seg-chip { display:flex; align-items:baseline; gap:6px; background:#1c1c1c; border:1px solid #333; border-radius:8px; padding:8px 13px; text-decoration:none; color:#eee; font-size:12.5px; }
        .seg-chip b { font-family: ui-monospace, monospace; font-size:15px; }
        .seg-chip.active { border-color:#ff7918; }
        .seg-chip.nuevo b { color:#7fbcdc; }
        .seg-chip.frecuente b { color:#6fd39a; }
        .seg-chip.ocasional b { color:#e8c76f; }
        .seg-chip.esporadico b { color:#e88a9a; }
        .search-form { display:flex; gap:8px; margin-bottom: 18px; }
        .search-form input { flex:1; }
        .pill-nuevo { background:#1c2f3a; color:#7fbcdc; }
        .pill-frecuente { background:#1c3a2a; color:#6fd39a; }
        .pill-ocasional { background:#3a331c; color:#e8c76f; }
        .pill-esporadico { background:#3a1c22; color:#e88a9a; }
        .name-link { color:#eee; text-decoration:none; font-weight:600; }
        .name-link:hover { color:#ff7918; }
    </style>

    <h1>Clientes</h1>
    <p class="sub">Historial de estadías y recurrencia — para identificar a quién vale la pena fidelizar o reactivar.</p>

    <section class="card segment-guide">
        <h2>¿Cómo se clasifica cada cliente?</h2>
        <p>La categoría se calcula automáticamente con las reservas finalizadas de los últimos 12 meses. Las canceladas, expiradas y no-show no cuentan.</p>
        <div class="segment-rules">
            <div><strong class="rule-new">Cliente nuevo</strong><span>0 o 1 estadía registrada</span></div>
            <div><strong class="rule-frequent">Alta recurrencia</strong><span>2+ estadías y vuelve en promedio cada 45 días o menos</span></div>
            <div><strong class="rule-occasional">Baja recurrencia</strong><span>Historial reciente, pero vuelve con más de 45 días promedio</span></div>
            <div><strong class="rule-sporadic">Esporádico</strong><span>Su última visita fue hace más de 120 días</span></div>
        </div>
        <small>La clasificación puede cambiar automáticamente después de cada estadía. Las notas y preferencias se registran aparte y no alteran esta medición.</small>
    </section>

    <form class="search-form" method="GET" action="{{ route('admin.customers.index') }}">
        @if ($segmentFilter !== '')<input type="hidden" name="segment" value="{{ $segmentFilter }}">@endif
        <input type="text" name="q" value="{{ $query }}" placeholder="Buscar por nombre o teléfono...">
        <button class="btn" type="submit">Buscar</button>
    </form>

    @php
        $counts = ['nuevo' => 0, 'frecuente' => 0, 'ocasional' => 0, 'esporadico' => 0];
        foreach ($customers as $c) { $counts[$c->computed_segment['type']]++; }
        $baseParams = $query !== '' ? ['q' => $query] : [];
    @endphp
    <div class="seg-chips">
        <a class="seg-chip {{ $segmentFilter === '' ? 'active' : '' }}" href="{{ route('admin.customers.index', $baseParams) }}"><b>{{ $customers->count() }}</b> Todos</a>
        <a class="seg-chip nuevo {{ $segmentFilter === 'nuevo' ? 'active' : '' }}" href="{{ route('admin.customers.index', $baseParams + ['segment' => 'nuevo']) }}"><b>{{ $counts['nuevo'] }}</b> Nuevos</a>
        <a class="seg-chip frecuente {{ $segmentFilter === 'frecuente' ? 'active' : '' }}" href="{{ route('admin.customers.index', $baseParams + ['segment' => 'frecuente']) }}"><b>{{ $counts['frecuente'] }}</b> Alta recurrencia</a>
        <a class="seg-chip ocasional {{ $segmentFilter === 'ocasional' ? 'active' : '' }}" href="{{ route('admin.customers.index', $baseParams + ['segment' => 'ocasional']) }}"><b>{{ $counts['ocasional'] }}</b> Baja recurrencia</a>
        <a class="seg-chip esporadico {{ $segmentFilter === 'esporadico' ? 'active' : '' }}" href="{{ route('admin.customers.index', $baseParams + ['segment' => 'esporadico']) }}"><b>{{ $counts['esporadico'] }}</b> Esporádicos</a>
    </div>

    @if(auth()->user()?->isAdministrador())
        <details class="card" style="margin-bottom:18px;">
            <summary style="cursor:pointer;font-weight:700;">Configurar clasificación y lista negra</summary>
            <form method="POST" action="{{ route('admin.customers.rules.update') }}" style="margin-top:14px;">
                @csrf
                <p class="sub">Modifica los días sin tocar el código. La regla se recalcula automáticamente al consultar cada cliente.</p>
                @foreach($segmentRules as $rule)
                    <div style="display:grid;grid-template-columns:1.2fr .8fr .8fr .8fr;gap:10px;margin:8px 0;align-items:end;">
                        <div><label>Etiqueta {{ $rule->segment }}</label><input name="rules[{{ $rule->id }}][label]" value="{{ $rule->label }}"></div>
                        <div><label>Visitas mínimas</label><input type="number" min="0" name="rules[{{ $rule->id }}][minimum_stays]" value="{{ $rule->minimum_stays }}"></div>
                        <div><label>Dentro de días</label><input type="number" min="1" name="rules[{{ $rule->id }}][analysis_window_days]" value="{{ $rule->analysis_window_days }}" placeholder="—"></div>
                        <div><label>Estrellas</label><input type="number" min="0" max="5" name="rules[{{ $rule->id }}][stars]" value="{{ $rule->stars }}"></div>
                    </div>
                @endforeach
                <button class="btn" type="submit">Guardar reglas</button>
            </form>
            <hr style="border-color:#333;margin:18px 0;">
            <form method="POST" action="{{ route('admin.customers.blacklist.import') }}" enctype="multipart/form-data">
                @csrf
                <label>Importar lista negra CSV</label>
                <small style="display:block;color:#aaa;margin:4px 0 8px;">Primera columna: teléfono. Segunda columna opcional: motivo. Solo actualiza clientes que ya existen.</small>
                <input type="file" name="file" accept=".csv,.txt" required>
                <button class="btn" type="submit" style="margin-top:10px;">Importar lista negra</button>
            </form>
        </details>
    @endif

    <div class="card">
        <table>
            <thead><tr><th>Cliente</th><th>Teléfono</th><th>Segmento</th><th>Estadías</th><th>Última visita</th></tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td><a class="name-link" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a></td>
                        <td>{{ $customer->phone_e164 }}</td>
                        <td><span class="pill pill-{{ $customer->computed_segment['type'] }}">{{ $customer->computed_segment['stars'] ? str_repeat('⭐', $customer->computed_segment['stars']).' ' : '' }}{{ strtoupper($customer->computed_segment['label']) }}</span><small style="display:block;color:#999;margin-top:3px;">{{ $customer->computed_segment['window_visits'] }} visitas{{ $customer->computed_segment['window_days'] ? ' / '.$customer->computed_segment['window_days'].' días' : '' }}</small></td>
                        <td>{{ $customer->computed_segment['total_stays'] }}</td>
                        <td>{{ $customer->computed_segment['last_visit_at']?->timezone('America/Santiago')->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:#666;">Sin clientes que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <style>
        .segment-guide{margin:18px 0 20px;background:#272727;border-color:#3d3d3d}.segment-guide h2{margin:0 0 6px;font-size:16px;color:#fff}.segment-guide p,.segment-guide small{display:block;color:#aaa;font-size:12.5px;line-height:1.45}.segment-rules{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:14px 0}.segment-rules div{display:flex;flex-direction:column;gap:5px;padding:11px 12px;border:1px solid #444;border-radius:8px;background:#202020}.segment-rules span{font-size:12px;color:#ccc}.rule-new{color:#7fbcdc}.rule-occasional{color:#e8c76f}.rule-frequent{color:#6fd39a}.rule-sporadic{color:#e88a9a}@media(max-width:800px){.segment-rules{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>
@endsection
