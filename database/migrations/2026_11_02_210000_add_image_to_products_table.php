<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto del producto (idealmente PNG sin fondo) para la pantalla del TV.
     * Va en su propia tabla, reducida, para no depender del disco del servidor
     * (se borra en cada publicación) y para que las listas de productos no
     * carguen la imagen; en products solo queda cuándo cambió (para el caché).
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mime', 30);
            $table->longText('data');
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('image_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_updated_at');
        });
        Schema::dropIfExists('product_images');
    }
};
