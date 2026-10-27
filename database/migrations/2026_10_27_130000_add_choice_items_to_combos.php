<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combos "a elección" (ej. "2 bebidas a elegir entre Coca-Cola, Sprite y
     * Fanta", o "2 a elegir de la categoría Bebidas"): un componente puede ser
     * una elección entre varios productos, o entre todos los de una categoría
     * (los nuevos de esa categoría entran solos), en vez de un producto fijo. Cada venta guarda qué productos descontó
     * realmente (components), para devolver al inventario los correctos.
     */
    public function up(): void
    {
        Schema::table('combo_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            $table->json('choice_product_ids')->nullable();
            $table->string('choice_category')->nullable();
        });

        Schema::table('booking_addons', function (Blueprint $table) {
            $table->json('components')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_addons', function (Blueprint $table) {
            $table->dropColumn('components');
        });
        Schema::table('combo_items', function (Blueprint $table) {
            $table->dropColumn(['choice_product_ids', 'choice_category']);
        });
    }
};
