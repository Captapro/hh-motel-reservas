<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programación de la pantalla del TV (/pantalla), editable desde
     * Administración → Pantalla TV (fila única, id=1).
     */
    public function up(): void
    {
        Schema::create('tv_screen_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('show_combos')->default(true);
            $table->boolean('show_combo_contents')->default(true);
            $table->string('combo_cta', 120)->default('Agrégalo a tu estadía en recepción');
            $table->boolean('show_reserve_qr')->default(true);
            $table->boolean('show_review_qr')->default(true);
            $table->unsignedSmallInteger('combo_seconds')->default(10);
            $table->unsignedSmallInteger('qr_seconds')->default(15);
            // Diapositivas propias: [{title, text, active}].
            $table->json('messages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_screen_settings');
    }
};
