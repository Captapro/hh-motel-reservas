<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tramos de descuento editables en Admin. Vacío = los tramos por defecto. */
    public function up(): void
    {
        Schema::table('short_stay_settings', function (Blueprint $table) {
            $table->json('tiers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('short_stay_settings', function (Blueprint $table) {
            $table->dropColumn('tiers');
        });
    }
};
