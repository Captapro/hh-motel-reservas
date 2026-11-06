<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contactos que el bot de WhatsApp traspasó a una persona y esperan respuesta de recepción. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attention_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('name')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('attended_at')->nullable()->index();
            $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attention_requests');
    }
};
