<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila única (id=1): el contexto del negocio que el administrador le
        // escribe al analista de ventas (objetivos, costos, competencia…).
        Schema::create('analyst_settings', function (Blueprint $table) {
            $table->id();
            $table->text('business_context')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyst_settings');
    }
};
