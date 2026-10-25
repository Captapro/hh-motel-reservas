<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', fn (Blueprint $table) => $table->unsignedTinyInteger('included_extra_guests')->default(0)->after('discount_value'));
    }

    public function down(): void
    {
        Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn('included_extra_guests'));
    }
};
