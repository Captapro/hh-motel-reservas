@extends('admin.layout')
@section('title', $customer->name)
@section('content')
    <style>
        .seg-banner { border-radius:10px; padding:16px 18px; margin-bottom:18px; }
        .seg-banner.nuevo { background:#16232b; border:1px solid #2c4a5e; }
        .seg-banner.frecuente { background:#1c2f1c; border:1px solid #2e5a2e; }
        .seg-banner.ocasional { background:#2a2410; border:1px solid #6b5a1e; }
        .seg-banner.esporadico { background:#3a1c22; border:1px solid #7a2d2d; }
        .seg-banner .seg-label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; opacity:.8; }
        .seg-banner .seg-title { font-size:16px; font-weight:700; margin-bottom:6px; }
        .seg-banner .seg-why { font-size:13px; color:#ccc; line-height:1.5; }
        .seg-stats { display:flex; gap:20px; margin-top:12px; flex-wrap:wrap; }
        .seg-stats div span { display:block; font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.04em; }
        .seg-stats div b { font-family: ui-monospace, monospace; font-size:17px; }
        .row { display:flex; justify-content:space-between; padding: 7px 0; font-size: 14.5px; gap:12px; }
        .row .muted { color:#999; flex:none; }
        .sec-label { font-size: 11px; color:#888; text-transform:uppercase; letter-spacing:.05em; margin: 22px 0 10px; }
        .sec-label:first-of-type { margin-top:0; }
        a.row-card { display:block; background:#1c1c1c; border:1px solid #333; border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; text-decoration:none; color:#eee; }
        a.row-card.now { border-color:#ff7918; background:#241419; }
        a.row-card:hover { border-color:#666; }
        .row1 { display:flex; justify-content:space-between; align-items:baseline; }
        .code { font-family: ui-monospace, monospace; color:#ff7918; font-size: 12.5px; }
        .when { font-weight:600; font-size:14.5px; }
        .meta { color:#999; font-size:13px; margin-top:4px; }
        .client-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:14px 16px; }
        .client-fields input, .client-fields select { width:100%; box-sizing:border-box; }
        .danger-zone { border:1px solid #5a2327; background:#1d1315; }
        .empty { color:#777; font-size:14px; padding: 4px 0 20px; }
    </style>

    <p class="sub" style="margin-bottom:6px;">
        <a class="link" href="#" onclick="if (window.history.length > 1) { history.back(); return false; }">← Volver</a>
        · <a class="link" href="{{ route('admin.customers.index') }}">Todos los clientes</a>
    </p>
    <h1>{{ $customer->name }}</h1>
    <p class="sub">
        {{ $customer->phone_e164 }}{{ $customer->email ? ' · '.$customer->email : '' }}
        @if ($customer->documentNumber())
            · {{ $customer->document_type === 'pasaporte' ? 'Pasaporte' : 'RUT' }} {{ $customer->documentNumber() }}
        @endif
        {{ $customer->nationality ? ' · '.$customer->nationality : '' }}
        {{ $customer->age() !== null ? ' · '.$customer->age().' años' : '' }}
    </p>

    <div class="card" style="margin-bottom:18px;border:1px solid {{ $customer->blacklist_status === 'blocked' ? '#a33' : ($customer->blacklist_status === 'warning' ? '#92751e' : '#333') }};">
        <strong>Lista negra</strong>
        <p class="sub" style="margin:5px 0 10px;">Estado actual: <b>{{ $customer->blacklist_status === 'none' ? 'Sin restricción' : strtoupper($customer->blacklist_status) }}</b></p>
        @if($customer->blacklist_reason)<p class="sub" style="margin:0 0 10px;">Motivo: {{ $customer->blacklist_reason }}</p>@endif
        {{-- Bloquear o desbloquear a un cliente es decisión del administrador. --}}
        @if (auth()->user()?->isAdministrador())
        <form method="POST" action="{{ route('admin.customers.blacklist', $customer) }}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
            @csrf @method('PUT')
            <div><label>Estado</label><select name="blacklist_status"><option value="none" @selected($customer->blacklist_status === 'none')>Sin restricción</option><option value="warning" @selected($customer->blacklist_status === 'warning')>Advertencia</option><option value="blocked" @selected($customer->blacklist_status === 'blocked')>Bloqueado</option></select></div>
            <div style="flex:1;min-width:220px;"><label>Motivo</label><input name="blacklist_reason" value="{{ $customer->blacklist_reason }}" placeholder="Motivo interno"></div>
            <button class="btn" type="submit">Guardar estado</button>
        </form>
        @else
            <p class="sub" style="margin:0;">Solo el administrador puede cambiar este estado.</p>
        @endif
    </div>

    @if (auth()->user()->isAdministrador())
        <div class="card" style="margin-bottom:18px;">
            <h2>En las planillas históricas</h2>
            @if ($historical)
                <p class="sub" style="margin:0 0 8px;">Estadías anteriores al sistema que calzan con el RUT {{ $customer->rut }}. Se suman a lo que ves abajo, que es solo lo registrado en el sistema.</p>
                <div class="client-stats" style="display:flex; gap:22px; flex-wrap:wrap;">
                    <div><span style="display:block;color:#888;font-size:12px;">Estadías</span><b>{{ $historical['visits'] }}</b></div>
                    <div><span style="display:block;color:#888;font-size:12px;">Como titular / acompañante</span><b>{{ $historical['as_holder'] }} / {{ $historical['as_companion'] }}</b></div>
                    <div><span style="display:block;color:#888;font-size:12px;">Gastó como titular</span><b>${{ number_format($historical['spent'], 0, ',', '.') }}</b></div>
                    <div><span style="display:block;color:#888;font-size:12px;">Primera / última</span><b>{{ $historical['first']->format('d/m/Y') }} / {{ $historical['last']->format('d/m/Y') }}</b></div>
                    <div><span style="display:block;color:#888;font-size:12px;">Meses en que vino</span><b style="text-transform:capitalize;">{{ collect($historical['months'])->map(fn ($m) => \Carbon\Carbon::parse($m.'-01')->locale('es')->isoFormat('MMM YY'))->implode(', ') }}</b></div>
                </div>
                <p class="sub" style="margin:10px 0 0;">Gastó = solo el alojamiento de las estadías en que fue titular (sin hora extra ni extras aparte).</p>
            @elseif (filled($customer->rut))
                <p class="sub" style="margin:0;">No aparece en las planillas cargadas con el RUT {{ $customer->rut }}.</p>
            @else
                <p class="sub" style="margin:0;">Este cliente no tiene RUT cargado, así que no se puede cruzar con las planillas (ellas no traen teléfono). Agrégalo en «Editar datos del cliente» y se cruza solo.</p>
            @endif
        </div>
    @endif

    <details style="margin-bottom:18px;">
        <summary class="link" style="cursor:pointer;">Editar datos del cliente</summary>
        <div class="card" style="margin-top:10px;">
            <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
                @csrf @method('PUT')
                <div class="client-fields">
                    <div>
                        <label>Nombre</label>
                        <input name="name" value="{{ $customer->name }}" maxlength="150" required>
                    </div>
                    <div>
                        <label>Correo</label>
                        <input type="email" name="email" value="{{ $customer->email }}" maxlength="255" placeholder="cliente@correo.cl">
                    </div>
                    <div>
                        <label>Tipo de documento</label>
                        <select name="document_type">
                            <option value="">— Sin definir</option>
                            <option value="rut" @selected($customer->document_type === 'rut')>RUT</option>
                            <option value="pasaporte" @selected($customer->document_type === 'pasaporte')>Pasaporte</option>
                        </select>
                    </div>
                    <div>
                        <label>RUT</label>
                        <input name="rut" value="{{ $customer->rut }}" maxlength="20" oninput="var c=this.value.toUpperCase().replace(/[^0-9K]/g,'');if(/^[0-9.\-kK\s]*$/.test(this.value))this.value=c.length>1?c.slice(0,-1)+'-'+c.slice(-1):c;">
                    </div>
                    <div>
                        <label>N° de pasaporte</label>
                        <input name="passport_number" value="{{ $customer->passport_number }}" maxlength="30">
                    </div>
                    <div>
                        <label>Nacionalidad</label>
                        <input name="nationality" value="{{ $customer->nationality }}" maxlength="60">
                    </div>
                    <div>
                        <label>Fecha de nacimiento</label>
                        <input type="date" name="birth_date" value="{{ $customer->birth_date?->toDateString() }}">
                    </div>
                </div>
                <label style="display:flex;gap:8px;align-items:center;margin-top:12px;font-weight:400;">
                    <input type="checkbox" name="marketing_opt_in" value="1" @checked($customer->marketing_opt_in) style="width:auto;margin:0;">
                    Acepta recibir promociones (WhatsApp y correo)@if ($customer->marketing_opt_in_at) <span class="sub" style="margin:0;">— desde {{ $customer->marketing_opt_in_at->timezone('America/Santiago')->format('d/m/Y') }}</span>@endif
                </label>
                <p class="sub" style="margin:10px 0 0;">El teléfono no se edita acá — es la identidad del cliente. Al guardar, se sincroniza el correo/nombre con GHL de inmediato.</p>
                <div class="actions" style="margin-top:12px;"><button class="btn" type="submit">Guardar cambios</button></div>
            </form>
        </div>
    </details>

    @if ($monthlyVisits >= 3)
        <div style="background:#211d10; border:1px solid #7a6a1e; color:#f0d98a; border-radius:10px; padding:12px 16px; margin-bottom:14px; font-weight:600; font-size:14px;">
            ⭐ Recurrente del mes — {{ $monthlyVisits }} reservas en {{ ucfirst(now()->locale('es')->isoFormat('MMMM')) }}
        </div>
    @endif

    <div class="seg-banner {{ $segment['type'] }}">
        <div class="seg-label">Segmento histórico</div>
        <div class="seg-title">{{ $segment['label'] }}</div>
        <div class="seg-why">
            @switch($segment['type'])
                @case('nuevo')
                    Todavía no acumula estadías previas — es su primera vez o la próxima será su primera vez completada.
                    @break
                @case('frecuente')
                    Vino {{ $segment['total_stays'] }} veces, en promedio cada {{ $segment['avg_interval_days'] }} días, y su última visita fue hace {{ $segment['days_since_last'] }} días. Buen candidato para beneficios de fidelización.
                    @break
                @case('ocasional')
                    Vino {{ $segment['total_stays'] }} veces, con un promedio de {{ $segment['avg_interval_days'] }} días entre visitas. Viene, pero sin un patrón muy seguido todavía.
                    @break
                @case('esporadico')
                    Tiene {{ $segment['total_stays'] }} estadías en su historial, pero la última fue hace {{ $segment['days_since_last'] }} días — se desconectó. Podría valer la pena reactivarlo con una promoción.
                    @break
            @endswitch
        </div>
        <div class="seg-stats">
            <div><span>Estadías</span><b>{{ $segment['total_stays'] }}</b></div>
            <div><span>Última visita</span><b>{{ $segment['last_visit_at']?->timezone('America/Santiago')->format('d/m/Y') ?? '—' }}</b></div>
            @if ($segment['avg_interval_days'])
                <div><span>Cada</span><b>{{ $segment['avg_interval_days'] }}d</b></div>
            @endif
        </div>
    </div>

    <div class="sec-label">Hoy y próximas</div>
    @forelse ($upcoming as $booking)
        @php $isNow = $booking->starts_at <= $now && $booking->ends_at > $now; @endphp
        <a class="row-card {{ $isNow ? 'now' : '' }}" href="{{ route('reservations.show', $booking->code) }}">
            <div class="row1">
                <span class="when">{{ ucfirst($booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('dddd D/MM, H:mm')) }}</span>
                <span class="pill">{{ $isNow ? 'OCUPANDO AHORA' : $booking->booking_status }}</span>
            </div>
            <div class="meta">{{ $booking->room->name }} ({{ $booking->room->category->name }}) · {{ $booking->duration_minutes / 60 }} h · <span class="code">{{ $booking->code }}</span></div>
        </a>
    @empty
        <p class="empty">No tiene reservas próximas.</p>
    @endforelse

    @if ($past->isNotEmpty())
        <div class="sec-label">Historial</div>
        @foreach ($past as $booking)
            <a class="row-card" href="{{ route('reservations.show', $booking->code) }}" style="opacity:.7;">
                <div class="row1">
                    <span class="when">{{ ucfirst($booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('dddd D/MM, H:mm')) }}</span>
                    <span class="pill">{{ $booking->booking_status }}</span>
                </div>
                <div class="meta">{{ $booking->room->name }} ({{ $booking->room->category->name }}) · {{ $booking->duration_minutes / 60 }} h · <span class="code">{{ $booking->code }}</span></div>
            </a>
        @endforeach
    @endif

    @php
        $bookingCount = $customer->bookings()->count();
        $blockers = $bookingCount > 0 ? $customer->historyBlockers() : [];
    @endphp
    @if ($errors->has('customer'))
        <div class="card" style="border:1px solid #a33;margin-top:22px;"><p style="margin:0;color:#ff9a9a;">{{ $errors->first('customer') }}</p></div>
    @endif
    @if (auth()->user()?->isAdministrador())
        <div class="card danger-zone" style="margin-top:22px;">
            <strong>Eliminar cliente</strong>
            @if ($bookingCount === 0)
                <p class="sub" style="margin:6px 0 12px;">Este cliente nunca reservó, así que se puede eliminar. No se borra de GHL.</p>
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('¿Eliminar a {{ e($customer->name) }}? Esta acción no se puede deshacer.');">
                    @csrf @method('DELETE')
                    <button class="btn" type="submit" style="background:#b3202f;color:#fff;">Eliminar cliente</button>
                </form>
            @elseif (empty($blockers))
                <p class="sub" style="margin:6px 0 12px;">Tiene {{ $bookingCount }} reserva(s), pero ninguna con pagos, consumos, cupones ni estadía realizada (por ejemplo, pruebas o canceladas). Se pueden eliminar junto con el cliente. No se borra de GHL.</p>
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('¿Eliminar a {{ e($customer->name) }} y sus {{ $bookingCount }} reserva(s)? Esta acción no se puede deshacer.');">
                    @csrf @method('DELETE')
                    <input type="hidden" name="with_bookings" value="1">
                    <button class="btn" type="submit" style="background:#b3202f;color:#fff;">Eliminar cliente y sus reservas</button>
                </form>
            @elseif ($customer->canForceDelete())
                <p class="sub" style="margin:6px 0 12px;">Tiene {{ implode(', ', $blockers) }}, pero ninguna de sus reservas llegó a ocurrir (todas están canceladas o vencidas). Si son <b>datos de prueba</b>, puedes borrarlo junto con esos pagos. Esto también descuenta esos montos de la caja y de las ventas, y <b>no se puede deshacer</b>.</p>
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('Se borrará a {{ e($customer->name) }}, sus {{ $bookingCount }} reserva(s) y sus pagos. ¿Seguro?');">
                    @csrf @method('DELETE')
                    <input type="hidden" name="force" value="1">
                    <label style="display:block;font-size:12.5px;color:#bbb;margin-bottom:6px;">Para confirmar, escribe <b>ELIMINAR</b></label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input name="confirm_text" autocomplete="off" placeholder="ELIMINAR" style="max-width:200px;">
                        <button class="btn" type="submit" style="background:#b3202f;color:#fff;">Eliminar cliente y su historial de prueba</button>
                    </div>
                </form>
            @else
                <p class="sub" style="margin:6px 0 0;">No se puede eliminar: tiene {{ implode(', ', $blockers) }} y alguna de sus reservas sí ocurrió o sigue vigente. Es historial real y no se borra. Si no quieres que vuelva a reservar, usa <b>Lista negra</b> arriba.</p>
            @endif
        </div>
    @endif
@endsection
