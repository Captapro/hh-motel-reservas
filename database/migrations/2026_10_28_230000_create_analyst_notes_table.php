<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos cortos del negocio (un costo, una meta, un precio de la competencia…) que el
        // analista lee en cada pregunta. Reemplazan la caja grande de «contexto» con plantilla.
        Schema::create('analyst_notes', function (Blueprint $table) {
            $table->id();
            $table->string('body', 300);
            $table->string('source', 12)->default('manual'); // manual | analista
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyst_notes');
    }
};
