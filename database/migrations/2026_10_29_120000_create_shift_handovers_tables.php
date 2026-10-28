<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entrega de turno del anfitrión: al entrar cuenta la caja chica y el
        // stock y acepta el turno ("recibir"); al salir hace lo mismo
        // ("entregar"). Lo esperado se guarda junto a lo contado para que la
        // diferencia quede fija aunque después cambien las ventas.
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_shift_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // recibir | entregar
            $table->integer('expected_cash');
            $table->integer('counted_cash');
            $table->boolean('has_differences')->default(false);
            $table->text('notes')->nullable();
            // El administrador marca revisadas las diferencias; mientras no,
            // le aparece el aviso en la barra de arriba.
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shift_handover_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_handover_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 120);
            $table->integer('expected_stock');
            $table->integer('counted_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handover_items');
        Schema::dropIfExists('shift_handovers');
    }
};
