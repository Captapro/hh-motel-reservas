@extends('admin.layout')
@section('title', 'Cambiar de pieza')
@section('content')
    @php $money = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <style>
        .rc-box { max-width: 720px; }
        .rc-info { display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:10px; margin-bottom:14px; }
        .rc-info .k { color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
        .rc-info .v { font-size:16px; font-weight:700; margin-top:3px; }
        .rc-note { background:rgba(255,121,24,.10); border:1px solid rgba(255,121,24,.4); border-radius:10px; padding:11px 14px; margin-bottom:14px; font-size:13.5px; line-height:1.5; }
        .rc-err { background:rgba(232,90,110,.12); border:1px solid rgba(232,90,110,.5); color:#e8788a; border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:14px; }
    </style>

    <div class="page-head">
        <div>
            <h1>Cambiar de pieza · {{ $booking->code }}</h1>
            <p class="sub">{{ $booking->customer?->name }} · hoy en {{ $booking->room->name }} · {{ $booking->starts_at->timezone('America/Santiago')->format('d/m H:i') }}–{{ $booking->ends_at->timezone('America/Santiago')->format('H:i') }}</p>
        </div>
        <a class="btn secondary" href="{{ route('reservations.show', $booking->code) }}">← Volver a la reserva</a>
    </div>

    <div class="rc-box">
        @if ($errors->any())
            <div class="rc-err">@foreach ($errors->all() as $e) {{ $e }}<br>@endforeach</div>
        @endif

        <div class="rc-note">
            Se mueve la reserva a otra pieza <strong>sin recalcular lo contratado</strong>. Si la pieza nueva cuesta más, la diferencia queda como un cobro propio
            («Diferencia por cambio de pieza») y al guardar te lleva a cobrarla, con su medio de pago y su N° de voucher, en esta misma reserva.
        </div>

        <div class="card">
            <div class="rc-info">
                <div><div class="k">Precio contratado</div><div class="v">{{ $money($booking->price_final) }}</div></div>
                <div><div class="k">Pagado</div><div class="v">{{ $money($booking->payments->where('status', 'aprobado')->sum('amount')) }}</div></div>
                <div><div class="k">Personas</div><div class="v">{{ $booking->guests_count }}</div></div>
            </div>

            <form method="POST" action="{{ route('reservations.change_room.store', $booking->code) }}">
                @csrf
                <label for="room_id">Pieza nueva</label>
                <select name="room_id" id="room_id" required>
                    <option value="">— elige una pieza —</option>
                    @foreach ($options as $o)
                        <option value="{{ $o['room']->id }}" data-suggested="{{ $o['suggested'] ?? '' }}" @disabled($o['problem']) @selected((string) old('room_id') === (string) $o['room']->id)>
                            {{ $o['room']->name }} ({{ $o['room']->category->name }})
                            @if ($o['problem']) — no disponible: {{ $o['problem'] }}
                            @elseif ($o['suggested'] === null) — sin tarifa para calcular
                            @else — diferencia sugerida {{ $money($o['suggested']) }}
                            @endif
                        </option>
                    @endforeach
                </select>

                <label for="amount">Diferencia a cobrar ($)</label>
                <input type="number" min="0" max="500000" step="1" name="amount" id="amount" value="{{ old('amount') }}" @unless ($canEditAmount) readonly @endunless placeholder="Se completa al elegir la pieza">
                @unless ($canEditAmount)<div class="sub" style="margin-top:4px">La diferencia la calcula el sistema. Solo un administrador puede cambiarla.</div>@endunless

                <label for="reason">Motivo del cambio</label>
                <textarea name="reason" id="reason" rows="2" maxlength="300" required placeholder="Ej.: el cliente pidió una pieza más grande en el check-in">{{ old('reason') }}</textarea>

                <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button class="btn" type="submit">Cambiar de pieza</button>
                    <a class="btn secondary" href="{{ route('reservations.show', $booking->code) }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var sel = document.getElementById('room_id'), amount = document.getElementById('amount');
            sel.addEventListener('change', function () {
                var s = sel.options[sel.selectedIndex].getAttribute('data-suggested');
                amount.value = (s === null || s === '') ? '' : s;
            });
            if (sel.value && !amount.value) { sel.dispatchEvent(new Event('change')); }
        })();
    </script>
@endsection
