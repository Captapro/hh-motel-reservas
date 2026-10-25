<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furniture_items', function (Blueprint $table) {
            $table->string('icon', 12)->default('✦')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('furniture_items', fn (Blueprint $table) => $table->dropColumn('icon'));
    }
};
