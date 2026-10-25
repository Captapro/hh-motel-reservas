<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('blacklist_status')->default('none')->index();
            $table->text('blacklist_reason')->nullable();
            $table->timestamp('blacklisted_at')->nullable();
        });
        Schema::create('customer_segment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('segment', 30)->unique();
            $table->string('label', 80);
            $table->unsignedInteger('max_avg_interval_days')->nullable();
            $table->unsignedInteger('stale_after_days')->nullable();
            $table->unsignedInteger('minimum_stays')->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
        DB::table('customer_segment_rules')->insert([
            ['segment' => 'nuevo', 'label' => 'Cliente nuevo', 'minimum_stays' => 0, 'max_avg_interval_days' => null, 'stale_after_days' => null, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['segment' => 'frecuente', 'label' => 'Alta recurrencia', 'minimum_stays' => 2, 'max_avg_interval_days' => 45, 'stale_after_days' => null, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['segment' => 'ocasional', 'label' => 'Baja recurrencia', 'minimum_stays' => 2, 'max_avg_interval_days' => null, 'stale_after_days' => null, 'display_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['segment' => 'esporadico', 'label' => 'Esporádico', 'minimum_stays' => 2, 'max_avg_interval_days' => null, 'stale_after_days' => 120, 'display_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segment_rules');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['blacklist_status', 'blacklist_reason', 'blacklisted_at']);
        });
    }
};
