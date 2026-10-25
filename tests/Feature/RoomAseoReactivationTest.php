<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Staff;
use App\Models\StaffShiftLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Una habitación que salió a aseo (check-out o envío manual) no puede volver
 * a "activa" directo -- tiene que pasar por "aseo listo" (con quién hizo el
 * aseo, para el registro de auditoría). RoomBoardController::updateStatus()
 * ya bloqueaba el atajo, pero no había ningún test que lo dejara fijo.
 */
class RoomAseoReactivationTest extends TestCase
{
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_26_120001_add_aseo_override_to_rooms_table.php',
            '2026_08_26_140001_add_aseo_started_to_rooms_table.php',
            '2026_09_22_004138_add_aseo_report_to_rooms_table.php',
            '2026_09_22_012032_add_room_check_to_bookings_table.php',
            '2026_09_16_010000_create_staff_and_shifts_tables.php',
            '2026_09_16_020000_add_legal_hours_to_staff.php',
            '2026_09_22_010022_add_pin_to_staff_table.php',
            '2026_09_22_010843_add_last_qr_scan_to_staff_table.php',
            '2026_09_22_012410_create_staff_shift_logs_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'aseo']);
    }

    public function test_room_in_aseo_cannot_be_reactivated_directly(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $response = $this->post("/habitaciones/{$this->room->id}/estado", [
            'operational_status' => 'activa',
        ]);

        $response->assertSessionHasErrors('room');
        $this->assertSame('aseo', $this->room->fresh()->operational_status);
    }

    public function test_marking_aseo_ready_reactivates_the_room(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true]);

        $response = $this->post("/habitaciones/{$this->room->id}/aseo-listo", [
            'cleaned_by' => 'Ana Mucama',
        ]);

        $response->assertRedirect();
        $this->assertSame('activa', $this->room->fresh()->operational_status);
    }

    public function test_marking_aseo_ready_rejects_a_name_outside_the_active_roster(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $response = $this->post("/habitaciones/{$this->room->id}/aseo-listo", [
            'cleaned_by' => 'Nadie Registrado',
        ]);

        $response->assertSessionHasErrors('cleaned_by');
        $this->assertSame('aseo', $this->room->fresh()->operational_status);
    }

    /**
     * El QR de la puerta lo escanea la mucama, que ahora necesita haber
     * iniciado sesión con su PIN (ver EnsureMucamaSession) -- ya no elige su
     * nombre de una lista, así que no puede reportar "a nombre de" otra.
     * Confirma también que el reporte deja la habitación en 'aseo' (no la
     * reactiva sola) y que recepción ve ese reporte como contexto al
     * confirmar.
     */
    public function test_logged_in_maid_can_report_aseo_via_the_qr_route(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $response = $this->withSession(['mucama_staff_id' => $mucama->id])
            ->post("/qr/habitacion/{$this->room->id}/aseo");

        $response->assertRedirect();
        $fresh = $this->room->fresh();
        $this->assertSame('aseo', $fresh->operational_status);
        $this->assertSame('Ana Mucama', $fresh->aseo_reported_by);
        $this->assertNotNull($fresh->aseo_reported_at);

        // Recepción confirma después -- el reporte de la mucama se limpia,
        // no queda arrastrado para el próximo ciclo de aseo.
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->post("/habitaciones/{$this->room->id}/aseo-listo", ['cleaned_by' => 'Ana Mucama']);

        $confirmed = $this->room->fresh();
        $this->assertSame('activa', $confirmed->operational_status);
        $this->assertNull($confirmed->aseo_reported_by);
        $this->assertNull($confirmed->aseo_reported_at);
    }

    public function test_reporting_aseo_without_a_maid_session_redirects_to_login(): void
    {
        Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $response = $this->post("/qr/habitacion/{$this->room->id}/aseo");

        $this->assertStringContainsString('/qr/mucamas/entrar', $response->headers->get('Location'));
        $this->assertNull($this->room->fresh()->aseo_reported_at);
    }

    public function test_reporting_aseo_is_rejected_once_the_room_is_no_longer_awaiting_it(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);
        $this->room->update(['operational_status' => 'activa']);

        $response = $this->withSession(['mucama_staff_id' => $mucama->id])
            ->post("/qr/habitacion/{$this->room->id}/aseo");

        $response->assertSessionHasErrors('pin');
        $this->assertNull($this->room->fresh()->aseo_reported_at);
    }

    public function test_maid_login_requires_the_correct_pin(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $response = $this->post('/qr/mucamas/entrar', ['staff_id' => $mucama->id, 'pin' => '9999']);

        $response->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_maid_login_succeeds_with_the_correct_pin(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $response = $this->post('/qr/mucamas/entrar', ['staff_id' => $mucama->id, 'pin' => '1234']);

        $response->assertRedirect(route('rooms.qr.mucama_panel'));
        $response->assertSessionHas('mucama_staff_id', $mucama->id);
    }

    /**
     * Recepción quiere saber "dónde anda cada mucama ahora" -- se registra
     * con cada visita al QR de una habitación (no solo al reportar aseo),
     * para que se note incluso cuando recién está mirando la pieza.
     */
    public function test_visiting_a_room_qr_while_logged_in_updates_maid_presence(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $this->withSession(['mucama_staff_id' => $mucama->id])
            ->get("/qr/habitacion/{$this->room->id}")
            ->assertOk();

        $fresh = $mucama->fresh();
        $this->assertSame($this->room->id, $fresh->last_qr_room_id);
        $this->assertNotNull($fresh->last_qr_seen_at);
    }

    /**
     * Además de "aseo listo" (la limpieza en sí), una mucama logueada puede
     * confirmar que la pieza quedó en condiciones justo antes de que llegue
     * la próxima reserva -- un último vistazo, no lo mismo que el aseo.
     */
    public function test_logged_in_maid_can_confirm_room_ready_for_next_booking(): void
    {
        $activeRoom = Room::create(['room_category_id' => $this->room->room_category_id, 'name' => 'GO 102', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911112222']);
        $booking = Booking::create([
            'code' => 'HH-TEST-0002',
            'customer_id' => $customer->id,
            'room_id' => $activeRoom->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'guests_count' => 2,
            'booking_status' => 'CONFIRMADA',
            'payment_status' => 'NO_PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
        ]);

        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $response = $this->withSession(['mucama_staff_id' => $mucama->id])
            ->post("/qr/habitacion/{$activeRoom->id}/confirmar");

        $response->assertRedirect();
        $fresh = $booking->fresh();
        $this->assertSame('Ana Mucama', $fresh->room_checked_by);
        $this->assertNotNull($fresh->room_checked_at);
    }

    /**
     * Login/logout funcionan como marca real de entrada/salida de turno
     * (StaffShiftLog) -- distinto del horario planificado (Shift). Un
     * segundo login sin haber cerrado sesión antes no abre un turno nuevo.
     */
    public function test_login_opens_a_shift_log_and_logout_closes_it(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $this->post('/qr/mucamas/entrar', ['staff_id' => $mucama->id, 'pin' => '1234']);
        $this->assertNotNull($mucama->openShiftLog());

        // Un segundo login (por ejemplo, la sesión expiró en el celular pero
        // no cerró sesión a propósito) no abre un turno duplicado.
        $this->post('/qr/mucamas/entrar', ['staff_id' => $mucama->id, 'pin' => '1234']);
        $this->assertSame(1, StaffShiftLog::where('staff_id', $mucama->id)->count());

        $this->withSession(['mucama_staff_id' => $mucama->id])->post('/qr/mucamas/salir');

        $this->assertNull($mucama->openShiftLog());
        $log = StaffShiftLog::where('staff_id', $mucama->id)->firstOrFail();
        $this->assertNotNull($log->ended_at);
    }
}
