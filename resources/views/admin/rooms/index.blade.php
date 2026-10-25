@extends('admin.layout')
@section('title', 'Playrooms')
@section('content')
    <div class="page-head">
        <div><h1>Playrooms <span class="pill">{{ $rooms->count() }}</span></h1><p class="sub">Inventario real, agrupado por categoría.</p></div>
        <div class="actions">
            <button type="button" class="btn secondary" onclick="navigator.clipboard.writeText('{{ route('catalog.index') }}'); this.textContent='¡Copiado!'; setTimeout(() => this.textContent='Copiar link del catálogo', 1500);">Copiar link del catálogo</button>
            <a class="btn secondary" href="{{ route('catalog.index') }}" target="_blank" rel="noopener">Ver catálogo ↗</a>
            <a class="btn secondary" href="{{ route('admin.rooms.qr_sheet') }}">QR de habitaciones →</a>
            <a class="btn" href="{{ route('admin.rooms.create') }}">+ Nuevo Playroom</a>
        </div>
    </div>

    @php
        $byCategory = $rooms->sortBy(fn ($r) => [$r->category->display_order, $r->name])->groupBy(fn ($r) => $r->category->name);
    @endphp
    <div class="masonry">
        @foreach ($byCategory as $categoryName => $group)
            <div class="card">
                <div class="cols-h"><h2>{{ $categoryName }}</h2><span class="pill">{{ $group->count() }}</span></div>
                <table class="tbl-compact">
                    <tbody>
                        @foreach ($group as $room)
                            <tr>
                                <td><b>{{ $room->name }}</b></td>
                                <td style="color:#999; font-size:12px;">{{ $room->buffer_minutes }} min</td>
                                <td><span class="pill {{ $room->operational_status === 'activa' ? '' : 'pill-warn' }}">{{ strtoupper($room->operational_status) }}</span></td>
                                <td>
                                    @if ($room->photos)
                                        <a href="{{ route('admin.rooms.edit', $room) }}" class="hh-room-thumb-link" title="{{ count($room->photos) }} foto(s)">
                                            <img src="{{ $room->photos[0] }}" alt="" class="hh-room-thumb">
                                            <span>{{ count($room->photos) }}</span>
                                        </a>
                                    @else
                                        <span class="sub" style="font-size:12px;">sin fotos</span>
                                    @endif
                                </td>
                                <td style="text-align:right;"><a class="link" href="{{ route('admin.rooms.edit', $room) }}">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
@endsection
