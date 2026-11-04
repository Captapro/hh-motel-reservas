<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cuaderno de novedades del turno y quién leyó cada una. */
    public function up(): void
    {
        Schema::create('shift_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('category', 30);
            $table->text('body');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('urgent')->default(false);
            $table->boolean('pending')->default(false);
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestampsTz();
            $table->index('created_at');
        });

        Schema::create('shift_note_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('read_at');
            $table->unique(['shift_note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_note_reads');
        Schema::dropIfExists('shift_notes');
    }
};
