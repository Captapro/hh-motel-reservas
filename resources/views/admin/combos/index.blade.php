@extends('admin.layout')
@section('title', 'Combos')
@section('content')
    <div class="actions" style="justify-content:space-between; margin-bottom:18px;">
        <div><h1>Combos</h1><p class="sub">Paquetes de productos a un precio especial — "2 cervezas por $5.000", "Combo Deseo", etc.</p></div>
        @if (auth()->user()->isAdministrador())
            <a class="btn" href="{{ route('admin.combos.create') }}">+ Nuevo combo</a>
        @endif
    </div>
    <div class="card">
        <table>
            <thead><tr><th>Combo</th><th>Incluye</th><th>Precio</th><th>Stock</th><th>Estado</th>@if(auth()->user()->isAdministrador())<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($combos as $combo)
                    <tr>
                        <td>{{ $combo->name }}</td>
                        <td style="font-size:12.5px; color:#999;">
                            @forelse ($combo->items as $item)
                                {{ $item->isChoice() ? $item->label() : $item->quantity.'x '.$item->product->name }}@if (! $loop->last), @endif
                            @empty
                                <span style="color:#666;">sin productos aún</span>
                            @endforelse
                        </td>
                        <td>${{ number_format($combo->price, 0, ',', '.') }}</td>
                        <td>
                            @php $avail = $combo->availableCount(); @endphp
                            @if (is_null($avail))
                                <span class="pill">SIN CONTROL</span>
                            @elseif ($avail <= 0)
                                <span class="pill pill-bad">AGOTADO</span>
                            @else
                                <span class="pill">{{ $avail }}</span>
                            @endif
                        </td>
                        <td><span class="pill {{ $combo->is_active ? '' : 'pill-warn' }}">{{ $combo->is_active ? 'ACTIVO' : 'PAUSADO' }}</span></td>
                        @if (auth()->user()->isAdministrador())
                            <td style="white-space:nowrap;">
                                <form method="POST" action="{{ route('admin.combos.toggle', $combo) }}" class="inline" style="margin-right:10px;">
                                    @csrf
                                    <button type="submit" style="background:#2a2a2a; border:1px solid #444; color:#ccc; border-radius:6px; font-size:11.5px; padding:5px 10px; cursor:pointer;">{{ $combo->is_active ? 'Pausar' : 'Activar' }}</button>
                                </form>
                                <a class="link" href="{{ route('admin.combos.edit', $combo) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.combos.destroy', $combo) }}" class="inline" style="margin-left:8px;" onsubmit="return confirm('¿Eliminar este combo?');">
                                    @csrf @method('DELETE')
                                    <button class="link" type="submit" style="background:none;border:0;padding:0;cursor:pointer;">Eliminar</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:#666;">Todavía no hay combos creados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
