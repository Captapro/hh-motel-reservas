@extends('admin.layout')
@section('title', 'Mobiliario')
@section('content')
    <div class="page-head">
        <div>
            <h1>Mobiliario y equipamiento</h1>
            <p class="sub">Catálogo por categorías. Los elementos se asignan a cada Playroom desde su ficha.</p>
        </div>
        <div class="actions">
            <details class="pop">
                <summary class="btn secondary">+ Nueva categoría</summary>
                <div class="pop-body">
                    <form method="POST" action="{{ route('admin.furniture.categories.store') }}">
                        @csrf
                        <label for="category-name">Nombre de la categoría</label>
                        <input id="category-name" name="name" maxlength="100" required placeholder="Ej. Mobiliario erótico, descanso, iluminación">
                        <button class="btn" type="submit" style="margin-top:10px;">Crear categoría</button>
                    </form>
                </div>
            </details>
            <details class="pop">
                <summary class="btn">+ Nuevo elemento</summary>
                <div class="pop-body">
                    <form method="POST" action="{{ route('admin.furniture.items.store') }}">
                        @csrf
                        <label for="item-category">Categoría</label>
                        <select id="item-category" name="furniture_category_id" required @disabled($categories->isEmpty())>
                            @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                        </select>
                        <label for="item-name">Nombre del elemento</label>
                        <input id="item-name" name="name" maxlength="100" required placeholder="Ej. Sillón tántrico">
                        <label for="item-icon">Ícono</label>
                        <input id="item-icon" name="icon" maxlength="12" placeholder="Pega un emoji, ej. 🛏️">
                        <div style="display:flex;flex-wrap:wrap;gap:5px;margin:8px 0 0;">
                            @foreach ($iconSuggestions as $icon)
                                <button type="button" onclick="document.getElementById('item-icon').value='{{ $icon }}'" style="padding:4px 8px;background:#2a2a2a;border:1px solid #3a3a3a;border-radius:7px;font-size:14px;cursor:pointer;line-height:1;">{{ $icon }}</button>
                            @endforeach
                        </div>
                        <button class="btn" type="submit" style="margin-top:10px;" @disabled($categories->isEmpty())>Crear elemento</button>
                        @if ($categories->isEmpty())<p class="sub">Primero crea una categoría.</p>@endif
                    </form>
                </div>
            </details>
            <a class="btn secondary" href="{{ route('admin.rooms.index') }}">Playrooms →</a>
        </div>
    </div>

    <div class="cols cols-4" style="margin-bottom:14px;">
        @foreach ([['Tipos de mobiliario', $summary['types']], ['Unidades asignadas', $summary['units']], ['Playrooms con mobiliario', $summary['rooms']], ['Elementos sin asignar', $summary['unassigned']]] as [$label, $value])
            <div class="card" style="padding:10px 14px;"><small style="display:block;color:#777;text-transform:uppercase;font-size:10px;letter-spacing:.06em;">{{ $label }}</small><strong style="display:block;font-size:22px;margin-top:2px;">{{ $value }}</strong></div>
        @endforeach
    </div>

    @php
        // Cada categoría va a la columna más corta, así las tres quedan alineadas por arriba.
        $stacks = [[], [], []];
        $heights = [0, 0, 0];
        foreach ($categories as $category) {
            $i = array_search(min($heights), $heights, true);
            $stacks[$i][] = $category;
            $heights[$i] += $category->items->count() + 2;
        }
    @endphp
    @forelse ($categories as $category)
        @if ($loop->first)<div class="cols cols-3">
            @foreach ($stacks as $stack)
                <div class="stack">
                    @foreach ($stack as $category)
                        <section class="card">
                            <div class="cols-h">
                                <h2>{{ $category->name }} <span class="pill">{{ $category->items->count() }}</span></h2>
                                <span class="actions" style="gap:10px;">
                                    <details class="pop">
                                        <summary>Renombrar</summary>
                                        <div class="pop-body">
                                            <form method="POST" action="{{ route('admin.furniture.categories.update', $category) }}">
                                                @csrf @method('PUT')
                                                <label>Nombre</label>
                                                <input name="name" value="{{ $category->name }}" maxlength="100" required>
                                                <button class="btn" type="submit" style="margin-top:10px;">Guardar</button>
                                            </form>
                                            @if ($category->items->isEmpty())
                                                <div class="danger">
                                                    <form method="POST" action="{{ route('admin.furniture.categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar esta categoría?')">
                                                        @csrf @method('DELETE')
                                                        <button class="danger-btn" type="submit">Eliminar categoría</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                </span>
                            </div>
                            <table class="tbl-compact">
                                <tbody>
                                    @forelse ($category->items as $item)
                                        @php $total = $item->rooms->sum(fn ($room) => (int) $room->pivot->quantity); @endphp
                                        <tr>
                                            <td style="width:30px; font-size:17px;">{{ $item->icon ?? '✦' }}</td>
                                            <td>{{ $item->name }}</td>
                                            <td style="color:#888; font-size:12px; white-space:nowrap;">{{ $total > 0 ? $total.' en '.$item->rooms->count().' hab.' : 'sin asignar' }}</td>
                                            <td style="text-align:right;">
                                                <details class="pop">
                                                    <summary>Editar</summary>
                                                    <div class="pop-body">
                                                        <div style="margin-bottom:10px;padding:8px 10px;background:#2a2a2a;border-radius:8px;font-size:12.5px;color:#ccc;">
                                                            <strong>Total registrado: {{ $total }}</strong>
                                                            @if ($item->rooms->isNotEmpty())
                                                                <div style="margin-top:4px;">
                                                                    @foreach ($item->rooms as $room)
                                                                        <span style="display:inline-block;margin:2px 4px 0 0;padding:2px 6px;background:#383838;border-radius:5px;">{{ $room->name }} ×{{ $room->pivot->quantity }}</span>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <div style="margin-top:4px;color:#888;">Sin Playrooms asignados</div>
                                                            @endif
                                                        </div>
                                                        <form method="POST" action="{{ route('admin.furniture.items.update', $item) }}" class="pop-grid">
                                                            @csrf @method('PUT')
                                                            <div><label>Nombre</label><input name="name" value="{{ $item->name }}" maxlength="100" required></div>
                                                            <div><label>Ícono</label><input id="icon-{{ $item->id }}" name="icon" value="{{ $item->icon }}" maxlength="12"></div>
                                                            <div class="full" style="display:flex;flex-wrap:wrap;gap:4px;">
                                                                @foreach ($iconSuggestions as $icon)
                                                                    <button type="button" onclick="document.getElementById('icon-{{ $item->id }}').value='{{ $icon }}'" style="padding:4px 8px;background:#2a2a2a;border:1px solid #3a3a3a;border-radius:7px;font-size:14px;cursor:pointer;line-height:1;">{{ $icon }}</button>
                                                                @endforeach
                                                            </div>
                                                            <div class="full"><label>Categoría</label>
                                                                <select name="furniture_category_id">
                                                                    @foreach ($categories as $option)<option value="{{ $option->id }}" @selected($option->id === $category->id)>{{ $option->name }}</option>@endforeach
                                                                </select>
                                                            </div>
                                                            <div class="full"><button class="btn" type="submit" style="padding:7px 14px; font-size:13px;">Guardar elemento</button></div>
                                                        </form>
                                                        <div class="danger">
                                                            @if ($item->rooms->isEmpty())
                                                                <form method="POST" action="{{ route('admin.furniture.items.destroy', $item) }}" onsubmit="return confirm('¿Eliminar este elemento?')">
                                                                    @csrf @method('DELETE')
                                                                    <button class="danger-btn" type="submit">Eliminar elemento</button>
                                                                </form>
                                                            @else
                                                                <small style="color:#888;">Está asignado a un Playroom y no se puede eliminar.</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" style="color:#666;">Sin elementos todavía.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </section>
                    @endforeach
                </div>
            @endforeach
        </div>@endif
    @empty
        <p class="sub">Aún no hay categorías. Crea el catálogo con los elementos reales de HH Motel.</p>
    @endforelse

    <details class="card" style="margin-top:14px;">
        <summary style="cursor:pointer;font-weight:600;">Equipamiento por Playroom <span class="pill">{{ $roomSummary->count() }}</span> <span class="sub" style="margin:0 0 0 8px;">— lo que encuentra recepción en cada uno; los mismos íconos salen en el tablero</span></summary>
        @if ($roomSummary->isNotEmpty())
            <div class="cols cols-4" style="margin-top:12px;">
                @foreach ($roomSummary as $room)
                    <div style="padding:10px 12px;background:#242424;border:1px solid #333;border-radius:9px;">
                        <strong style="font-size:14px;">{{ $room->name }}</strong>
                        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;">
                            @foreach ($room->furniture->where('pivot.quantity', '>', 0) as $equipment)
                                <span style="padding:3px 7px;background:#2f2f2f;color:#eee;border-radius:6px;font-size:11.5px;">{{ $equipment->icon ?? '✦' }} {{ $equipment->name }} ×{{ $equipment->pivot->quantity }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="sub" style="margin-top:10px;">Todavía no hay mobiliario asignado a Playrooms.</p>
        @endif
    </details>
@endsection
