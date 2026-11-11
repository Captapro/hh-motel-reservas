<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_ad_origins', function (Blueprint $table) {
            // Id del clic en el anuncio de WhatsApp (lo guarda GHL en el contacto): con él Meta
            // sabe a qué anuncio atribuirle la venta de esa conversación.
            $table->string('ctwa_clid', 600)->nullable();
            $table->timestamp('wa_purchase_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_ad_origins', function (Blueprint $table) {
            $table->dropColumn(['ctwa_clid', 'wa_purchase_sent_at']);
        });
    }
};
