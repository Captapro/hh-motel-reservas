<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asistente de ayuda para anfitriones: cada pregunta queda guardada
        // para que el administrador vea en qué se traba el equipo.
        Schema::create('help_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 30)->nullable();
            $table->string('page', 120)->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('listo'); // listo | error
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
        });

        // Fila única (id=1): reglas de la casa que escribe el administrador.
        Schema::create('help_settings', function (Blueprint $table) {
            $table->id();
            $table->text('house_rules')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_settings');
        Schema::dropIfExists('help_questions');
    }
};
