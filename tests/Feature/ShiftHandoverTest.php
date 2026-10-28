<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Product;
use App\Models\ShiftHandover;
use App\Models\User;
use App\Support\AccessSections;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftHandoverTest extends TestCase
{
    private User $host;

    private Product $beer;

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
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_09_15_233000_add_voucher_receipt_to_payments.php',
            '2026_09_29_100000_add_request_token_to_payments.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_150001_add_inventory_to_products_table.php',
            '2026_09_11_100000_create_cash_movements_table.php',
            '2026_09_16_010000_create_staff_and_shifts_tables.php',
            '2026_09_16_020000_add_legal_hours_to_staff.php',
            '2026_09_22_010022_add_pin_to_staff_table.php',
            '2026_09_22_010843_add_last_qr_scan_to_staff_table.php',
            '2026_09_22_012410_create_staff_shift_logs_table.php',
            '2026_09_25_231158_add_user_id_to_staff_shift_logs_table.php',
            '2026_10_23_200000_create_access_permissions_table.php',
            '2026_10_29_120000_create_shift_handovers_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        $this->host = User::factory()->create(['role' => 'recepcion', 'password' => bcrypt('secreto123')]);
        $this->beer = Product::create(['name' => 'Cerveza', 'category' => 'bebida', 'price' => 3000, 'track_inventory' => true, 'stock' => 10, 'low_stock_threshold' => 2, 'display_order' => 1, 'is_active' => true]);
        CashMovement::create(['type' => 'ingreso', 'amount' => 50000, 'description' => 'Fondo inicial']);
    }

    protected function tearDown(): void
    {
        AccessSections::forget();
        parent::tearDown();
    }

    private function login(): void
    {
        $this->post('/login', ['email' => $this->host->email, 'password' => 'secreto123']);
    }

    public function test_the_host_cannot_work_until_receiving_the_shift(): void
    {
        $this->login();

        $this->get('/caja')->assertRedirect(route('shift.receive'));
        $this->get('/turno/recibir')->assertOk()->assertSee('Cerveza')->assertDontSee('50.000');

        $this->post('/turno/recibir', ['counted_cash' => 50000, 'counts' => [$this->beer->id => 10]])
            ->assertRedirect(route('rooms.board'));

        $handover = ShiftHandover::sole();
        $this->assertFalse($handover->has_differences);
        $this->assertSame(1, CashMovement::count());
        $this->get('/caja')->assertOk();
    }

    public function test_a_difference_needs_a_note_and_then_becomes_the_new_count(): void
    {
        $this->login();

        $this->from('/turno/recibir')
            ->post('/turno/recibir', ['counted_cash' => 45000, 'counts' => [$this->beer->id => 8]])
            ->assertRedirect('/turno/recibir')->assertSessionHasErrors('notes');
        $this->assertSame(0, ShiftHandover::count());

        $this->post('/turno/recibir', ['counted_cash' => 45000, 'counts' => [$this->beer->id => 8], 'notes' => 'Faltan 2 cervezas y $5.000'])
            ->assertRedirect();

        $handover = ShiftHandover::sole();
        $this->assertTrue($handover->has_differences);
        $this->assertSame(-5000, $handover->cashDifference());
        $this->assertSame(8, $this->beer->fresh()->stock);
        $this->assertSame(45000, CashMovement::balance()['saldo']);

        $admin = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($admin)->get('/caja-turnos')->assertOk()->assertSee('Faltan 2 cervezas')->assertSee('Turnos con diferencias (1)');
        $this->post(route('cash.shifts.review', $handover))->assertRedirect();
        $this->assertNotNull($handover->fresh()->reviewed_at);
    }

    public function test_delivering_the_shift_logs_the_host_out(): void
    {
        $this->login();
        $this->post('/turno/recibir', ['counted_cash' => 50000, 'counts' => [$this->beer->id => 10]]);

        $this->post('/turno/entregar', ['counted_cash' => 50000, 'counts' => [$this->beer->id => 10]])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(['recibir', 'entregar'], ShiftHandover::orderBy('id')->pluck('kind')->all());
        $this->assertNotNull($this->host->shiftLogs()->first()->ended_at);
    }

    public function test_the_host_cannot_see_the_shift_history(): void
    {
        $this->login();
        $this->post('/turno/recibir', ['counted_cash' => 50000, 'counts' => [$this->beer->id => 10]]);

        $this->get('/caja-turnos')->assertForbidden();
    }
}
