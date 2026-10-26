<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Staff;
use App\Models\StaffShiftLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Limpieza del historial de pruebas: solo soporte, con confirmación, y sin tocar lo demás. */
class CleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_09_11_100000_create_cash_movements_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_09_22_004138_add_aseo_report_to_rooms_table.php',
            '2026_08_26_120001_add_aseo_override_to_rooms_table.php',
            '2026_08_26_140001_add_aseo_started_to_rooms_table.php',
            '2026_09_15_231000_create_room_inspections_table.php',
            '2026_09_15_234000_add_round_details_to_room_inspections.php',
            '2026_09_16_010000_create_staff_and_shifts_tables.php',
            '2026_09_16_020000_add_legal_hours_to_staff.php',
            '2026_09_22_010022_add_pin_to_staff_table.php',
            '2026_09_22_010843_add_last_qr_scan_to_staff_table.php',
            '2026_09_22_012410_create_staff_shift_logs_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_only_soporte_can_open_and_run_the_cleanup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('admin.cleanup.index'))->assertForbidden();
        $this->delete(route('admin.cleanup.destroy'), ['what' => ['aseo'], 'confirm_text' => 'ELIMINAR'])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'soporte']));
        $this->get(route('admin.cleanup.index'))->assertOk()->assertSee('Limpiar historial de pruebas');
    }

    public function test_cleanup_deletes_only_what_was_chosen_and_needs_the_confirmation_word(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'soporte']));
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa', 'aseo_reported_by' => 'Ana', 'aseo_reported_at' => now()]);
        $staff = Staff::create(['name' => 'Ana', 'role' => 'Mucama', 'is_active' => true, 'last_qr_room_id' => $room->id, 'last_qr_seen_at' => now()]);
        AuditLog::record(null, 'habitacion.aseo_reportado', 'Room', $room->id, null, ['staff_id' => $staff->id]);
        AuditLog::record(null, 'habitacion.qr_visita', 'Room', $room->id, null, ['staff_id' => $staff->id]);
        AuditLog::record(1, 'reserva.crear', 'Booking', 5, null, []);
        StaffShiftLog::create(['staff_id' => $staff->id, 'started_at' => now()]);

        // Sin la palabra de confirmación no se borra nada.
        $this->delete(route('admin.cleanup.destroy'), ['what' => ['aseo'], 'confirm_text' => 'si'])->assertSessionHasErrors('confirm_text');
        $this->assertSame(3, AuditLog::count());

        // Solo aseo: salen los dos registros de aseo y lo que las mucamas dejaron visto; queda la reserva y los turnos.
        $this->delete(route('admin.cleanup.destroy'), ['what' => ['aseo'], 'confirm_text' => 'ELIMINAR'])->assertRedirect(route('admin.cleanup.index'));
        $this->assertSame(0, AuditLog::where('action', 'like', 'habitacion.%')->count());
        $this->assertSame(1, AuditLog::where('action', 'reserva.crear')->count());
        $this->assertNotNull(AuditLog::where('action', 'limpieza.historial')->first());
        $this->assertNull($staff->fresh()->last_qr_room_id);
        $this->assertNull($room->fresh()->aseo_reported_by);
        $this->assertSame(1, StaffShiftLog::count());

        // Con fecha: lo de hoy se conserva.
        $this->delete(route('admin.cleanup.destroy'), ['what' => ['turnos'], 'before' => now()->subDay()->toDateString(), 'confirm_text' => 'ELIMINAR']);
        $this->assertSame(1, StaffShiftLog::count());
        $this->delete(route('admin.cleanup.destroy'), ['what' => ['turnos'], 'confirm_text' => 'ELIMINAR']);
        $this->assertSame(0, StaffShiftLog::count());
    }
}
