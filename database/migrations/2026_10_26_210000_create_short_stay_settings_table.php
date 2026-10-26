<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reglas de la estadía corta por cierre, editables desde Administración
     * (fila única, id=1) en vez de estar fijas en el código.
     */
    public function up(): void
    {
        Schema::create('short_stay_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->boolean('reception_enabled')->default(true);
            $table->boolean('catalog_enabled')->default(true);
            // Se ofrece solo si para el cierre quedan entre min y max minutos.
            $table->unsignedSmallInteger('min_minutes')->default(30);
            $table->unsignedSmallInteger('max_minutes')->default(90);
            // 'proporcional': tiempo que queda / duración mínima, con piso en percent.
            // 'porcentaje': siempre percent del precio de la duración mínima.
            $table->string('pricing_mode', 20)->default('proporcional');
            $table->unsignedTinyInteger('percent')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_stay_settings');
    }
};
