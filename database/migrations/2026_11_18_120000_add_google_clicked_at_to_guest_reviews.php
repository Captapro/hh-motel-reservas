<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_reviews', function (Blueprint $table) {
            $table->timestamp('google_clicked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guest_reviews', function (Blueprint $table) {
            $table->dropColumn('google_clicked_at');
        });
    }
};
