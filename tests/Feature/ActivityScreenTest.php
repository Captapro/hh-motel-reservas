<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\StaffShiftLog;
use App\Models\User;
use App\Support\AccessSections;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «Actividad y accesos» (solo soporte): quién entra, quién está en turno y qué acciones se registran.
 */
class ActivityScreenTest extends TestCase
{
    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        AccessSections::forget();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_10_27_120000_add_ai_access_to_users_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_09_15_233000_add_voucher_receipt_to_payments.php',
            '2026_09_29_100000_add_request_token_to_payments.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_150001_add_inventory_to_products_table.php',
            '2026_09_16_010000_create_staff_and_shifts_tables.php',
            '2026_09_16_020000_add_legal_hours_to_staff.php',
            '2026_09_22_010022_add_pin_to_staff_table.php',
            '2026_09_22_012410_create_staff_shift_logs_table.php',
            '2026_09_25_231158_add_user_id_to_staff_shift_logs_table.php',
            '2026_10_23_200000_create_access_permissions_table.php',
            '2026_10_29_120000_create_shift_handovers_tables.php',
            '2026_11_16_150000_create_login_events_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->host = User::factory()->create(['name' => 'Ana Anfitriona', 'role' => 'recepcion', 'password' => bcrypt('secreto123')]);
    }

    protected function tearDown(): void
    {
        AccessSections::forget();
        parent::tearDown();
    }

    public function test_logins_logouts_and_failed_attempts_are_recorded_with_ip_and_device(): void
    {
        $this->post('/login', ['email' => $this->host->email, 'password' => 'mala'], ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $this->host->email, 'password' => 'secreto123'])->assertRedirect();
        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertSame(['fallido', 'ok', 'salida'], LoginEvent::orderBy('id')->pluck('result')->all());
        $failed = LoginEvent::where('result', 'fallido')->first();
        $this->assertSame($this->host->id, $failed->user_id);
        $this->assertNotNull($failed->ip);
        $this->assertSame('Chrome · Windows', $failed->device());
        $this->assertNotNull(StaffShiftLog::first()->ended_at);
    }

    public function test_the_screen_is_for_soporte_only_and_shows_shifts_logins_and_actions(): void
    {
        $this->post('/login', ['email' => $this->host->email, 'password' => 'secreto123']);
        AuditLog::record($this->host->id, 'claves.ver', 'AccessCode', 3, null, null);
        AuditLog::record($this->host->id, 'claves.cambiar', 'AccessCode', 3, ['codigo' => '48271'], ['codigo' => '73958']);
        auth()->logout();

        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('admin.activity.index'))->assertForbidden();
        // El anfitrión no entra (lo manda a recibir su turno o le niega el paso).
        $this->actingAs($this->host);
        $this->assertContains($this->get(route('admin.activity.index'))->getStatusCode(), [302, 403]);

        $this->actingAs(User::factory()->create(['name' => 'Soporte Master', 'role' => 'soporte']));
        $page = $this->get(route('admin.activity.index'))->assertOk()
            ->assertSee('Actividad y accesos')
            ->assertSee('En turno ahora')
            ->assertSee('Ana Anfitriona')
            ->assertSee('Vio una clave de puerta')
            ->assertSee('Entró')
            ->assertSee('codigo: ••••');
        // Nunca muestra lo que se escribió en una clave.
        $this->assertStringNotContainsString('73958', $page->getContent());
        $this->assertStringNotContainsString('48271', $page->getContent());

        // Filtrar por otra persona deja fuera sus movimientos.
        $other = User::factory()->create(['name' => 'Otra Persona', 'role' => 'recepcion']);
        $this->get(route('admin.activity.index', ['user' => $other->id]))->assertOk()->assertDontSee('Vio una clave de puerta');
        $this->get(route('admin.activity.index', ['group' => 'claves']))->assertOk()->assertSee('Vio una clave de puerta');
    }
}
