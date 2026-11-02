@extends('admin.layout')
@section('title', $product->exists ? 'Editar producto' : 'Nuevo producto')
@section('content')
    <h1>{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
    <p class="sub">{{ $product->exists ? $product->name : 'Agregar al catálogo de consumo' }}</p>

    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="row2">
            <div>
                <label>Nombre</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
            </div>
            <div>
                <label>Categoría (opcional)</label>
                <input type="text" name="category" value="{{ old('category', $product->category) }}" placeholder="Bebidas, Cervezas, Juegos, Higiene...">
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Precio (CLP)</label>
                <input type="number" name="price" min="0" value="{{ old('price', $product->price ?? 0) }}" required>
            </div>
            <div>
                <label>Orden de despliegue</label>
                <input type="number" name="display_order" value="{{ old('display_order', $product->display_order ?? 0) }}" required>
            </div>
        </div>

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
            <input type="checkbox" name="track_inventory" value="1" style="width:auto;" @checked(old('track_inventory', $product->track_inventory ?? true)) id="track_inventory">
            Controlar stock de este producto
        </label>

        <div class="row2">
            <div>
                <label>Stock actual</label>
                <input type="number" name="stock" min="0" value="{{ old('stock', $product->stock ?? 0) }}">
            </div>
            <div>
                <label>Avisar cuando quede igual o menos de</label>
                <input type="number" name="low_stock_threshold" min="0" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}">
            </div>
        </div>

        <label>Estado</label>
        <select name="is_active">
            <option value="1" @selected(old('is_active', $product->is_active ?? true))>Activo</option>
            <option value="0" @selected(!old('is_active', $product->is_active ?? true))>Inactivo</option>
        </select>

        <div class="actions" style="margin-top:20px;">
            <button class="btn" type="submit">Guardar</button>
            <a class="link" href="{{ route('admin.products.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
