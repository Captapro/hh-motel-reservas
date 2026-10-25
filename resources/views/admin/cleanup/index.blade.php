@extends('admin.layout')
@section('title', 'Limpieza')
@section('content')
    <div class="page-head">
        <div>
            <h1>Limpiar historial de pruebas</h1>
            <p class="sub">Solo soporte. Borra registros de operación generados durante las pruebas. <b>No se puede deshacer.</b></p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.cleanup.destroy') }}" class="cols cols-2" onsubmit="return confirm('¿Limpiar el historial elegido? No se puede deshacer.');">
        @csrf @method('DELETE')
        <div class="card">
            <h2>Qué limpiar</h2>
            @foreach ($options as $key => $option)
                <label style="display:flex; gap:10px; align-items:flex-start; margin:0 0 12px; font-size:14px; color:#eee; cursor:pointer;">
                    <input type="checkbox" name="what[]" value="{{ $key }}" style="width:auto; margin-top:3px;" @checked(in_array($key, old('what', [])))>
                    <span>
                        <b>{{ $option['title'] }}</b> <span class="pill">{{ $option['count'] }} registros</span>
                        <span style="display:block; color:#999; font-size:12.5px; margin-top:2px;">{{ $option['help'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <div class="card">
            <h2>Alcance y confirmación</h2>
            <label>Solo lo anterior a esta fecha (opcional)</label>
            <input type="date" name="before" value="{{ old('before') }}">
            <p class="sub" style="font-size:12px; margin-top:4px;">Vacío = todo el historial de lo elegido. Con fecha, se conserva lo de ese día en adelante.</p>

            <label>Para confirmar, escribe <b>ELIMINAR</b></label>
            <input name="confirm_text" autocomplete="off" placeholder="ELIMINAR" style="max-width:220px;">

            <button class="btn" type="submit" style="margin-top:14px; background:#b3202f;">Limpiar historial</button>

            <div class="sub" style="margin-top:18px; font-size:12.5px;">
                <b>Lo que esto no toca:</b> reservas, pagos, consumos, clientes ni el stock. Esas se eliminan desde la ficha de cada reserva (solo soporte). La limpieza queda anotada en auditoría.
            </div>
        </div>
    </form>
@endsection
