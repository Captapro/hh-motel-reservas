<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fotos de inspección dentro de la base de datos (comprimidas), para
        // no depender del disco del servidor: sin disco, Render publica sin cortes.
        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_inspection_id')->constrained()->cascadeOnDelete();
            $table->string('mime', 30);
            // En base64: PDO de Postgres no manda bien bytes crudos como parámetro.
            $table->longText('data');
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_photos');
    }
};
