<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tiempo de cada aseo por mucama, y novedades escritas por mucamas (no son usuarios del sistema). */
    public function up(): void
    {
        Schema::create('room_cleanings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->index(['staff_id', 'finished_at']);
        });

        Schema::table('shift_notes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::dropIfExists('room_cleanings');
    }
};
