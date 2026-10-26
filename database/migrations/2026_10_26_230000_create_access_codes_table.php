<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Claves de chapas digitales. Una fila por habitación (room_id) o por
        // recinto (bodega, sala de máquinas...). La clave y la nota se guardan cifradas.
        Schema::create('access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('kind', 20)->default('recinto');
            $table->string('location')->nullable();
            $table->text('code');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_codes');
    }
};
