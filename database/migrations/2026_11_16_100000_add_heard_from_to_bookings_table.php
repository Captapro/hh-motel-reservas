<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «¿Cómo nos conoció?»: lo que el cliente le dice a recepción al ingresar. Sirve para saber de
     * dónde viene la venta presencial, que es la mayoría y hoy no se puede medir (16-nov).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('heard_from', 20)->nullable()->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('heard_from');
        });
    }
};
