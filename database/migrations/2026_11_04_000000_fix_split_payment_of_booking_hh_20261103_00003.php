<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HH-20261103-00003 (204): el pago fraccionado de $5.000 de la estadía se
     * cargó como consumo porque Venta pasada acepta un solo pago. La estadía
     * vale $32.000 y el pago en efectivo se mantiene: solo sale el consumo.
     */
    public function up(): void
    {
        $booking = DB::table('bookings')->where('code', 'HH-20261103-00003')->first();
        $addon = $booking ? DB::table('booking_addons')->where('booking_id', $booking->id)
            ->where('description', 'Pago fraccionado estadía (efectivo)')->where('amount', 5000)->first() : null;
        if (! $addon || (int) $booking->price_final !== 27000) {
            return;
        }

        DB::transaction(function () use ($booking, $addon) {
            DB::table('booking_addons')->where('id', $addon->id)->delete();
            DB::table('bookings')->where('id', $booking->id)->update(['price_original' => 32000, 'price_final' => 32000]);
            DB::table('audit_logs')->insert([
                'action' => 'reserva.corregir_pago_fraccionado', 'entity_type' => 'Booking', 'entity_id' => (string) $booking->id,
                'old_value' => json_encode(['price_final' => 27000, 'consumo' => 'Pago fraccionado estadía (efectivo) $5.000']),
                'new_value' => json_encode(['price_final' => 32000]), 'created_at' => now(),
            ]);
        });
    }

    public function down(): void {}
};
