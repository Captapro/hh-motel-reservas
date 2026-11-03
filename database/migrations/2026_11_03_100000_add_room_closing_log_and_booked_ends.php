<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto diaria de cómo quedó cada Playroom al cierre del día operativo.
        Schema::create('room_closing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('operating_date');
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('operational_status', 20);
            $table->string('operational_note')->nullable();
            $table->boolean('occupied')->default(false);
            $table->boolean('offered')->default(true);
            $table->timestampTz('taken_at');
            $table->unique(['operating_date', 'room_id']);
        });

        // Hora de salida contratada: al finalizar antes de tiempo, ends_at se
        // acorta a la salida real y la hora contratada se perdía.
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestampTz('booked_ends_at')->nullable();
        });

        // Las ya finalizadas: la hora contratada sale del registro de auditoría
        // del primer cierre (valor anterior de ends_at); si no hay, la que quedó.
        $originalEnds = DB::table('audit_logs')
            ->where('action', 'reserva.finalizar')->where('entity_type', 'Booking')
            ->orderByDesc('id')->get(['entity_id', 'old_value'])
            ->mapWithKeys(function ($log) {
                $old = json_decode((string) $log->old_value, true);

                return [(int) $log->entity_id => $old['ends_at'] ?? null];
            });

        DB::table('bookings')->whereNotNull('checked_out_at')->orderBy('id')->get(['id', 'ends_at'])
            ->each(function ($b) use ($originalEnds) {
                $original = $originalEnds[$b->id] ?? null;
                DB::table('bookings')->where('id', $b->id)->update([
                    'booked_ends_at' => $original ? Carbon::parse($original)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : $b->ends_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_closing_snapshots');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('booked_ends_at');
        });
    }
};
