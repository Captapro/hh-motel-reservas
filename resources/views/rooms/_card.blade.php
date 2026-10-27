@php $room = $entry['room']; $status = $entry['status']; $offer = ($roomOffers ?? [])[$room->id] ?? null; $needsMaintenance = $room->latestInspection && $room->latestInspection->needs_maintenance; @endphp
@php $cardTarget = ($status['current_booking'] ?? null) ? route('reservations.show', $status['current_booking']->code) : route('rooms.bookings', $room); @endphp
<div class="card cat-{{ Str::slug($room->category->name) }} {{ $needsMaintenance ? 'needs-maintenance' : '' }} {{ (($status['eta_minutes'] ?? 0) < 0 && ($status['current_booking'] ?? null)) ? 'overdue-room' : '' }} {{ $status['upcoming_count'] > 0 ? 'has-upcoming' : '' }}" data-wing="{{ $room->wing ?? 'none' }}" data-href="{{ $cardTarget }}">
    <div class="card-head">
        <b>{{ $room->name }}</b>
        @if ($offer)
            <span class="offer-badge" title="{{ $offer['label'] }}">{{ $offer['tag'] }}</span>
        @endif
        @if ($needsMaintenance)
            <span class="maintenance-badge" title="{{ $room->latestInspection->notes }}">Mantención</span>
        @endif
    </div>
    <div class="cat"><span class="cat-label">Categoría </span>{{ $room->category->name }}</div>
    <span class="brk b1"></span><span class="brk b2"></span>

    @if ($room->operational_status === 'aseo')
        <span class="pill pill-aseo">EN ASEO</span>
    @elseif ($room->operational_status !== 'activa')
        <span class="pill pill-{{ $room->operational_status }}">{{ strtoupper($room->operational_status) }}</span>
    @elseif ($status['occupancy'] === 'ocupada' && $status['current_booking'] && ! $status['current_booking']->checked_in_at)
        <span class="pill pill-reservada">RESERVADA — SIN CHECK-IN</span>
    @elseif ($status['occupancy'] === 'ocupada')
        <span class="pill pill-ocupada">OCUPADA</span>
    @else
        <span class="pill pill-libre">LIBRE</span>
    @endif

    @if ($room->operational_status === 'aseo')
        @if ($room->aseo_reported_at)
            <div class="eta" style="color:#34d399;">✓ {{ $room->aseo_reported_by }} reportó aseo listo a las {{ $room->aseo_reported_at->timezone('America/Santiago')->format('H:i') }} — falta tu confirmación</div>
        @else
            <div class="eta">Esperando aseo — no vuelve a disponibles hasta que recepción la marque</div>
        @endif
        @if (($cleaningStaff ?? collect())->isNotEmpty())
            @php $reportedBy = $room->aseo_reported_at && $cleaningStaff->contains($room->aseo_reported_by) ? $room->aseo_reported_by : null; @endphp
            <form method="POST" action="{{ route('rooms.aseo_ready', $room) }}" class="aseo-ready-form">
                @csrf
                @if ($reportedBy)
                    {{-- La mucama ya se identificó con su PIN al reportar: solo se confirma. --}}
                    <button type="submit" class="aseo-ready-btn">Confirmar aseo de {{ $reportedBy }} — reactivar</button>
                @else
                    <select name="cleaned_by" class="aseo-cleaner-input" required>
                        <option value="" selected disabled>¿Quién hizo el aseo?</option>
                        @foreach ($cleaningStaff as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="aseo-ready-btn">Marcar aseo listo — reactivar</button>
                @endif
            </form>
        @else
            <div class="eta aseo-help" style="color:#e88a9a;">No hay mucamas activas cargadas en <a href="{{ route('admin.staff.index') }}" style="color:#e88a9a;">Personal</a> — agrega al menos una para poder marcar el aseo.</div>
        @endif
    @elseif ($room->operational_status !== 'activa')
        @if ($room->operational_note)
            <div class="eta">{{ $room->operational_note }}</div>
        @endif
    @else
        @if ($status['occupancy'] === 'ocupada' && $status['current_booking'])
            @if ($status['eta_minutes'] < 0)
                <div class="eta overtime">Pasó su horario hace {{ \App\Support\TimeFormat::minutes($status['eta_minutes']) }}</div>
            @else
                <div class="eta">Libera en {{ \App\Support\TimeFormat::minutes($status['eta_minutes']) }}</div>
            @endif
            @if ($status['current_booking']->short_stay)
                <div class="eta" style="color:#ffcf6e;">⏱ Estadía corta · sale al cierre, {{ $status['current_booking']->ends_at->timezone('America/Santiago')->format('H:i') }}</div>
            @endif
            <div class="code">{{ $status['current_booking']->code }}</div>
            <a class="consumo-btn" href="{{ route('reservations.show', $status['current_booking']->code) }}#consumo">+ Agregar consumo →</a>
            @if ($status['current_booking']->checked_in_at)
                @if ($status['current_booking']->balanceDue() > 0)
                    <a class="checkout-btn pending-balance" href="{{ route('payments.create', ['code' => $status['current_booking']->code, 'after' => 'board']) }}">Falta cobrar ${{ number_format($status['current_booking']->balanceDue(), 0, ',', '.') }} →</a>
                @else
                    <a class="checkout-btn" href="{{ route('bookings.finalize.show', $status['current_booking']->code) }}">Finalizar / Check-out →</a>
                @endif
            @else
                <a class="checkin-btn" href="{{ route('bookings.checkin.show', $status['current_booking']->code) }}">Falta check-in — marcar ahora</a>
            @endif
        @elseif ($status['next_booking'])
            <div class="eta">Próxima: {{ $status['next_booking']->starts_at->timezone('America/Santiago')->format('H:i') }}</div>
        @else
            <div class="eta">Disponible ahora</div>
        @endif

        @if ($status['occupancy'] === 'libre')
            <a class="reserve-btn" href="{{ route('reservations.create', ['room_id' => $room->id, 'modo' => 'ingreso']) }}">Ingresar →</a>
            <a class="reserve-alt" href="{{ route('reservations.create', ['room_id' => $room->id]) }}">o reservar para más tarde</a>
        @endif
    @endif

    @if ($room->operational_status === 'activa' && $status['occupancy'] === 'ocupada' && $status['current_booking'])
        {{-- Solo se ve en la vista comprimida (chips): atajos sin entrar a la tarjeta completa. --}}
        <div class="chip-actions">
            <a href="{{ route('rooms.bookings', $room) }}" class="{{ $status['upcoming_count'] > 0 ? 'has' : 'none' }}" title="Reservas futuras de {{ $room->name }}">📅 {{ $status['upcoming_count'] > 0 ? 'Próximas · '.$status['upcoming_count'] : 'Sin próximas' }}</a>
            <a href="{{ route('reservations.show', $status['current_booking']->code) }}#consumo" class="plus" title="Agregar consumo a {{ $room->name }}">+ Consumo</a>
        </div>
    @endif

    @if ($status['upcoming_count'] > 0)
        <a class="bookings-btn has-bookings" href="{{ route('rooms.bookings', $room) }}" title="Reservas futuras de {{ $room->name }}">
            <span class="lg">{{ $status['upcoming_count'] }} {{ Str::plural('reserva', $status['upcoming_count']) }} →</span><span class="sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span><span class="sm">{{ $status['upcoming_count'] }}</span>
        </a>
    @else
        <a class="bookings-btn empty" href="{{ route('rooms.bookings', $room) }}" title="Sin reservas próximas: ver las reservas de {{ $room->name }}"><span class="lg">Sin reservas próximas</span><span class="sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span></a>
    @endif
    <button type="button" class="copy-link-btn" data-room-link="{{ route('catalog.room', $room) }}" onclick="hhCopyRoomLink(this)" title="Copiar el link de la ficha de {{ $room->name }}"><span class="lg">📋 Copiar link de ficha</span><span class="sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg></span></button>

    <details>
        <summary title="Cambiar estado de {{ $room->name }}"><span class="lg">Cambiar estado</span><span class="sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg></span></summary>
        <form method="POST" action="{{ route('rooms.status', $room) }}" onsubmit="return this.operational_status.value !== '__aseo' || confirm('¿Mandar {{ $room->name }} a aseo? Queda fuera de disponibles hasta que la marques como activa.');">
            @csrf
            <select name="operational_status">
                <option value="activa" @selected($room->operational_status === 'activa') @disabled($room->operational_status === 'aseo')>Activa{{ $room->operational_status === 'aseo' ? ' — primero marcar aseo listo' : '' }}</option>
                <option value="mantencion" @selected($room->operational_status === 'mantencion')>En mantención</option>
                <option value="inactiva" @selected($room->operational_status === 'inactiva')>Inactiva</option>
                @if ($room->operational_status === 'aseo')
                    <option value="aseo" selected>En aseo</option>
                @elseif ($status['occupancy'] === 'libre')
                    <option value="__aseo">Aseo</option>
                @endif
            </select>
            <button type="submit">Guardar</button>
        </form>
    </details>
</div>
