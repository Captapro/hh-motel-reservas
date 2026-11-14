{{-- Detalle de piezas a lavar por Playroom. Recibe $pieces (AseoReportService::piecesByRoom). --}}
@php $pieceNames = array_keys(\App\Services\Booking\AseoReportService::SET_PIECES); @endphp
{{-- Resumen total: cuántas piezas de cada tipo van a lavandería en el período. --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin:12px 0 6px;">
    @foreach ($pieceNames as $name)
        <div style="background:#111;border:1px solid #333;border-radius:8px;padding:10px 12px;">
            <div style="font-size:10.5px;color:#999;text-transform:uppercase;letter-spacing:.04em;">{{ $name }}</div>
            <div style="font-size:20px;font-weight:600;font-family:ui-monospace,monospace;color:#7fbcdc;">{{ $pieces->sum(fn ($e) => $e['pieces'][$name]) }}</div>
        </div>
    @endforeach
    <div style="background:#111;border:1px solid #333;border-radius:8px;padding:10px 12px;">
        <div style="font-size:10.5px;color:#999;text-transform:uppercase;letter-spacing:.04em;">Toallas</div>
        <div style="font-size:20px;font-weight:600;font-family:ui-monospace,monospace;color:#7fbcdc;">{{ $pieces->sum('towels') }}</div>
    </div>
    <div style="background:#1c1420;border:1px solid #ff7918;border-radius:8px;padding:10px 12px;">
        <div style="font-size:10.5px;color:#ff7918;text-transform:uppercase;letter-spacing:.04em;">Total piezas</div>
        <div style="font-size:20px;font-weight:600;font-family:ui-monospace,monospace;color:#ff7918;">{{ $pieces->sum('total') }}</div>
    </div>
</div>
<p class="hint" style="margin:0 0 4px;">{{ $pieces->sum('sets') }} {{ $pieces->sum('sets') === 1 ? 'juego de cama' : 'juegos de cama' }} en {{ $pieces->sum('exits') }} {{ $pieces->sum('exits') === 1 ? 'salida' : 'salidas' }}. Abajo, el detalle por Playroom.</p>
<div style="overflow-x:auto;">
<table class="linen-table" style="max-width:860px;">
    <thead>
        <tr>
            <th>Playroom</th><th class="n">Salidas</th><th class="n">Juegos</th>
            @foreach ($pieceNames as $name)<th class="n">{{ $name }}</th>@endforeach
            <th class="n">Toallas</th><th class="n">Total piezas</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pieces as $entry)
            <tr>
                <td>{{ $entry['room']->name }}</td>
                <td class="n">{{ $entry['exits'] }}</td>
                <td class="n">{{ $entry['sets'] ?: 'Sin cama' }}</td>
                @foreach ($pieceNames as $name)<td class="n">{{ $entry['pieces'][$name] ?: '—' }}</td>@endforeach
                <td class="n">{{ $entry['towels'] }}</td>
                <td class="n"><b>{{ $entry['total'] }}</b></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="n">{{ $pieces->sum('exits') }}</td>
            <td class="n">{{ $pieces->sum('sets') }}</td>
            @foreach ($pieceNames as $name)<td class="n">{{ $pieces->sum(fn ($e) => $e['pieces'][$name]) }}</td>@endforeach
            <td class="n">{{ $pieces->sum('towels') }}</td>
            <td class="n">{{ $pieces->sum('total') }}</td>
        </tr>
    </tfoot>
</table>
</div>
