@if ($kind === 'reserve')
    <section class="slide" data-seconds="15">
        @if ($photos->isNotEmpty()) <div class="bg" style="background-image:url('{{ $photos->last() }}')"></div> @endif
        <div class="content">
            <div class="text">
                <span class="tag">Tu próxima visita</span>
                <h1>Reserva desde tu celular</h1>
                <p class="desc">Elige tu Playroom, el día y la hora, y llega directo. Escanea con la cámara.</p>
            </div>
            <div class="qr qr-big"><img src="{{ $reserveQr }}" alt="QR para reservar"></div>
        </div>
    </section>
@else
    <section class="slide" data-seconds="15">
        @if ($photos->isNotEmpty()) <div class="bg" style="background-image:url('{{ $photos->first() }}')"></div> @endif
        <div class="content">
            <div class="text">
                <span class="tag">¿Cómo te fue?</span>
                <h1>Danos tu opinión</h1>
                <p class="desc">Toma menos de un minuto y nos ayuda a mejorar cada Playroom. Escanea con la cámara.</p>
            </div>
            <div class="qr qr-big"><img src="{{ $reviewQr }}" alt="QR para dejar opinión"></div>
        </div>
    </section>
@endif
