<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_rules', fn (Blueprint $table) => $table->unsignedInteger('extra_hour_price')->default(0)->after('extra_person_price'));
        DB::table('rate_rules')->where('name', 'HH')->update(['extra_hour_price' => 10000]);
        DB::table('rate_rules')->where('name', 'HOT')->update(['extra_hour_price' => 15000]);
    }

    public function down(): void
    {
        Schema::table('rate_rules', fn (Blueprint $table) => $table->dropColumn('extra_hour_price'));
    }
};
