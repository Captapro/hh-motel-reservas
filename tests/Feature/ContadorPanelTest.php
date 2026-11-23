<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El contador / auditor entra con su propia cuenta y solo ve su panel: resumen,
 * libro de ventas, libro de cobros y conciliación, con planillas. No puede
 * tocar nada más del sistema.
 */
class ContadorPanelTest extends TestCase
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
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_09_11_090000_add_identity_fields_to_customers_table.php',
            '2026_11_01_020000_make_customer_phone_optional.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_11_10_150000_add_stock_deducted_at_to_booking_addons.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120017_create_refunds_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_19_090003_add_consumption_offered_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_11_03_100000_add_room_closing_log_and_booked_ends.php',
            '2026_08_26_120001_add_aseo_override_to_rooms_table.php',
            '2026_08_26_140001_add_aseo_started_to_rooms_table.php',
            '2026_09_15_233000_add_voucher_receipt_to_payments.php',
            '2026_09_29_100000_add_request_token_to_payments.php',
            '2026_11_11_170000_add_checkout_regularized_to_bookings.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable();
        });

        $category = RoomCategory::create(['name' => 'PLUS', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'PLUS 307', 'operational_status' => 'activa']);
        // Un nombre que Excel leería como fórmula: tiene que salir neutralizado en la planilla.
        $customer = Customer::create(['name' => '=HYPERLINK("http://x")', 'phone_e164' => '+56911111111']);
        $debito = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito', 'is_active' => true]);

        $booking = Booking::create([
            'code' => 'HH-TEST-0001', 'pass_token' => (string) Str::uuid(),
            'customer_id' => $customer->id, 'room_id' => $room->id,
            'starts_at' => Carbon::parse('2026-11-21 15:00', 'America/Santiago'), 'ends_at' => Carbon::parse('2026-11-21 18:00', 'America/Santiago'),
            'duration_minutes' => 180, 'guests_count' => 2,
            'booking_status' => 'FINALIZADA', 'payment_status' => 'PAGADA',
            'price_original' => 40000, 'price_final' => 40000,
        ]);
        Payment::create([
            'booking_id' => $booking->id, 'payment_method_id' => $debito->id, 'amount' => 40000, 'status' => 'aprobado',
            'voucher_number' => '206', 'external_id' => 'REC-TEST-0001', 'created_at' => Carbon::parse('2026-11-21 15:05', 'America/Santiago'),
        ]);
    }

    private function contador(): User
    {
        return User::factory()->create(['role' => 'contador']);
    }

    public function test_the_accountant_lands_on_their_panel_and_sees_the_month(): void
    {
        $user = $this->contador();
        $this->assertSame('contador.index', $user->homeRoute());

        $this->actingAs($user)->get('/contador?mes=2026-11')
            ->assertOk()
            ->assertSee('Panel del contador')
            ->assertSee('$40.000')
            ->assertSee('Débito');
        $this->actingAs($user)->get('/contador/ventas?mes=2026-11')->assertOk()->assertSee('HH-TEST-0001');
        $this->actingAs($user)->get('/contador/cobros?mes=2026-11')->assertOk()->assertSee('206');
    }

    public function test_an_administrator_can_set_the_real_date_of_a_payment_and_it_is_audited(): void
    {
        $payment = Payment::firstOrFail();
        $admin = User::factory()->create(['role' => 'administrador']);

        $this->actingAs($admin)->post('/reservas/HH-TEST-0001/pagos/'.$payment->id.'/fecha', [
            'paid_at' => '2026-11-12T22:31', 'reason' => 'Se cobró en la máquina y se anotó tarde',
        ])->assertRedirect();

        $this->assertSame('2026-11-12 22:31', $payment->fresh()->created_at->timezone('America/Santiago')->format('Y-m-d H:i'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'pago.fecha', 'user_id' => $admin->id]);

        $this->actingAs($admin)->post('/reservas/HH-TEST-0001/pagos/'.$payment->id.'/fecha', [
            'paid_at' => now('America/Santiago')->addDay()->format('Y-m-d\TH:i'), 'reason' => 'Fecha futura no vale',
        ])->assertSessionHasErrors('booking');

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))
            ->post('/reservas/HH-TEST-0001/pagos/'.$payment->id.'/fecha', ['paid_at' => '2026-11-12T22:31', 'reason' => 'No debería poder'])
            ->assertForbidden();
    }

    public function test_staff_see_a_back_to_system_link_but_the_accountant_does_not(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']))->get('/contador?mes=2026-11')
            ->assertOk()->assertSee('Volver al sistema');
        $this->actingAs($this->contador())->get('/contador?mes=2026-11')
            ->assertOk()->assertDontSee('Volver al sistema');
    }

    public function test_the_accountant_opens_the_mercado_pago_page_without_the_operational_menu(): void
    {
        config(['services.mercadopago.access_token' => null]);

        $this->actingAs($this->contador())->get('/admin/mercado-pago?mes=2026-11')
            ->assertOk()
            ->assertSee('Mercado Pago')
            ->assertSee('Volver a mi panel')
            ->assertDontSee('Emitir boleta')
            ->assertDontSee('Ingreso presencial');

        // Administración sigue entrando a la suya, con su menú.
        $this->actingAs(User::factory()->create(['role' => 'administrador']))->get('/admin/mercado-pago')
            ->assertOk()->assertSee('Emitir boleta')->assertDontSee('Volver a mi panel');
    }

    public function test_the_accountant_can_open_the_read_only_sheet_of_a_booking(): void
    {
        $this->actingAs($this->contador())->get('/contador/reserva/HH-TEST-0001')
            ->assertOk()
            ->assertSee('HH-TEST-0001')
            ->assertSee('PLUS 307')
            ->assertSee('206')
            ->assertSee('$40.000');
        $this->get('/contador/reserva/NO-EXISTE')->assertNotFound();
        $this->post('/contador/reserva/HH-TEST-0001')->assertStatus(405);
    }

    public function test_the_accountant_cannot_reach_anything_else(): void
    {
        $this->actingAs($this->contador());

        foreach (['/caja', '/reservar', '/reservas/HH-TEST-0001', '/admin/categorias', '/ventas/reserva/HH-TEST-0001', '/resumen-mensual'] as $url) {
            $this->get($url)->assertRedirect(route('contador.index'));
        }
        $this->post('/reservas/HH-TEST-0001/finalizar')->assertRedirect(route('contador.index'));
    }

    public function test_other_profiles_do_not_get_in_the_accountant_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->get('/contador')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'marketing']))->get('/contador')->assertForbidden();
    }

    public function test_a_visitor_without_login_goes_to_login(): void
    {
        $this->get('/contador')->assertRedirect(route('login'));
    }

    public function test_exports_are_csv_for_excel_leave_an_audit_trail_and_neutralise_formulas(): void
    {
        $this->actingAs($this->contador());

        $ventas = $this->get('/contador/ventas?mes=2026-11&export=csv');
        $ventas->assertOk();
        $body = $ventas->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('HH-TEST-0001', $body);
        $this->assertStringContainsString('40000', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringNotContainsString(';=HYPERLINK', $body);

        $this->get('/contador?mes=2026-11&export=csv')->assertOk();
        $cobros = $this->get('/contador/cobros?mes=2026-11&export=csv')->streamedContent();
        $this->assertStringContainsString('206', $cobros);

        $this->assertSame(3, DB::table('audit_logs')->where('action', 'contador.exportar')->count());
    }

    public function test_reconciliation_explains_when_mercado_pago_is_not_connected(): void
    {
        config(['services.mercadopago.access_token' => null]);

        $this->actingAs($this->contador())->get('/contador/conciliacion?mes=2026-11')->assertOk();
    }

    public function test_reconciliation_shows_the_detail_of_the_chosen_day(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        // El cobro 01 de la máquina calza por N° de voucher con el pago registrado.
        Payment::query()->update(['voucher_number' => '1']);
        Cache::flush();
        Http::fake(['api.mercadopago.com/*' => Http::response(['results' => [[
            'id' => 123456789, 'status' => 'approved', 'date_approved' => '2026-11-21T12:06:00.000-03:00',
            'transaction_amount' => 40000, 'payment_type_id' => 'debit_card', 'authorization_code' => 'AUTH42',
            'point_of_interaction' => ['type' => 'POINT'], 'external_reference' => 'Venta presencial',
            'fee_details' => [['amount' => 756]], 'transaction_details' => ['net_received_amount' => 39244],
        ]]])]);

        $this->actingAs($this->contador())->get('/contador/conciliacion?mes=2026-11&dia=2026-11-21')
            ->assertOk()
            ->assertSee('Detalle del día')
            ->assertSee('123456789')
            ->assertSee('AUTH42')
            ->assertSee('Registrado')
            ->assertSee('PLUS 307');
    }
}
