<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('audience', 20);
            $table->boolean('urgent')->default(false);
            $table->text('body');
            $table->timestampsTz();
            $table->index('created_at');
        });

        Schema::create('staff_notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->timestampTz('read_at');
            $table->unique(['staff_notice_id', 'user_id']);
            $table->unique(['staff_notice_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notice_reads');
        Schema::dropIfExists('staff_notices');
    }
};
