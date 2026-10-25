<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->enum('status', ['pendiente', 'en_proceso', 'aprobado', 'rechazado', 'reembolsado'])->default('pendiente');
            $table->string('external_id')->nullable(); // ID de pago de Mercado Pago u otro externo
            $table->jsonb('raw_payload')->nullable(); // notificación cruda del webhook, para auditoría
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('status');
        });

        // Idempotencia: una notificación de Mercado Pago repetida nunca crea un pago duplicado.
        DB::statement('
            CREATE UNIQUE INDEX payments_external_id_unique
            ON payments (external_id)
            WHERE external_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
