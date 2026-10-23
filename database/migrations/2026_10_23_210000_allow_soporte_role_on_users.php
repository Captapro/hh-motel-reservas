<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La columna role era un enum con tres valores. Se pasa a texto para
     * poder sumar el perfil "soporte" (master); la validación de qué roles
     * son válidos ya se hace en la aplicación.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('recepcion')->change();
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás: volver al enum rechazaría cuentas "soporte" existentes.
    }
};
