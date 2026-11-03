<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medición de marketing: de dónde llegó cada reserva del catálogo (tracking_data
 * ya existía sin usarse), si ya se le avisó la venta a Meta y en qué quedó su
 * oportunidad en el embudo de GHL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_data', function (Blueprint $table) {
            $table->string('referrer', 500)->nullable()->after('utm_term');
            $table->string('landing_url', 500)->nullable()->after('referrer');
            $table->string('source_label', 120)->nullable()->after('landing_url');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('meta_purchase_sent_at')->nullable();
            $table->string('ghl_opportunity_id', 64)->nullable();
            $table->string('ghl_opportunity_state', 40)->nullable();
            $table->index('ghl_opportunity_state');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['ghl_opportunity_state']);
            $table->dropColumn(['meta_purchase_sent_at', 'ghl_opportunity_id', 'ghl_opportunity_state']);
        });
        Schema::table('tracking_data', function (Blueprint $table) {
            $table->dropColumn(['referrer', 'landing_url', 'source_label']);
        });
    }
};
