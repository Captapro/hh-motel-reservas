<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega 'ocupada' como estado operativo: un cliente adentro sin reserva
     * en el sistema (p. ej. entró con la planilla antes de la marcha blanca).
     * Cuenta como ocupada en el tablero, no se vende y al salir va a aseo.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS rooms_operational_status_check');
        DB::statement("ALTER TABLE rooms ADD CONSTRAINT rooms_operational_status_check CHECK (operational_status IN ('activa', 'mantencion', 'inactiva', 'aseo', 'ocupada'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement("UPDATE rooms SET operational_status = 'aseo' WHERE operational_status = 'ocupada'");
        DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS rooms_operational_status_check');
        DB::statement("ALTER TABLE rooms ADD CONSTRAINT rooms_operational_status_check CHECK (operational_status IN ('activa', 'mantencion', 'inactiva', 'aseo'))");
    }
};
