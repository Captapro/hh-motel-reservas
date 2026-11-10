<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificadores del clic de Google Ads de cada reserva del catálogo (gclid; gbraid y
 * wbraid en iOS). Permiten subir después las reservas reales como conversiones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_data', function (Blueprint $table) {
            $table->string('gclid', 500)->nullable()->after('fbclid');
            $table->string('gbraid', 500)->nullable()->after('gclid');
            $table->string('wbraid', 500)->nullable()->after('gbraid');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_data', function (Blueprint $table) {
            $table->dropColumn(['gclid', 'gbraid', 'wbraid']);
        });
    }
};
