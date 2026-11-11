<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una reserva que GHL rechaza (por ejemplo, teléfono con código de país inválido) se
        // anota aquí y se salta por un rato, en vez de frenar a todas las que vienen detrás.
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('ghl_sync_failed_at')->nullable();
            $table->unsignedSmallInteger('ghl_sync_attempts')->default(0);
            $table->string('ghl_sync_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['ghl_sync_failed_at', 'ghl_sync_attempts', 'ghl_sync_error']);
        });
    }
};
