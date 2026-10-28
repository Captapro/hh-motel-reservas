@extends('admin.layout')
@section('title', 'Extras core')
@section('content')
    @php $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <div class="page-head">
        <div>
            <h1>Extras core</h1>
            <p class="sub"><b>Extra core</b> es lo adicional de la habitación que el cliente pide: hora extra, persona adicional, toalla adicional, lavandería… Los <b>consumos</b> (preservativos, bebidas) son <b>extras</b> y van en Productos. Este listado empieza vacío: lo vas armando tú.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="card" style="border-color:#7a2d2d; background:#3a1c1c; color:#f3b8b8;">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="cols cols-2">
        <form method="POST" action="{{ route('admin.core_extras.store') }}" class="card">
            @csrf
            <h2>Agregar un extra core</h2>
            <label>Nombre</label>
            <input name="name" value="{{ old('name') }}" placeholder="Ej. Toalla adicional" maxlength="80" required>
            <label>Precio (opcional)</label>
            <input type="number" name="price" value="{{ old('price') }}" min="0" placeholder="Ej. 3000">
            <button class="btn" type="submit" style="margin-top:12px;">Agregar al listado</button>
        </form>

        <div class="card">
            <h2>Listado ({{ $items->count() }})</h2>
            @forelse ($items as $item)
                <form method="POST" action="{{ route('admin.core_extras.update', $item) }}" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; border-bottom:1px solid #333; padding:8px 0;">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $item->name }}" maxlength="80" style="flex:1; min-width:150px;" required>
                    <input type="number" name="price" value="{{ $item->price }}" min="0" placeholder="Precio" style="width:110px;">
                    <label style="margin:0; display:flex; gap:6px; align-items:center; font-size:13px;"><input type="checkbox" name="is_active" value="1" @checked($item->is_active) style="width:auto;"> Activo</label>
                    <button class="btn" type="submit">Guardar</button>
                    <button class="btn" type="submit" form="del-{{ $item->id }}" style="background:#b3202f;" onclick="return confirm('¿Quitar «{{ $item->name }}» del listado?');">Quitar</button>
                </form>
                <form id="del-{{ $item->id }}" method="POST" action="{{ route('admin.core_extras.destroy', $item) }}" style="display:none;">@csrf @method('DELETE')</form>
            @empty
                <p class="sub">El listado está vacío. Agrega el primero a la izquierda, o toma los que ya aparecen en tus planillas (abajo).</p>
            @endforelse
        </div>
    </div>

    @if ($found->isNotEmpty())
        <div class="card" style="margin-top:18px;">
            <h2>Extra core que aparece en las planillas cargadas</h2>
            <p class="sub">Así los anotó recepción. Agrega al listado los que quieras conservar.</p>
            <table class="table">
                <thead><tr><th>Nombre en la planilla</th><th>Líneas</th><th>Venta</th><th></th></tr></thead>
                <tbody>
                @foreach ($found as $f)
                    <tr>
                        <td>{{ $f['name'] !== '' ? $f['name'] : '(sin detalle)' }}</td>
                        <td>{{ $f['lines'] }}</td>
                        <td>{{ $money($f['total']) }}</td>
                        <td>
                            @if ($f['listed'])
                                <span class="pill">En el listado</span>
                            @elseif ($f['name'] !== '')
                                <form method="POST" action="{{ route('admin.core_extras.store') }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="name" value="{{ ucfirst(mb_strtolower($f['name'])) }}">
                                    <button class="btn" type="submit">Agregar al listado</button>
                                </form>
                            @else
                                <span class="sub">Sin nombre: no se puede agregar</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
