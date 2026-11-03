@extends('admin.layout')
@section('title', 'Integración GHL')
@section('content')
    <h1>Integración con GoHighLevel</h1>
    <p class="sub">Cada reserva nueva crea/actualiza el contacto en GHL y le pone un tag "Reservó:{fecha}" -- reemplaza el de la reserva anterior, no se acumulan. Prueba acá con un número tuyo antes de confiar en que la sincronización automática funciona.</p>

    <div class="card">
        @if ($configured)
            <p style="color:#6ee7b7; margin:0;">✓ Token y Location ID configurados.</p>
        @else
            <p style="color:#f3b8b8; margin:0;">✗ Falta configurar GHL_PRIVATE_TOKEN y/o GHL_LOCATION_ID en las variables de entorno -- ninguna reserva se está sincronizando todavía.</p>
        @endif
    </div>

    <div class="card">
        <h2 style="font-size:14px; margin:0 0 4px;">Medición: embudo de GHL y ventas a Meta</h2>
        <p class="sub" style="margin:0 0 10px;">Cada reserva con teléfono (desde el {{ \Illuminate\Support\Carbon::parse($funnel['since'])->format('d-m-Y') }}) queda como oportunidad en el embudo "{{ config('services.ghl.pipeline_name') }}" con su monto y su origen. Cada venta pagada se le avisa a Meta para medir qué anuncios venden.</p>
        <p style="margin:2px 0; color:#c1c3c7;">Embudo GHL: {{ $funnel['synced'] }} reservas subidas · {{ $funnel['pending'] }} por subir o mover de etapa{{ $funnel['lastRun'] ? ' · última pasada '.\Illuminate\Support\Carbon::parse($funnel['lastRun']['at'])->timezone('America/Santiago')->format('H:i:s') : '' }}</p>
        @if ($funnel['error'])
            <p style="margin:2px 0; color:#f3b8b8;">✗ {{ \Illuminate\Support\Carbon::parse($funnel['error']['at'])->timezone('America/Santiago')->format('d-m H:i') }}: {{ $funnel['error']['message'] }} (se reintenta en 10 minutos)</p>
        @endif
        <p style="margin:6px 0 2px; color:{{ $meta['pixel'] ? '#6ee7b7' : '#f3b8b8' }};">{{ $meta['pixel'] ? '✓ Píxel de Meta en el catálogo' : '✗ Sin píxel de Meta (META_PIXEL_ID)' }}</p>
        <p style="margin:2px 0; color:{{ $meta['capi'] ? '#6ee7b7' : '#f3b8b8' }};">{{ $meta['capi'] ? '✓ Ventas a Meta desde el servidor activas ('.$meta['sent'].' enviadas)' : '✗ Falta META_CAPI_TOKEN en Render: las ventas no se le avisan a Meta desde el servidor' }}</p>
        @if ($meta['last'])
            <p style="margin:2px 0; color:{{ $meta['last']['error'] ? '#f3b8b8' : '#c1c3c7' }};">Último aviso: {{ $meta['last']['booking'] }} a las {{ \Illuminate\Support\Carbon::parse($meta['last']['at'])->timezone('America/Santiago')->format('d-m H:i') }} — {{ $meta['last']['error'] ? 'Meta respondió '.$meta['last']['status'].': '.$meta['last']['error'] : 'recibido' }}</p>
        @endif
    </div>

    <div class="card">
        <h2 style="font-size:14px; margin:0 0 4px;">Pedidos de opinión</h2>
        <p class="sub" style="margin:0 0 10px;">15 minutos después del check-out el sistema avisa a GHL para que mande el WhatsApp. Aquí se ve qué pasó con cada salida de las últimas 24 horas.</p>
        @foreach ($reviewWebhooks as $name => $set)
            <p style="margin:2px 0; color:{{ $set ? '#6ee7b7' : '#f3b8b8' }};">{{ $set ? '✓' : '✗' }} {{ $name }} {{ $set ? 'configurado' : 'vacío: no se envía nada' }}</p>
        @endforeach
        @if ($jobLastRun && $jobLastRun->gt(now()->subMinutes(3)))
            <p style="margin:6px 0 2px; color:#6ee7b7;">✓ Proceso automático corriendo (última vuelta {{ $jobLastRun->timezone('America/Santiago')->format('H:i:s') }})</p>
        @else
            <p style="margin:6px 0 2px; color:#f3b8b8;">✗ El proceso automático no ha corrido en los últimos 3 minutos{{ $jobLastRun ? ' (última vuelta '.$jobLastRun->timezone('America/Santiago')->format('d-m H:i').')' : '' }}.</p>
        @endif
        @foreach ($jobErrors as $step => $err)
            <p style="margin:2px 0; color:#f3b8b8;">✗ Paso "{{ $step }}" falló a las {{ \Illuminate\Support\Carbon::parse($err['at'])->timezone('America/Santiago')->format('d-m H:i') }}: {{ $err['message'] }}</p>
        @endforeach
        @if ($lastReviewSent)
            <p style="margin:6px 0 2px; color:#c1c3c7;">Último pedido enviado: {{ $lastReviewSent['booking'] }} a las {{ \Illuminate\Support\Carbon::parse($lastReviewSent['at'])->timezone('America/Santiago')->format('d-m H:i') }} ({{ $lastReviewSent['result'] }})</p>
        @endif

        <table style="width:100%; margin-top:10px; font-size:13px; border-collapse:collapse;">
            <thead><tr style="text-align:left; color:#8b8e94;"><th>Salida</th><th>Reserva</th><th>Playroom</th><th>Teléfono</th><th>Resultado</th></tr></thead>
            <tbody>
                @forelse ($recentCheckouts as $row)
                    <tr style="border-top:1px solid #2a2c31;">
                        <td>{{ $row['booking']->checked_out_at->timezone('America/Santiago')->format('d-m H:i') }}</td>
                        <td><a href="{{ route('reservations.show', $row['booking']->code) }}">{{ $row['booking']->code }}</a></td>
                        <td>{{ $row['booking']->room?->name }}</td>
                        <td>{{ $row['has_phone'] ? 'Sí' : 'No' }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:#8b8e94; padding-top:6px;">Sin salidas en las últimas 24 horas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:14px; margin:0 0 4px;">Probar conexión</h2>
        <p class="sub" style="margin:0 0 10px;">Usa un número que sea tuyo (o de alguien avisado) -- esto crea/edita de verdad un contacto en la cuenta real de GHL.</p>
        <form method="POST" action="{{ route('admin.ghl.test') }}">
            @csrf
            <label for="name">Nombre de prueba</label>
            <input type="text" id="name" name="name" value="{{ old('name', 'Prueba GHL') }}" required>
            <label for="phone">Teléfono</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone', $testedPhone ?? '') }}" placeholder="+56 9 1234 5678" required>
            <button class="btn" type="submit" style="margin-top:14px;">Probar ahora →</button>
        </form>
    </div>

    @if ($errors->any())
        <div class="card" style="border-color:#7a2d2d; background:#2a1414;">
            @foreach ($errors->all() as $error)
                <p style="color:#f3b8b8; margin:4px 0;">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @isset($log)
        <div class="card">
            <h2 style="font-size:14px; margin:0 0 10px;">Resultado</h2>
            @foreach ($log as $line)
                <p style="margin:4px 0; font-family:ui-monospace,monospace; font-size:12.5px; color:#c1c3c7;">{{ $line }}</p>
            @endforeach
            @if ($error)
                <p style="margin:10px 0 0; color:#f3b8b8;">✗ Error: {{ $error }}</p>
            @else
                <p style="margin:10px 0 0; color:#6ee7b7;">✓ Listo -- revisa el contacto en GHL para confirmar que se ve como esperas.</p>
            @endif
        </div>
    @endisset
@endsection
