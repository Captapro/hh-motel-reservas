<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quién puede usar el Analista IA y la Ayuda IA: se autoriza persona
        // por persona en Personal. Las cuentas de administrador y soporte que
        // ya existen parten autorizadas para no quedar fuera.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_use_analyst')->default(false);
            $table->boolean('can_use_help')->default(false);
        });
        DB::table('users')->whereIn('role', ['administrador', 'soporte'])->update(['can_use_analyst' => true, 'can_use_help' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_use_analyst', 'can_use_help']);
        });
    }
};
