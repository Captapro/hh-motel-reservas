<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_addons', function (Blueprint $table) {
            // Cuándo se descontó del inventario lo que trae este consumo.
            // Null = aún no se entrega (se descuenta al hacer el check-in).
            $table->timestamp('stock_deducted_at')->nullable()->after('components');
        });

        // Todo lo anterior a este cambio ya se había descontado al venderlo.
        DB::table('booking_addons')->whereNull('stock_deducted_at')->update(['stock_deducted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('booking_addons', function (Blueprint $table) {
            $table->dropColumn('stock_deducted_at');
        });
    }
};
