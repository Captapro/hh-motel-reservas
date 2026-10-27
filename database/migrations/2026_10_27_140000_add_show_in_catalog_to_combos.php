<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada combo decide por sí mismo si se ofrece al cliente en el catálogo
     * (antes había que crear un upsell de tipo pack). Los que hoy ya se ven en
     * el catálogo (con un upsell de pack activo) quedan marcados.
     */
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->boolean('show_in_catalog')->default(false);
        });

        $shown = DB::table('upsell_offers')->where('type', 'combo')->where('is_active', true)->whereNotNull('combo_id')->pluck('combo_id');
        DB::table('combos')->whereIn('id', $shown)->update(['show_in_catalog' => true]);
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn('show_in_catalog');
        });
    }
};
