<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué diapositivas salen en el TV y en qué orden: [{key, on}] con key
     * 'combo:{id}', 'reserve', 'review' o 'message:{n}'. Null = orden automático.
     */
    public function up(): void
    {
        Schema::table('tv_screen_settings', function (Blueprint $table) {
            $table->json('playlist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tv_screen_settings', function (Blueprint $table) {
            $table->dropColumn('playlist');
        });
    }
};
