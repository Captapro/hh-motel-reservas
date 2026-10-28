<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ventas anteriores al sistema (planillas de recepción). No son reservas:
        // no tocan caja, ventas del mes ni Analytics; solo las lee el analista y
        // la pantalla de repetición de clientes.
        Schema::create('historical_sales', function (Blueprint $table) {
            $table->id();
            $table->string('source', 60);          // p. ej. "planilla-2026-10"
            $table->unsignedInteger('source_row'); // N° de registro dentro de la planilla
            $table->date('sold_on');
            $table->string('sale_type', 30);       // ALOJAMIENTO, EXTRAS, EXTRA CORE, PAGO FRACCIONADO
            $table->string('doc_type', 20)->nullable();   // Voucher / Boleta
            $table->string('voucher_number', 30)->nullable();
            $table->string('receipt_number', 30)->nullable();
            $table->string('detail', 255)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('room_label', 20)->nullable();
            $table->string('category', 30)->nullable();
            $table->unsignedSmallInteger('guests_count')->nullable();
            $table->unsignedSmallInteger('hours_paid')->nullable();
            $table->string('starts_time', 8)->nullable();
            $table->string('ends_time', 8)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('discount_note', 120)->nullable();
            $table->unsignedInteger('amount_main')->default(0);   // alojamiento + extras core
            $table->unsignedInteger('amount_extras')->default(0); // extras
            $table->string('payment_method', 30)->nullable();
            $table->string('reservation_ref', 60)->nullable();
            $table->text('notes')->nullable();
            $table->string('shift', 20)->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_row']);
            $table->index('sold_on');
        });

        // Personas de cada estadía (titular y acompañantes). El RUT normalizado
        // es la llave para medir cuántas veces vuelve cada persona.
        Schema::create('historical_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_sale_id')->constrained('historical_sales')->cascadeOnDelete();
            $table->string('name', 160)->nullable();
            $table->string('document', 30)->nullable();       // RUT normalizado "12345678-9" u otro documento
            $table->string('document_kind', 20)->nullable();   // rut, rut_invalido, otro
            $table->string('nationality', 60)->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->boolean('is_holder')->default(false);
            $table->timestamps();

            $table->index('document');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_guests');
        Schema::dropIfExists('historical_sales');
    }
};
