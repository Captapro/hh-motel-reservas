<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rescate de reservas web sin pagar: cuándo se avisó a GHL que el pago
     * estaba por vencer y cuándo que la reserva se liberó, para mandar cada
     * aviso una sola vez.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('payment_reminder_sent_at')->nullable();
            $table->timestamp('abandon_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_reminder_sent_at', 'abandon_notified_at']);
        });
    }
};
