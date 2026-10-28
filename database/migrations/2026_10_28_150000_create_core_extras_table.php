<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Listado de "extra core": lo adicional de la habitación que el cliente
        // pide (hora extra, persona adicional, toalla adicional…). Los consumos
        // (preservativos, bebidas) son extras y van en Productos. Empieza vacío.
        Schema::create('core_extras', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedInteger('price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_extras');
    }
};
