@extends('admin.layout')
@section('title', 'Clientes que vuelven')
@section('content')
    <div class="page-head">
        <div>
            <h1>Clientes que vuelven</h1>
            <p class="sub">Quiénes repiten según las planillas de recepción cargadas. Solo soporte: aquí se ven nombres y RUT. Se cuenta por <b>RUT válido</b> y solo estadías de <b>alojamiento</b>; no incluye reservas del sistema.</p>
        </div>
        <a class="link" href="{{ route('admin.historical.index') }}">← Ventas históricas</a>
    </div>

    @if ($loaded === null)
        <div class="card"><p class="sub">Todavía no hay planillas cargadas. Súbelas en Ventas históricas y vuelve aquí.</p></div>
    @else
        @php
            $money = fn ($n) => '$'.number_format($n, 0, ',', '.');
            $pct = fn ($n, $t) => $t ? round($n / $t * 100) : 0;
            $url = fn (array $over = []) => route('admin.historical.customers', array_filter(array_merge(['desde' => $fromMonth, 'hasta' => $toMonth, 'min' => $min, 'orden' => $order, 'rol' => $role, 'q' => $search], $over), fn ($v) => $v !== '' && $v !== null));
        @endphp

        <div class="card">
            <h2>Período</h2>
            <p class="sub" style="font-size:12.5px;">Meses cargados: {{ $monthName($loaded['first']) }} a {{ $monthName($loaded['last']) }}. Elige un mes, dos, tres… o todo.</p>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin:8px 0 12px;">
                @foreach ($chips as $label => $range)
                    @php $on = $range['desde'] === $fromMonth && $range['hasta'] === $toMonth; @endphp
                    <a class="btn {{ $on ? '' : 'secondary' }}" style="padding:6px 12px; font-size:12.5px;" href="{{ $url($range) }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('admin.historical.customers') }}" class="add-row">
                <div><label>Desde (mes)</label><input type="month" name="desde" value="{{ $fromMonth }}" min="{{ $loaded['first'] }}" max="{{ $loaded['last'] }}"></div>
                <div><label>Hasta (mes)</label><input type="month" name="hasta" value="{{ $toMonth }}" min="{{ $loaded['first'] }}" max="{{ $loaded['last'] }}"></div>
                <div><label>Mínimo de visitas</label>
                    <select name="min">
                        @foreach ([1 => '1 o más (todos)', 2 => '2 o más (los que volvieron)', 3 => '3 o más', 4 => '4 o más', 5 => '5 o más'] as $v => $l)
                            <option value="{{ $v }}" @selected($min === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Ordenar por</label>
                    <select name="orden">
                        @foreach (['visitas' => 'Más visitas', 'gasto' => 'Más gasto', 'ultima' => 'Visita más reciente', 'nombre' => 'Nombre'] as $v => $l)
                            <option value="{{ $v }}" @selected($order === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Mostrar</label>
                    <select name="rol">
                        <option value="todos" @selected($role === 'todos')>Titulares y acompañantes</option>
                        <option value="titular" @selected($role === 'titular')>Solo quienes fueron titular</option>
                    </select>
                </div>
                <div><label>Buscar nombre o RUT</label><input name="q" value="{{ $search }}" placeholder="Ej: Castro o 17769538"></div>
                <button class="btn" type="submit">Aplicar</button>
            </form>
        </div>

        <div class="cols" style="margin-top:14px; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));">
            <div class="card"><h3>Personas en el período</h3><p style="font-size:26px; margin:4px 0;">{{ $stats['people'] }}</p><p class="sub" style="margin:0;">distintas, por RUT válido</p></div>
            <div class="card"><h3>Volvieron dentro del período</h3><p style="font-size:26px; margin:4px 0;">{{ $stats['returned'] }}</p><p class="sub" style="margin:0;">{{ $pct($stats['returned'], $stats['people']) }}% · 2 o más estadías en estos meses</p></div>
            <div class="card"><h3>Ya habían venido antes</h3><p style="font-size:26px; margin:4px 0;">{{ $stats['came_before'] }}</p><p class="sub" style="margin:0;">{{ $pct($stats['came_before'], $stats['people']) }}% · tienen estadías antes del período</p></div>
            <div class="card"><h3>Primera vez en lo cargado</h3><p style="font-size:26px; margin:4px 0;">{{ $stats['first_time'] }}</p><p class="sub" style="margin:0;">{{ $pct($stats['first_time'], $stats['people']) }}% · sin estadías antes</p></div>
        </div>

        <div class="card" style="margin-top:14px;">
            <h2>{{ $total }} {{ $total === 1 ? 'persona' : 'personas' }} · {{ $monthName($fromMonth) }}{{ $fromMonth !== $toMonth ? ' a '.$monthName($toMonth) : '' }}</h2>
            <p class="sub" style="font-size:12.5px;">
                <b>Visitas</b>: estadías de alojamiento en el período (titular o acompañante). <b>Tipo de playroom</b>: en qué tipo de habitación fueron esas estadías (por ejemplo «Plus ×3 · Lite ×1»; «Sin tipo» si la planilla no lo trae). <b>Gastó</b>: alojamiento de las estadías en que fue <b>titular</b> (no incluye hora extra ni extras aparte; si siempre fue acompañante sale $0).
                <b>Antes del período</b>: estadías anteriores al primer mes elegido («—» si el período empieza en el primer mes cargado, porque antes no hay planillas). <b>En todo lo cargado</b>: todas sus estadías en las planillas. Con pocos meses cargados, no tener visitas antes no significa que sea cliente nuevo.
            </p>
            @if ($rows->isEmpty())
                <p class="sub">Nadie cumple con esos filtros. Prueba con «1 o más» visitas o un período más largo.</p>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Persona</th><th>RUT</th><th>Visitas</th><th>Titular / acomp.</th><th>Tipo de playroom</th><th>Gastó</th><th>Meses en que vino</th><th>Primera</th><th>Última</th><th>Antes del período</th><th>En todo lo cargado</th><th>En el sistema</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $p)
                            <tr>
                                <td>{{ $p['name'] }}</td>
                                <td style="white-space:nowrap;">{{ $p['document'] }}</td>
                                <td><b>{{ $p['visits'] }}</b></td>
                                <td>{{ $p['as_holder'] }} / {{ $p['as_companion'] }}</td>
                                <td>{{ collect($p['categories'])->map(fn ($n, $c) => \Illuminate\Support\Str::title(mb_strtolower($c)).' ×'.$n)->implode(' · ') }}</td>
                                <td>{{ $money($p['spent']) }}</td>
                                <td style="text-transform:capitalize;">{{ collect($p['months'])->map(fn ($m) => \Carbon\Carbon::parse($m.'-01')->locale('es')->isoFormat('MMMM'))->unique()->implode(', ') }}</td>
                                <td>{{ $p['first']->format('d/m/Y') }}</td>
                                <td>{{ $p['last']->format('d/m/Y') }}</td>
                                <td>{{ $fromMonth === $loaded['first'] ? '—' : $p['before'] }}</td>
                                <td>{{ $p['total'] }}</td>
                                <td>@if (isset($systemCustomers[$p['document']]))<a class="link" href="{{ route('admin.customers.show', $systemCustomers[$p['document']]) }}">Ver cliente</a>@else<span style="color:#888;">—</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($total > $limit)
                    <p class="sub" style="margin-top:8px;">Se muestran las primeras {{ $limit }} de {{ $total }}. Sube el mínimo de visitas o busca por nombre o RUT para acotar.</p>
                @endif
            @endif
        </div>
    @endif
@endsection
