<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto del documento que pide un cupón con verificación (ej. credencial
        // de estudiante), tomada en el check-in. Comprimida, en base64 como las
        // fotos de inspección, y se borra sola a los 30 días.
        Schema::create('coupon_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mime', 30);
            $table->longText('data');
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_proofs');
    }
};
