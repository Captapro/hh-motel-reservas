<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_category_id')->constrained()->restrictOnDelete();
            $table->string('name'); // ej. "PLUS 206"
            $table->jsonb('photos')->nullable();
            $table->unsignedSmallInteger('buffer_minutes')->default(15); // tiempo de aseo entre reservas
            // 'aseo' y 'ocupada' se agregaron después vía migraciones separadas (ver
            // 2026_09_10_120000_add_aseo_to_rooms_operational_status), que
            // en Postgres reconstruye este mismo CHECK -- se incluye acá
            // desde el arranque para que una base nueva (tests en SQLite,
            // donde el enum se fija en el CREATE TABLE y no se puede alterar
            // después) tenga el valor disponible sin depender de esa migración.
            $table->enum('operational_status', ['activa', 'mantencion', 'inactiva', 'aseo', 'ocupada'])->default('activa');
            $table->text('operational_note')->nullable(); // motivo de mantención, etc.
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
