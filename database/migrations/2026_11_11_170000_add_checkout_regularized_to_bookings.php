<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salida cargada DESPUÉS del hecho (horas corregidas por el administrador o venta de planilla):
        // el cliente ya se fue y la pieza ya se limpió, así que no corresponde dejarla como aseo pendiente.
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('checkout_regularized')->default(false);
        });

        // Las que ya se regularizaron antes de este cambio.
        if (Schema::hasTable('audit_logs')) {
            $ids = DB::table('audit_logs')
                ->where('entity_type', 'Booking')
                ->whereIn('action', ['reserva.corregir_horas', 'reserva.crear_desde_planilla'])
                ->pluck('entity_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique();
            foreach ($ids->chunk(500) as $chunk) {
                DB::table('bookings')->whereIn('id', $chunk->all())->update(['checkout_regularized' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checkout_regularized');
        });
    }
};
