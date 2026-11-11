<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De qué anuncio llegó el cliente de cada reserva, leído de GHL por su teléfono
        // (GHL guarda el anuncio de Meta desde el que escribió por WhatsApp).
        Schema::create('booking_ad_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('meta_ad_id', 40)->nullable();
            $table->string('meta_ad_name', 160)->nullable();
            // 'Paid Social' = vino de un anuncio; 'Social media' = escribió por su cuenta.
            $table->string('wa_source', 40)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->boolean('contact_found')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_ad_origins');
    }
};
