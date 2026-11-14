{{-- Detalle de piezas a lavar por Playroom. Recibe $pieces (AseoReportService::piecesByRoom). --}}
@php $pieceNames = array_keys(\App\Services\Booking\AseoReportService::SET_PIECES); @endphp
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
