@extends('admin.layout')
@section('title', 'Actividad y accesos')
@section('content')
    @php
        $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $tz = 'America/Santiago';
        $fmt = fn ($d) => $d ? $d->timezone($tz)->format('d/m H:i') : '—';
        $dur = function ($log) {
            $m = $log->durationMinutes();

            return intdiv($m, 60).' h '.str_pad($m % 60, 2, '0', STR_PAD_LEFT).' min';
        };
        $sensitiveLabels = \App\Support\ActivityLabels::SENSITIVE;
    @endphp
    <h1>Actividad y accesos</h1>
    <p class="sub">Quién está en turno, el historial de turnos con su entrega y sus cobros, quién entra al sistema y qué acciones se registran. Solo soporte. Todo en hora de Chile.</p>

    <form method="GET" action="{{ route('admin.activity.index') }}" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <div><label>Desde</label><input type="date" name="from" value="{{ $from }}"></div>
        <div><label>Hasta</label><input type="date" name="to" value="{{ $to }}"></div>
        <div>
            <label>Persona</label>
            <select name="user">
                <option value="">Todas</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Tipo de acción</label>
            <select name="group">
                <option value="">Todas</option>
                @foreach (\App\Support\ActivityLabels::GROUPS as $key => [$name])
                    <option value="{{ $key }}" @selected($group === $key)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Ver</button>
    </form>

    <div class="card">
        <h3 style="margin:0 0 8px;">En turno ahora</h3>
        @forelse ($open as $log)
            <div style="padding:5px 0;border-bottom:1px solid #262626;"><b>{{ $log->who()?->name ?? '—' }}</b> · {{ $log->staff_id ? 'Mucama' : 'Cuenta' }} · entró {{ $fmt($log->started_at) }} ({{ $dur($log) }})</div>
        @empty
            <p class="sub" style="margin:0;">Nadie con el turno abierto.</p>
        @endforelse
    </div>

    <div class="card">
        <h3 style="margin:0 0 8px;">Acciones delicadas por persona</h3>
        <p class="sub" style="margin:0 0 8px;">Claves vistas o cambiadas, devoluciones, reservas eliminadas, correcciones de venta, cambios de cuentas y accesos.</p>
        @forelse ($sensitive as $uid => $counts)
            <div style="padding:6px 0;border-bottom:1px solid #262626;">
                <b>{{ $uid ? ($userNames[$uid] ?? 'Usuario #'.$uid) : 'Sin cuenta (sistema o mucama)' }}</b>:
                @foreach ($counts as $action => $n)
                    <span class="pill">{{ \App\Support\ActivityLabels::label($action) }} × {{ $n }}</span>
                @endforeach
            </div>
        @empty
            <p class="sub" style="margin:0;">Sin acciones delicadas en el rango.</p>
        @endforelse
    </div>

    <div class="card">
        <h3 style="margin:0 0 8px;">Historial de turnos</h3>
        <p class="sub" style="margin:0 0 8px;">Cada entrada al sistema abre un turno y «Salir» lo cierra. Se muestra lo que contó al recibir y entregar, y lo que cobró dentro del turno (pagos registrados con su cuenta).</p>
        @if ($shiftRows->isEmpty())
            <p class="sub" style="margin:0;">Sin turnos en el rango.</p>
        @else
            <table>
                <thead><tr><th>Persona</th><th>Perfil</th><th>Entró</th><th>Salió</th><th>Duración</th><th>Entrega de turno</th><th style="text-align:right;">Cobros</th></tr></thead>
                <tbody>
                @foreach ($shiftRows as $r)
                    <tr>
                        <td>{{ $r['who'] }}</td>
                        <td>{{ $r['kind'] }}</td>
                        <td>{{ $fmt($r['log']->started_at) }}</td>
                        <td>{{ $r['log']->ended_at ? $fmt($r['log']->ended_at) : 'Abierto' }}</td>
                        <td>{{ $dur($r['log']) }}</td>
                        <td>
                            @forelse ($r['handovers'] as $h)
                                <span class="pill">{{ $h->kind === 'recibir' ? 'Recibió' : 'Entregó' }}</span>
                                @if (! $h->has_differences) <span style="color:#8fe0ad;">cuadró</span>
                                @elseif ($h->reviewed_at) diferencia revisada
                                @else <a href="{{ route('cash.shifts') }}" style="color:#e8c76f;">con diferencias</a>
                                @endif
                            @empty
                                <span class="sub">—</span>
                            @endforelse
                        </td>
                        <td style="text-align:right;">{{ $r['payments_count'] ? $r['payments_count'].' · '.$money($r['payments_sum']) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3 style="margin:0 0 8px;">Entradas al sistema</h3>
        <p class="sub" style="margin:0 0 8px;">
            Se registra desde que se publicó esta pantalla. @if ($failedLogins > 0)<b style="color:#e8c76f;">{{ $failedLogins }} {{ $failedLogins === 1 ? 'intento fallido' : 'intentos fallidos' }} en el rango.</b>@else Sin intentos fallidos en el rango.@endif
        </p>
        @if ($logins->isEmpty())
            <p class="sub" style="margin:0;">Sin movimientos en el rango.</p>
        @else
            <table>
                <thead><tr><th>Cuándo</th><th>Persona</th><th>Qué pasó</th><th>Dispositivo</th><th>IP</th></tr></thead>
                <tbody>
                @foreach ($logins as $e)
                    <tr>
                        <td>{{ $fmt($e->created_at) }}</td>
                        <td>{{ $e->user?->name ?? ($e->email ?: '—') }}</td>
                        <td>@if (in_array($e->result, ['fallido', 'desactivada'], true))<span style="color:#e8c76f;">{{ \App\Models\LoginEvent::RESULT_LABELS[$e->result] }}</span>@else{{ \App\Models\LoginEvent::RESULT_LABELS[$e->result] ?? $e->result }}@endif</td>
                        <td>{{ $e->device() }}</td>
                        <td>{{ $e->ip ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3 style="margin:0 0 8px;">Acciones registradas</h3>
        <p class="sub" style="margin:0 0 8px;">Lo que cada persona hizo en el sistema, de lo más nuevo a lo más antiguo @if ($logsLimited)(se muestran las últimas 200; acota las fechas o elige a una persona para ver más)@endif.</p>
        @if ($logs->isEmpty())
            <p class="sub" style="margin:0;">Sin acciones registradas en el rango.</p>
        @else
            <table>
                <thead><tr><th>Cuándo</th><th>Persona</th><th>Acción</th><th>Sobre</th><th>Detalle</th></tr></thead>
                <tbody>
                @foreach ($logs as $l)
                    @php
                        $sensitiveRow = in_array($l->action, $sensitiveLabels, true);
                        $on = match ($l->entity_type) {
                            'Booking' => isset($bookingCodes[$l->entity_id]) ? $bookingCodes[$l->entity_id] : 'Reserva #'.$l->entity_id,
                            'Room' => $roomNames[$l->entity_id] ?? 'Pieza #'.$l->entity_id,
                            default => $l->entity_type,
                        };
                    @endphp
                    <tr>
                        <td>{{ $fmt($l->created_at) }}</td>
                        <td>{{ $l->user?->name ?? ($l->user_id ? 'Usuario #'.$l->user_id : 'Sistema o mucama') }}</td>
                        <td @if ($sensitiveRow) style="color:#e8c76f;" @endif>{{ \App\Support\ActivityLabels::label($l->action) }}</td>
                        <td>@if ($l->entity_type === 'Booking' && isset($bookingCodes[$l->entity_id]))<a class="link" href="{{ route('reservations.show', $bookingCodes[$l->entity_id]) }}">{{ $on }}</a>@else{{ $on }}@endif</td>
                        <td class="sub">{{ \App\Support\ActivityLabels::summary($l) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
