<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo se le avisó a Google Ads la venta de esta reserva (por el servidor, con su gclid),
 * para mandarla una sola vez aunque el cliente nunca vuelva a la página de confirmación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_data', function (Blueprint $table) {
            $table->timestamp('google_conversion_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_data', function (Blueprint $table) {
            $table->dropColumn('google_conversion_sent_at');
        });
    }
};
