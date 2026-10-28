<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Cuándo aceptó el aviso de privacidad al reservar en línea.
            $table->timestamp('privacy_accepted_at')->nullable();
            // Permiso aparte (opcional) para recibir promociones por WhatsApp o correo.
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('marketing_opt_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['privacy_accepted_at', 'marketing_opt_in', 'marketing_opt_in_at']);
        });
    }
};
