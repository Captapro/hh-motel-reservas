{{-- Cliente que va a transferir: se le mandan los datos y recepción sigue sin esperar el pago. --}}
@php
    $pay = config('services.hh_payment');
    $hasBank = $pay['bank_titular'] && $pay['bank_account_number'];
    $phone = preg_replace('/[^0-9]/', '', (string) $booking->customer?->phone_e164);
@endphp
@if ($balance > 0 && $hasBank)
    @php
        $tz = 'America/Santiago';
        $lines = array_filter([
            'Titular: '.$pay['bank_titular'],
            $pay['bank_rut'] ? 'RUT: '.$pay['bank_rut'] : null,
            $pay['bank_name'] ? 'Banco: '.$pay['bank_name'] : null,
            trim(($pay['bank_account_type'] ?: 'Cuenta').': '.$pay['bank_account_number']),
            $pay['bank_email'] ? 'Correo: '.$pay['bank_email'] : null,
        ]);
        $message = 'Hola '.$booking->customer?->name.", tu reserva en HH Motel está creada.\n\n"
            .'Código: '.$booking->code."\n"
            .'Playroom: '.$booking->room->name."\n"
            .'Horario: '.$booking->starts_at->timezone($tz)->format('d/m H:i').' a '.$booking->ends_at->timezone($tz)->format('H:i')."\n"
            .'Monto a transferir: $'.number_format($balance, 0, ',', '.')."\n\n"
            ."Datos para transferencia:\n".implode("\n", $lines)
            ."\n\nEnvíanos el comprobante por este mismo WhatsApp.";
    @endphp
    <div id="transfer-box" hidden style="margin:14px 0 4px; padding:14px; border:1px solid #4b4b4b; border-radius:10px; background:#1f1f1f;">
        <div style="font-size:14px; font-weight:800; color:#fff; margin-bottom:4px;">¿Aún no transfiere?</div>
        <div style="font-size:13px; color:#c9c9c9; margin-bottom:10px; white-space:pre-line;">{{ implode("\n", $lines) }}</div>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <button type="button" id="transfer-copy" data-message="{{ $message }}" style="min-height:40px; padding:0 14px; border:1px solid #6b6b6b; border-radius:8px; background:#2a2a2a; color:#fff; font-size:13px; font-weight:700; cursor:pointer;">Copiar datos para transferencia</button>
            @if ($phone)
                <a href="https://wa.me/{{ $phone }}?text={{ rawurlencode($message) }}" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; min-height:40px; padding:0 14px; border:1px solid #ff7918; border-radius:8px; color:#ff9a4d; font-size:13px; font-weight:700; text-decoration:none;">Enviar por WhatsApp</a>
            @endif
            <button type="submit" form="transfer-sent-form" style="min-height:40px; padding:0 14px; border:none; border-radius:8px; background:#ff7918; color:#21170e; font-size:13px; font-weight:800; cursor:pointer;">Datos enviados, avanzar →</button>
        </div>
        <div style="font-size:12px; color:#a8a8a8; margin-top:8px;">Al avanzar, el Playroom queda tomado hasta registrar el pago. Si ya llegó la plata, regístrala aquí abajo.</div>
    </div>
    <script>
        document.getElementById('transfer-copy').addEventListener('click', function () {
            const button = this;
            navigator.clipboard.writeText(button.dataset.message).then(function () {
                button.textContent = 'Copiado ✓';
                setTimeout(function () { button.textContent = 'Copiar datos para transferencia'; }, 1800);
            }).catch(function () { button.textContent = 'No se pudo copiar'; });
        });
    </script>
@endif
