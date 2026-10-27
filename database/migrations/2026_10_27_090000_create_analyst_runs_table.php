<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada pregunta al analista de ventas (IA) y su respuesta. "thread"
        // agrupa una conversación para que las preguntas de seguimiento
        // tengan el contexto de las anteriores.
        Schema::create('analyst_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('thread')->index();
            $table->text('question');
            $table->string('status', 20)->default('pendiente'); // pendiente | trabajando | listo | error
            $table->longText('answer')->nullable();
            $table->text('error')->nullable();
            $table->json('steps')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyst_runs');
    }
};
