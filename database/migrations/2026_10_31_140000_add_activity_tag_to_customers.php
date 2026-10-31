<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tramo de actividad que ya se reflejó en GHL (hh_pausa, hh_inactivo_30...):
        // así solo se llama a GHL cuando el cliente cambia de tramo.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('activity_tag', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('activity_tag');
        });
    }
};
