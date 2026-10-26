<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo nació la reserva: 'presencial' (ingreso en recepción), 'recepcion'
     * (reserva creada por el equipo) o 'catalogo' (cliente en línea). Antes no
     * se guardaba: solo se puede saber con certeza que las que no tienen
     * cuenta creadora vinieron del catálogo; el resto del historial queda sin
     * dato (se muestra "—").
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('origin', 20)->nullable()->after('created_by');
        });

        DB::table('bookings')->whereNull('created_by')->update(['origin' => 'catalogo']);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
