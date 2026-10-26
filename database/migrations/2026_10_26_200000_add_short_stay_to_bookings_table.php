<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estadía corta por cierre: quedaba menos tiempo para el cierre que la
     * duración mínima vendida, y recepción la vendió con precio proporcional
     * y salida a la hora de cierre (ends_at < starts_at + duration_minutes).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('short_stay')->default(false)->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('short_stay');
        });
    }
};
