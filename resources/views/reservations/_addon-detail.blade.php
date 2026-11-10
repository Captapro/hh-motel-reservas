{{-- Qué incluye un consumo/pack y si ya salió del inventario — para que recepción sepa qué entregar. --}}
@php
    $lines = $addon->contentLines();
    $toDeliver = $addon->stock_deducted_at === null && ! $booking->checked_in_at && ($addon->product_id || $addon->combo_id);
@endphp
@if ($lines || $toDeliver)
    <div style="margin:2px 0 12px; padding:9px 12px; background:#262626; border-left:3px solid #ff7918; border-radius:0 8px 8px 0;">
        @if ($lines)
            <div style="font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:#999; margin-bottom:5px;">Incluye</div>
            <ul style="margin:0; padding:0; list-style:none;">
                @foreach ($lines as $line)
                    <li style="font-size:14.5px; font-weight:600; line-height:1.7; color:#f3f3f3;">{{ $line }}</li>
                @endforeach
            </ul>
        @endif
        @if ($toDeliver)
            <div style="margin-top:8px; font-size:12.5px; font-weight:600; color:#f0c75a;">📦 Por entregar en el check-in — aún no se descuenta del inventario</div>
        @endif
    </div>
@endif
