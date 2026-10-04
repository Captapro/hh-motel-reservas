<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Máximo real de personas por tipo, informado por HH Motel (mínimo 2 en todos).
    private const MAX_BY_CATEGORY = ['GO' => 6, 'LITE' => 4, 'NEW LITE' => 4, 'PLUS' => 8, 'MAX' => 12];

    public function up(): void
    {
        Schema::table('room_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_capacity')->default(10)->after('base_capacity');
        });

        foreach (self::MAX_BY_CATEGORY as $name => $max) {
            DB::table('room_categories')->where('name', $name)->update(['max_capacity' => $max]);
        }
    }

    public function down(): void
    {
        Schema::table('room_categories', function (Blueprint $table) {
            $table->dropColumn('max_capacity');
        });
    }
};
