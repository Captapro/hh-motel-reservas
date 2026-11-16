<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca qué consumos se vendieron como upsell (subir categoría, más tiempo, pack) para medir
     * cuántos se aceptan y cuánto suman. Sin llave foránea: si se borra el upsell, queda el registro.
     */
    public function up(): void
    {
        Schema::table('booking_addons', function (Blueprint $table) {
            $table->unsignedBigInteger('upsell_offer_id')->nullable()->index()->after('combo_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_addons', function (Blueprint $table) {
            $table->dropColumn('upsell_offer_id');
        });
    }
};
