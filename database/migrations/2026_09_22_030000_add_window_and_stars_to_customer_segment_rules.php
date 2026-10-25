<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_segment_rules', function (Blueprint $table) {
            $table->unsignedInteger('analysis_window_days')->nullable()->after('minimum_stays');
            $table->unsignedTinyInteger('stars')->default(0)->after('analysis_window_days');
        });
        DB::table('customer_segment_rules')->where('segment', 'frecuente')->update(['minimum_stays' => 3, 'analysis_window_days' => 45, 'stars' => 3, 'max_avg_interval_days' => null]);
        DB::table('customer_segment_rules')->where('segment', 'ocasional')->update(['minimum_stays' => 1, 'analysis_window_days' => 90, 'stars' => 1]);
        DB::table('customer_segment_rules')->where('segment', 'esporadico')->update(['minimum_stays' => 0, 'analysis_window_days' => 120, 'stars' => 0, 'stale_after_days' => 120]);
    }

    public function down(): void
    {
        Schema::table('customer_segment_rules', function (Blueprint $table) {
            $table->dropColumn(['analysis_window_days', 'stars']);
        });
    }
};
