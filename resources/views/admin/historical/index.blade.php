@extends('admin.layout')
@section('title', 'Ventas históricas')
@section('content')
    <div class="page-head">
        <div>
            <h1>Ventas históricas</h1>
            <p class="sub">Solo soporte. Carga planillas de ventas anteriores al sistema. Sirven al <b>Analista de IA</b> (solo cifras) y para medir cuánto vuelven los clientes. <b>No son reservas</b>: no cambian la caja, las ventas del mes ni Analytics.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="card" style="border-color:#7a2d2d; background:#3a1c1c; color:#f3b8b8;">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    @php $summary = session('historical_summary'); @endphp
    @if ($summary)
        <div class="card" style="border-color:{{ $summary['dry'] ? '#6b5a1e' : '#2e6e45' }};">
            <h2>{{ $summary['dry'] ? 'Simulación' : 'Cargada' }} · {{ $summary['source'] }}</h2>
            <p class="sub">Período {{ $summary['desde'] }} a {{ $summary['hasta'] }} · {{ $summary['lineas'] }} líneas de venta · {{ $summary['huespedes'] }} personas · {{ $summary['filas_ignoradas'] }} filas ignoradas (resúmenes y blancos) · {{ $summary['tipo_inferido'] }} sin tipo de venta tomadas como alojamiento</p>
            <table class="table">
                <thead><tr><th>Tipo</th><th>Líneas</th><th>Principal</th><th>Extras</th></tr></thead>
                <tbody>
                @foreach ($summary['por_tipo'] as $type => $row)
                    <tr><td>{{ $type }}</td><td>{{ $row['lineas'] }}</td><td>${{ number_format($row['principal'], 0, ',', '.') }}</td><td>${{ number_format($row['extras'], 0, ',', '.') }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <p><b>Venta total: ${{ number_format($summary['venta_principal'] + $summary['venta_extras'], 0, ',', '.') }}</b> — compárala con el total de la planilla.</p>
            <p class="sub">Documentos: RUT válido {{ $summary['documentos']['rut'] }} · RUT con dígito mal {{ $summary['documentos']['rut_invalido'] }} · otro documento {{ $summary['documentos']['otro'] }} · sin documento {{ $summary['documentos']['sin_documento'] }}</p>
        </div>
    @endif

    <div class="cols cols-2">
        <form method="POST" action="{{ route('admin.historical.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <h2>Cargar una planilla</h2>
            <p class="sub">En Google Sheets: abre la pestaña del mes → Archivo → Descargar → <b>Valores separados por comas (.csv)</b>. El archivo no se guarda en el sistema.</p>
            <label>Archivo CSV</label>
            <input type="file" name="archivo" accept=".csv,text/csv" required>
            <label>Nombre de la planilla</label>
            <input name="fuente" value="{{ old('fuente') }}" placeholder="planilla-2026-10" required>
            <p class="sub" style="font-size:12px;">Cargar de nuevo el mismo nombre actualiza esas ventas, no las duplica.</p>
            <label>Año de las fechas</label>
            <input type="number" name="anio" value="{{ old('anio', now()->year) }}" min="2015" max="2100" required>
            <p class="sub" style="font-size:12px;">La planilla trae "1-octubre" sin año: aquí se lo ponemos.</p>
            <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
                <button class="btn" type="submit" name="accion" value="simular" style="background:#3a3a3a;">Simular (no guarda)</button>
                <button class="btn" type="submit" name="accion" value="cargar" onclick="return confirm('¿Cargar esta planilla en las ventas históricas?');">Cargar</button>
            </div>
        </form>

        <div class="card">
            <h2>Lo que hay cargado</h2>
            @forelse ($sources as $source)
                <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; border-bottom:1px solid #333; padding:8px 0;">
                    <span><b>{{ $source->source }}</b><br><span class="sub" style="font-size:12.5px;">{{ \Carbon\Carbon::parse($source->first_day)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($source->last_day)->format('d/m/Y') }} · {{ $source->lines }} líneas</span></span>
                    <form method="POST" action="{{ route('admin.historical.destroy') }}" onsubmit="return confirm('¿Quitar la planilla {{ $source->source }} completa?');">
                        @csrf @method('DELETE')
                        <input type="hidden" name="fuente" value="{{ $source->source }}">
                        <button class="btn" type="submit" style="background:#b3202f;">Quitar</button>
                    </form>
                </div>
            @empty
                <p class="sub">Todavía no hay planillas cargadas.</p>
            @endforelse
            <p class="sub" style="margin-top:12px;">Personas registradas (titulares y acompañantes): {{ $guests }}</p>
            <p class="sub" style="font-size:12px;">Los nombres y RUT solo los ve soporte. El Analista de IA nunca los recibe: solo cuántas personas distintas hay y cuántas volvieron.</p>
        </div>
    </div>

    @if ($months->isNotEmpty())
        <div class="cols cols-2" style="margin-top:18px;">
            <div class="card">
                <h2>Por mes</h2>
                <table class="table">
                    <thead><tr><th>Mes</th><th>Estadías</th><th>Venta total</th></tr></thead>
                    <tbody>
                    @foreach ($months as $m)
                        <tr><td>{{ \Carbon\Carbon::parse($m['month'].'-01')->locale('es')->isoFormat('MMMM YYYY') }}</td><td>{{ $m['stays'] }}</td><td>${{ number_format($m['total'], 0, ',', '.') }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h2>Personas que más vuelven</h2>
                <p class="sub" style="font-size:12.5px;">Contadas por RUT válido, con 2 o más estadías. "Gastó" es lo pagado en las estadías donde figura como titular.</p>
                @if ($returning->isEmpty())
                    <p class="sub">Todavía no hay personas con más de una estadía en lo cargado.</p>
                @else
                    <table class="table">
                        <thead><tr><th>Persona</th><th>RUT</th><th>Veces</th><th>Gastó</th><th>Última</th></tr></thead>
                        <tbody>
                        @foreach ($returning as $p)
                            <tr><td>{{ $p['name'] }}</td><td>{{ $p['document'] }}</td><td>{{ $p['visits'] }}</td><td>${{ number_format($p['spent'], 0, ',', '.') }}</td><td>{{ $p['last'] }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif
@endsection
