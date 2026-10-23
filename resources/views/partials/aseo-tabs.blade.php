@php
    $tabs = [
        ['aseo.daily', 'aseo.*', 'Aseo del día'],
        ['shift_round.index', 'shift_round.*', 'Ronda de turno'],
        ['mucamas.activity', 'mucamas.activity*', 'Mucamas en turno'],
        ['rooms.inspections.panel', 'rooms.inspections.*', 'Mantención'],
    ];
@endphp
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px;">
    @foreach ($tabs as [$destination, $pattern, $label])
        @php $on = request()->routeIs($pattern); @endphp
        <a href="{{ route($destination) }}" style="padding:7px 14px;border-radius:7px;font-size:13px;text-decoration:none;{{ $on ? 'background:#ff7918;color:#111;font-weight:600;' : 'background:#1c1c1c;border:1px solid #333;color:#eee;' }}">{{ $label }}</a>
    @endforeach
</div>
