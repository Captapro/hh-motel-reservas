<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El teléfono deja de ser obligatorio: hay clientes presenciales que no
     * lo entregan. Sin teléfono se identifican por RUT/pasaporte y no se
     * sincronizan con GHL (no hay a dónde mandarles WhatsApp).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customers ALTER COLUMN phone_e164 DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // No se vuelve a exigir: los clientes sin teléfono quedarían inválidos.
    }
};
