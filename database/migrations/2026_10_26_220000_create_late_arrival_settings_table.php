<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regla de atrasos al check-in, editable desde Administración (fila
     * única, id=1): hasta cuántos minutos de atraso el contador parte de cero,
     * y qué se descuenta cuando se pasa de esa tolerancia.
     */
    public function up(): void
    {
        Schema::create('late_arrival_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tolerance_minutes')->default(15);
            // 'exceso': se descuenta solo lo que pasa de la tolerancia.
            // 'todo': pasada la tolerancia se descuenta el atraso completo.
            $table->string('mode', 20)->default('exceso');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_arrival_settings');
    }
};
