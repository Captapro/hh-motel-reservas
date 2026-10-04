@extends('admin.layout')
@section('title', $category->exists ? 'Editar categoría' : 'Nueva categoría')
@section('content')
    <h1>{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1>
    <p class="sub">{{ $category->exists ? $category->name : 'Crear categoría de Playroom' }}</p>

    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="row2">
            <div>
                <label>Nombre</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required>
            </div>
            <div>
                <label>Nombre anterior (opcional)</label>
                <input type="text" name="former_name" value="{{ old('former_name', $category->former_name) }}">
            </div>
        </div>

        <label>Frase de venta corta (opcional) <span style="color:#888; font-weight:400;">— la que se ve destacada arriba en el catálogo público</span></label>
        <input type="text" name="sales_tip" maxlength="300" value="{{ old('sales_tip', $category->sales_tip) }}" placeholder="Ej. Ambiente íntimo con mobiliario seleccionado...">

        <label>Descripción (opcional)</label>
        <textarea name="description" rows="2">{{ old('description', $category->description) }}</textarea>

        <label>Características (una por línea)</label>
        <textarea name="features" rows="3">{{ old('features', $category->features ? implode("\n", $category->features) : '') }}</textarea>

        <div class="row2">
            <div>
                <label>Capacidad base (personas)</label>
                <input type="number" name="base_capacity" min="1" value="{{ old('base_capacity', $category->base_capacity ?? 2) }}" required>
            </div>
            <div>
                <label>Capacidad máxima (personas)</label>
                <input type="number" name="max_capacity" min="1" value="{{ old('max_capacity', $category->max_capacity ?? 10) }}" required>
            </div>
            <div>
                <label>Cobra adicional desde la N° persona</label>
                <input type="number" name="extra_guest_from" min="1" value="{{ old('extra_guest_from', $category->extra_guest_from ?? 3) }}" required>
            </div>
        </div>

        <div class="row2">
            <div>
                <label>Orden de despliegue</label>
                <input type="number" name="display_order" value="{{ old('display_order', $category->display_order ?? 0) }}" required>
            </div>
            <div>
                <label>Estado</label>
                <select name="is_active">
                    <option value="1" @selected(old('is_active', $category->is_active ?? true))>Activa</option>
                    <option value="0" @selected(!old('is_active', $category->is_active ?? true))>Inactiva</option>
                </select>
            </div>
        </div>

        <div class="actions" style="margin-top:20px;">
            <button class="btn" type="submit">Guardar</button>
            <a class="link" href="{{ route('admin.categories.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
