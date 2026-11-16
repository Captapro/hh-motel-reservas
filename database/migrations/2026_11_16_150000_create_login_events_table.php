<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entradas, salidas e intentos fallidos de las cuentas de acceso, con su IP y dispositivo.
     * Para controlar quién entra al sistema (pantalla «Actividad y accesos», solo soporte).
     */
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email', 190)->nullable();
            $table->string('result', 20); // ok, salida, fallido, desactivada
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
