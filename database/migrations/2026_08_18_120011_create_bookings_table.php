<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Necesaria para poder combinar igualdad (room_id) con superposición de
        // rangos (tstzrange) en una misma restricción EXCLUDE -- específico de
        // Postgres. En SQLite (solo se usa para tests que no dependen de esta
        // garantía a nivel de motor) se salta directo a crear la tabla.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // HH-20260818-10584
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('guests_count')->default(2);

            $table->enum('booking_status', [
                'BORRADOR', 'PENDIENTE_PAGO', 'RETENIDA', 'CONFIRMADA',
                'CHECK_IN', 'FINALIZADA', 'CANCELADA', 'EXPIRADA', 'NO_SHOW',
            ])->default('PENDIENTE_PAGO');

            $table->enum('payment_status', [
                'NO_PAGADA', 'PAGO_PENDIENTE', 'PARCIALMENTE_PAGADA',
                'PAGADA', 'REEMBOLSO_PARCIAL', 'REEMBOLSADA',
            ])->default('NO_PAGADA');

            // --- snapshot de precio: congelado al confirmar, nunca se recalcula ---
            $table->foreignId('rate_rule_id')->nullable()->constrained('rate_rules')->nullOnDelete();
            $table->string('rate_rule_name_snapshot', 50)->nullable(); // "HH" / "HOT"
            $table->unsignedInteger('price_original');
            $table->unsignedInteger('extra_guests_fee')->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code_snapshot', 50)->nullable();
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('price_final');
            $table->unsignedInteger('deposit_amount')->default(0);
            // --- fin snapshot ---

            $table->text('notes')->nullable();
            $table->timestampTz('expires_at')->nullable(); // vencimiento de la retención, si aplica
            $table->timestamps();

            $table->index(['room_id', 'starts_at', 'ends_at']);
            $table->index('booking_status');
            $table->index('payment_status');
        });

        // Ninguna habitación puede tener dos reservas activas con horarios superpuestos.
        // Es una garantía del motor de base de datos, no del código de la aplicación
        // -- sintaxis EXCLUDE de Postgres, no existe en SQLite.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_no_overlap
                EXCLUDE USING gist (
                    room_id WITH =,
                    tstzrange(starts_at, ends_at) WITH &&
                )
                WHERE (booking_status NOT IN ('CANCELADA', 'EXPIRADA', 'NO_SHOW'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
