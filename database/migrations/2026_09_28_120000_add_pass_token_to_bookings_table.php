<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El pase PDF se comparte por WhatsApp sin login (el código de reserva
 * hacía de token de acceso) -- pero el código sale de un contador
 * secuencial por día (HH-20260928-00001, 00002...), así que probando
 * enlaces cercanos se podía llegar al pase de otro huésped (nombre,
 * habitación, horario, pagos). pass_token es aleatorio e independiente
 * del código legible que usa el equipo puertas adentro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('pass_token', 64)->nullable()->after('code');
        });

        foreach (DB::table('bookings')->select('id')->cursor() as $row) {
            DB::table('bookings')->where('id', $row->id)->update(['pass_token' => Str::random(40)]);
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('pass_token', 64)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('pass_token');
        });
    }
};
