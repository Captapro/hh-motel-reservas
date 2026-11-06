<?php

use App\Models\OperationalSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversaciones parte de cero: lo anterior a este momento no cuenta como
     * por atender. No toca nada en GHL.
     */
    public function up(): void
    {
        Schema::table('operational_settings', function (Blueprint $table) {
            $table->timestamp('conversations_since')->nullable();
        });

        OperationalSetting::current()->update(['conversations_since' => now()]);
    }

    public function down(): void
    {
        Schema::table('operational_settings', function (Blueprint $table) {
            $table->dropColumn('conversations_since');
        });
    }
};
