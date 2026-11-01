@extends('admin.layout')
@section('title', 'Combos')
@section('content')
    <div class="actions" style="justify-content:space-between; margin-bottom:18px;">
        <div><h1>Combos</h1><p class="sub">Paquetes de productos a un precio especial — "2 cervezas por $5.000", "Combo Deseo", etc.</p></div>
        @if (auth()->user()->isAdministrador())
            <a class="btn" href="{{ route('admin.combos.create') }}">+ Nuevo combo</a>
        @endif
    </div>
    @php
        $monthName = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][now('America/Santiago')->month - 1];
        $ranked = $combos->filter(fn ($c) => $c->sold_month > 0)->sortByDesc('sold_month');
    @endphp
    <div class="card" style="margin-bottom:16px;">
        <h2 style="margin:0 0 10px;">Packs vendidos</h2>
        <div style="display:flex; gap:28px; flex-wrap:wrap; font-size:13px; color:#999;">
            <div><div style="font-size:26px; font-weight:700;">{{ (int) $combos->sum('sold_today') }}</div>hoy</div>
            <div><div style="font-size:26px; font-weight:700;">{{ (int) $combos->sum('sold_month') }}</div>en {{ $monthName }}</div>
            <div><div style="font-size:26px; font-weight:700;">${{ number_format((int) $combos->sum('amount_month'), 0, ',', '.') }}</div>vendido en {{ $monthName }}</div>
            <div><div style="font-size:26px; font-weight:700;">{{ (int) $combos->sum('sold_total') }}</div>desde el inicio</div>
        </div>
        @if ($ranked->isNotEmpty())
            <div style="margin-top:12px; font-size:13px; color:#888;">Más vendido en {{ $monthName }}: <b>{{ $ranked->first()->name }}</b> ({{ (int) $ranked->first()->sold_month }})</div>
        @endif
    </div>
    <div class="card">
        <table>
            <thead><tr><th>Combo</th><th>Incluye</th><th>Precio</th><th title="Packs vendidos (sin reservas canceladas ni expiradas)">Vendidos<br><span style="font-weight:400; font-size:11px;">hoy · mes · total</span></th><th>Stock</th><th>Estado</th><th>Catálogo</th>@if(auth()->user()->isAdministrador())<th></th>@endif</tr></thead>
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
                        <td style="white-space:nowrap;">
                            <b>{{ (int) $combo->sold_today }}</b> · <b>{{ (int) $combo->sold_month }}</b> · {{ (int) $combo->sold_total }}
                            @if ($combo->amount_month)
                                <div style="font-size:11.5px; color:#888;">${{ number_format((int) $combo->amount_month, 0, ',', '.') }} este mes</div>
                            @endif
                        </td>
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
                        <td style="white-space:nowrap;">
                            @if (auth()->user()->isAdministrador())
                                <form method="POST" action="{{ route('admin.combos.catalog', $combo) }}" class="inline" title="{{ $combo->show_in_catalog ? 'Se ofrece al cliente al reservar en línea. Toca para ocultarlo.' : 'No se muestra en el catálogo. Toca para ofrecerlo en línea.' }}">
                                    @csrf
                                    <button type="submit" style="border-radius:20px; font-size:11px; font-weight:700; padding:4px 10px; cursor:pointer; {{ $combo->show_in_catalog ? 'background:#e7f8ef; border:1px solid #6fd39a; color:#137a4a;' : 'background:transparent; border:1px solid #c9ccd1; color:#7a7f87;' }}">{{ $combo->show_in_catalog ? '● En catálogo' : '○ Oculto' }}</button>
                                </form>
                            @else
                                {{ $combo->show_in_catalog ? 'Sí' : 'No' }}
                            @endif
                        </td>
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
                    <tr><td colspan="8" style="color:#666;">Todavía no hay combos creados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
