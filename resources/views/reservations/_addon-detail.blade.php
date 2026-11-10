{{-- Qué incluye un consumo/pack y si ya salió del inventario — para que recepción sepa qué entregar. --}}
@php
    $lines = $addon->contentLines();
    $toDeliver = $addon->stock_deducted_at === null && ! $booking->checked_in_at && ($addon->product_id || $addon->combo_id);
@endphp
@if ($lines || $toDeliver)
    <div style="margin:-2px 0 8px 18px; font-size:12px; line-height:1.45; color:#9aa;">
        @if ($lines)
            <div>Incluye: {{ implode(' · ', $lines) }}</div>
        @endif
        @if ($toDeliver)
            <div style="color:#e8c76f;">📦 Por entregar en el check-in — aún no se descuenta del inventario</div>
        @endif
    </div>
@endif
