@extends('admin.layout')
@section('title', $offer->exists ? 'Editar upsell' : 'Nuevo upsell')
@section('content')
    @php $t = old('type', $offer->type ?? 'category_upgrade'); @endphp

    <h1>{{ $offer->exists ? 'Editar upsell' : 'Nuevo upsell' }}</h1>
    <p class="sub">Precio fijo, estilo "¿quieres agrandar?". Se ofrece al reservar y al check-in.</p>

    <form method="POST" action="{{ $offer->exists ? route('admin.upsells.update', $offer) : route('admin.upsells.store') }}">
        @csrf
        @if ($offer->exists) @method('PUT') @endif

        <label>Nombre (lo ve recepción)</label>
        <input type="text" name="name" value="{{ old('name', $offer->name) }}" placeholder="Ej. Sube a MAX · 3 horas más · Pack 2 cervezas" required>

        <label>Tipo</label>
        <select name="type" id="type-select" onchange="hhToggleType()">
            <option value="category_upgrade" @selected($t === 'category_upgrade')>Subir de categoría</option>
            <option value="time_extension" @selected($t === 'time_extension')>Más tiempo de estadía</option>
            <option value="combo" @selected($t === 'combo')>Pack de productos (combo)</option>
        </select>

        <div class="t-block" data-for="category_upgrade">
            <div class="row2">
                <div>
                    <label>Desde la categoría</label>
                    <select name="from_room_category_id">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('from_room_category_id', $offer->from_room_category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>A la categoría</label>
                    <select name="to_room_category_id">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('to_room_category_id', $offer->to_room_category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="t-block" data-for="time_extension">
            <label>Minutos que suma</label>
            <input type="number" name="extra_minutes" min="15" step="15" value="{{ old('extra_minutes', $offer->extra_minutes ?? 180) }}" placeholder="180 = 3 horas">
        </div>

        <div class="t-block" data-for="combo">
            <label>Combo</label>
            <select name="combo_id">
                <option value="">—</option>
                @foreach ($combos as $combo)
                    <option value="{{ $combo->id }}" @selected(old('combo_id', $offer->combo_id) == $combo->id)>{{ $combo->name }} — ${{ number_format($combo->price, 0, ',', '.') }}</option>
                @endforeach
            </select>
            <div class="hint">El precio del pack es el del combo. No hace falta poner precio abajo. Este upsell lo ofrece recepción en el aviso rápido; para mostrar un combo en el catálogo del cliente, usa el botón "Catálogo" en Administración → Combos.</div>
        </div>

        <div class="t-block" data-for="category_upgrade time_extension">
            <label>Precio fijo del upsell (CLP)</label>
            <input type="number" name="price" min="0" value="{{ old('price', $offer->price) }}" placeholder="9990">
        </div>

        <div class="row2">
            <div>
                <label>Orden (menor = primero)</label>
                <input type="number" name="display_order" min="0" value="{{ old('display_order', $offer->display_order ?? 0) }}">
            </div>
            <div>
                <label>Estado</label>
                <select name="is_active">
                    <option value="1" @selected(old('is_active', $offer->is_active ?? true))>Activo</option>
                    <option value="0" @selected(! old('is_active', $offer->is_active ?? true))>Pausado</option>
                </select>
            </div>
        </div>

        <div class="actions" style="margin-top:20px;">
            <button class="btn" type="submit">Guardar</button>
            <a class="link" href="{{ route('admin.upsells.index') }}">Cancelar</a>
        </div>
    </form>

    <script>
        function hhToggleType() {
            const t = document.getElementById('type-select').value;
            document.querySelectorAll('.t-block').forEach(b => {
                b.style.display = b.dataset.for.split(' ').includes(t) ? '' : 'none';
            });
        }
        hhToggleType();
    </script>
@endsection
