<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\ShiftNote;
use App\Models\RoomCleaning;
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
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_10_26_220000_create_late_arrival_settings_table.php',
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
            '2026_11_04_120000_create_shift_notes_tables.php',
            '2026_11_04_130000_add_room_cleanings_and_note_staff.php',
            '2026_11_04_140000_add_ronda_pin_to_users.php',
            '2026_11_04_150000_create_staff_notices_tables.php',
            '2026_11_04_160000_create_push_subscriptions_table.php',
            '2026_11_05_100000_add_pinned_to_shift_notes.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_11_10_150000_add_stock_deducted_at_to_booking_addons.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_09_15_230000_create_room_furniture_tables.php',
            '2026_09_15_231000_create_room_inspections_table.php',
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

    public function test_reception_just_confirms_who_cleaned_when_the_maid_reported_with_her_pin(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);
        $this->withSession(['mucama_staff_id' => $mucama->id])->post("/qr/habitacion/{$this->room->id}/aseo");

        // Sin elegir a nadie: el PIN ya dijo quién hizo el aseo.
        $this->actingAs(User::factory()->create(['role' => 'recepcion']));
        $this->post("/habitaciones/{$this->room->id}/aseo-listo")->assertSessionHasNoErrors();

        $this->assertSame('activa', $this->room->fresh()->operational_status);
        $log = AuditLog::where('action', 'habitacion.aseo_listo')->latest('id')->first();
        $this->assertSame('Ana Mucama', $log->new_value['cleaned_by']);
    }

    public function test_maid_starts_and_finishes_a_cleaning_and_leaves_a_note(): void
    {
        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);
        $session = ['mucama_staff_id' => $mucama->id];
        $other = Room::create(['room_category_id' => $this->room->room_category_id, 'name' => 'PLUS 999', 'operational_status' => 'aseo']);

        $this->withSession($session)->post(route('mucamas.work.start', $this->room))->assertRedirect(route('rooms.qr.mucama_panel'));
        $cleaning = RoomCleaning::sole();
        $this->assertNull($cleaning->finished_at);

        // Un aseo a la vez.
        $this->withSession($session)->post(route('mucamas.work.start', $other))->assertSessionHasErrors('aseo');
        $this->assertSame(1, RoomCleaning::count());

        $this->travel(25)->minutes();
        $this->withSession($session)->post(route('mucamas.work.finish', $this->room))->assertRedirect(route('rooms.qr.mucama_panel'));
        $this->assertSame(25, $cleaning->fresh()->minutes());
        $this->assertSame('Ana Mucama', $this->room->fresh()->aseo_reported_by);

        $this->withSession($session)->post(route('mucamas.work.note'), ['category' => 'Insumos', 'body' => 'Falta jabón'])->assertSessionHasNoErrors();
        $note = ShiftNote::sole();
        $this->assertSame('Ana Mucama', $note->authorName());

        // La novedad de la mucama le llega a la anfitriona.
        $host = User::factory()->create(['role' => 'recepcion']);
        $this->assertSame([$note->id], ShiftNote::alertFor($host)->pluck('id')->all());
    }

    public function test_each_room_falls_in_one_condition_and_only_free_rooms_get_a_round(): void
    {
        $categoryId = $this->room->room_category_id;
        $free = Room::create(['room_category_id' => $categoryId, 'name' => 'GO 102', 'operational_status' => 'activa']);
        $marked = Room::create(['room_category_id' => $categoryId, 'name' => 'GO 103', 'operational_status' => 'ocupada']);
        $out = Room::create(['room_category_id' => $categoryId, 'name' => 'GO 104', 'operational_status' => 'mantencion']);

        $groups = app(\App\Services\Booking\RoomBoardService::class)->byCondition();
        $names = fn (string $group) => $groups[$group]->map(fn (array $e) => $e['room']->name)->all();

        $this->assertSame([$this->room->name], $names('aseo'));
        $this->assertSame([$free->name], $names('free'));
        $this->assertSame([$marked->name], $names('occupied'));
        $this->assertSame([$out->name], $names('out'));

        $mucama = Staff::create(['name' => 'Ana Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);
        $this->withSession(['mucama_staff_id' => $mucama->id])->get(route('rooms.qr.mucama_panel'))
            ->assertOk()->assertSeeInOrder(['Ocupadas', 'GO 103', 'Fuera de servicio', 'GO 104', 'Mantención']);

        // Aunque tenga un escaneo válido, una pieza en aseo no admite levantamiento.
        $this->actingAs(User::factory()->create(['role' => 'recepcion']))
            ->withSession(['qr_scanned_at.'.$this->room->id => now()->timestamp])
            ->get(route('rooms.inspections.create', $this->room).'?qr=1')
            ->assertRedirect(route('rooms.qr.show', $this->room));
    }

    public function test_upcoming_arrivals_go_first_in_both_panels(): void
    {
        $activeRoom = Room::create(['room_category_id' => $this->room->room_category_id, 'name' => 'GO 102', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911112222']);
        Booking::create([
            'code' => 'HH-TEST-0003',
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
        $this->withSession(['mucama_staff_id' => $mucama->id])->get(route('rooms.qr.mucama_panel'))
            ->assertOk()->assertSeeInOrder(['ocupadas', 'Ocupadas', 'Próximas a llegar', 'GO 102', 'Urgente', 'Por limpiar', 'Novedades de hoy']);

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->get(route('shift_round.index'))
            ->assertOk()->assertSeeInOrder(['ocupadas', 'Ocupadas', 'Próximas a llegar', 'GO 102', 'Urgente', 'En aseo', 'Por revisar']);
    }

    public function test_host_creates_a_pin_and_it_only_opens_the_round(): void
    {
        $host = User::factory()->create(['role' => 'recepcion', 'name' => 'Ana Anfitriona']);
        $admin = User::factory()->create(['role' => 'administrador', 'name' => 'Jefa Admin']);

        $this->get(route('shift_round.index'))->assertRedirect(route('ronda.login'));
        $this->get(route('ronda.login'))->assertOk()->assertSee('Ana Anfitriona')->assertDontSee('Jefa Admin');

        // Crear el PIN pide repetirlo.
        $this->post(route('ronda.login.store'), ['user_id' => $host->id, 'pin' => '1234', 'pin_confirmation' => '9999'])->assertSessionHasErrors('pin');
        $this->assertNull($host->fresh()->ronda_pin);
        $this->assertGuest();

        $this->post(route('ronda.login.store'), ['user_id' => $host->id, 'pin' => '1234', 'pin_confirmation' => '1234'])->assertRedirect(route('shift_round.index'));
        $this->assertAuthenticatedAs($host);
        $this->assertNotSame('1234', $host->fresh()->ronda_pin);

        // La sesión con PIN sirve solo para la ronda y no abre turno.
        $this->get(route('shift_round.index'))->assertOk()->assertSee('Salir de la ronda');
        $this->get(route('rooms.board'))->assertRedirect(route('shift_round.index'));
        $this->get(route('cash.show'))->assertRedirect(route('shift_round.index'));
        $this->assertSame(0, StaffShiftLog::count());

        $this->post(route('ronda.logout'))->assertRedirect(route('ronda.login'));
        $this->assertGuest();

        // Después entra siempre con ese PIN.
        $this->post(route('ronda.login.store'), ['user_id' => $host->id, 'pin' => '0000'])->assertSessionHasErrors('pin');
        $this->assertGuest();
        $this->post(route('ronda.login.store'), ['user_id' => $host->id, 'pin' => '1234'])->assertRedirect(route('shift_round.index'));
        $this->assertAuthenticatedAs($host);
        $this->post(route('ronda.logout'));

        // Un administrador no entra con PIN.
        $this->post(route('ronda.login.store'), ['user_id' => $admin->id, 'pin' => '1234', 'pin_confirmation' => '1234'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_admin_sends_a_notice_and_staff_must_confirm_it(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $host = User::factory()->create(['role' => 'recepcion', 'name' => 'Ana Anfitriona']);
        $mucama = Staff::create(['name' => 'Eva Mucama', 'role' => 'Mucama', 'is_active' => true, 'pin' => '1234']);

        $this->actingAs($host)->get(route('admin_app.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin_app.index'))->assertOk()->assertSee('Avisar al personal');
        $this->post(route('admin_app.notify'), ['audience' => 'mucamas', 'body' => 'Revisar insumos', 'urgent' => '1'])->assertSessionHasNoErrors();
        $this->get(route('admin_app.index'))->assertOk()->assertSee('Revisar insumos')->assertSee('Eva Mucama');
        $notice = \App\Models\StaffNotice::sole();

        // A la anfitriona no le llega un aviso solo para mucamas.
        $this->actingAs($host)->getJson(route('staff_notices.pending'))->assertExactJson([]);
        $this->postJson(route('staff_notices.read', $notice))->assertNotFound();
        auth()->logout();

        $session = ['mucama_staff_id' => $mucama->id];
        $this->withSession($session)->getJson(route('staff_notices.pending'))->assertJsonCount(1)->assertJsonPath('0.urgent', true);
        $this->withSession($session)->postJson(route('staff_notices.read', $notice))->assertOk();
        $this->withSession($session)->getJson(route('staff_notices.pending'))->assertExactJson([]);
        $this->assertSame(['Eva Mucama'], $notice->fresh('reads')->delivery()['read']->all());
    }

    public function test_room_going_to_maintenance_alerts_the_admins_with_the_reason(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $host = User::factory()->create(['role' => 'recepcion']);
        $room = Room::create(['room_category_id' => $this->room->room_category_id, 'name' => 'GO 102', 'operational_status' => 'activa']);

        $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", ['operational_status' => 'mantencion', 'operational_note' => 'Fuga en la ducha'])->assertSessionHasNoErrors();

        $this->assertSame('GO 102 quedó en mantención. Motivo: Fuga en la ducha', \App\Models\StaffNotice::sole()->body);
        $this->getJson(route('staff_notices.pending'))->assertJsonCount(1);
        $this->actingAs($host)->getJson(route('staff_notices.pending'))->assertExactJson([]);

        // Guardar otra vez el mismo estado no repite la alerta.
        $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", ['operational_status' => 'mantencion', 'operational_note' => 'Sigue la fuga']);
        $this->assertSame(1, \App\Models\StaffNotice::count());
    }

    public function test_admin_app_lists_reservations_beyond_the_next_day(): void
    {
        $customer = Customer::create(['name' => 'Cliente Lejano', 'phone_e164' => '+56911112222']);
        $later = now()->addDays(2);
        Booking::create([
            'code' => 'HH-TEST-0010',
            'customer_id' => $customer->id,
            'room_id' => $this->room->id,
            'starts_at' => $later,
            'ends_at' => $later->copy()->addHours(2),
            'duration_minutes' => 120,
            'guests_count' => 2,
            'booking_status' => 'CONFIRMADA',
            'payment_status' => 'NO_PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'administrador']))
            ->get(route('admin_app.index'))->assertOk()->assertSeeInOrder(['Próximas reservas', 'GO 101', 'Cliente Lejano']);

        $this->getJson(route('admin_app.pulse'))->assertExactJson(['booking' => Booking::max('id'), 'payment' => 0, 'new' => []]);
        $this->getJson(route('admin_app.pulse', ['desde' => Booking::max('id')]))->assertJsonPath('new', []);
        Booking::query()->update(['origin' => 'presencial']);
        $this->getJson(route('admin_app.pulse', ['desde' => 0]))->assertJsonCount(1, 'new')
            ->assertJsonPath('new.0.room', 'GO 101')->assertJsonPath('new.0.origin', Booking::sole()->originLabel())->assertJsonPath('new.0.amount', 20000);
    }

    public function test_admin_phone_can_subscribe_to_notifications(): void
    {
        $payload = ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'clave', 'auth' => 'token']];

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->postJson(route('admin_app.push'), $payload)->assertForbidden();

        $admin = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($admin)->postJson(route('admin_app.push'), $payload)->assertOk();
        $this->actingAs($admin)->postJson(route('admin_app.push'), $payload)->assertOk();

        $this->assertSame($admin->id, \App\Models\PushSubscription::sole()->user_id);
    }

    public function test_notification_problems_never_stop_a_sale(): void
    {
        config(['services.webpush.public_key' => 'mala', 'services.webpush.private_key' => 'mala']);
        \App\Models\PushSubscription::create(['user_id' => User::factory()->create()->id, 'endpoint' => 'https://push.example.com/abc', 'public_key' => 'x', 'auth_token' => 'y']);

        \App\Services\Notifications\AdminPush::send('Prueba', 'Con claves inválidas');
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();

        $this->assertSame(1, \App\Models\PushSubscription::count());
    }

    public function test_inspection_that_needs_maintenance_alerts_the_admins(): void
    {
        $inspection = new \App\Models\RoomInspection(['inspected_by' => 'Ana', 'needs_maintenance' => true, 'notes' => 'Fuga en la ducha']);
        $this->mock(\App\Services\Booking\RoomInspectionService::class, function ($mock) use ($inspection) {
            $mock->shouldReceive('validationRules')->andReturn([]);
            $mock->shouldReceive('submit')->andReturn($inspection);
        });

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))
            ->post(route('rooms.inspections.store', $this->room), ['inspected_by' => 'Ana', 'shift' => 'Noche'])
            ->assertSessionHasNoErrors();

        $notice = \App\Models\StaffNotice::sole();
        $this->assertSame('administracion', $notice->audience);
        $this->assertSame('GO 101 necesita mantención. Inspección de Ana: Fuga en la ducha', $notice->body);
    }
}
