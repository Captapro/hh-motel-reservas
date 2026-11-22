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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dos veces en dos días recepción anotó un N° de voucher que ya estaba usado
 * (PLUS 307 el 21 y el 22 de noviembre): el cruce con Mercado Pago deja de
 * calzar y parece que falta plata aunque esté todo cobrado. Acá queda fijo que
 * el formulario avisa antes de guardar, y que igual deja seguir cuando de
 * verdad es un solo cobro para dos piezas.
 */
class DuplicateVoucherTest extends TestCase
{
    private Booking $booking;

    private PaymentMethod $debito;

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
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_11_10_150000_add_stock_deducted_at_to_booking_addons.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
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
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $category = RoomCategory::create(['name' => 'PLUS', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'PLUS 307', 'operational_status' => 'activa']);
        $otra = Room::create(['room_category_id' => $category->id, 'name' => 'PLUS 206', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);
        $this->debito = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito', 'is_active' => true]);

        $hacerReserva = fn (string $code, Room $r, int $precio) => Booking::create([
            'code' => $code,
            'customer_id' => $customer->id,
            'room_id' => $r->id,
            'starts_at' => Carbon::parse('2026-11-21 15:00'),
            'ends_at' => Carbon::parse('2026-11-21 18:00'),
            'duration_minutes' => 180,
            'guests_count' => 2,
            'booking_status' => 'CHECK_IN',
            'payment_status' => 'NO_PAGADA',
            'price_original' => $precio,
            'price_final' => $precio,
            'checked_in_at' => now(),
        ]);

        // La pieza de al lado ya cobró con el voucher 206 hoy.
        $vecina = $hacerReserva('HH-TEST-0002', $otra, 45000);
        Payment::create([
            'booking_id' => $vecina->id,
            'payment_method_id' => $this->debito->id,
            'amount' => 45000,
            'status' => 'aprobado',
            'voucher_number' => '206',
            'external_id' => 'REC-TEST-0001',
        ]);

        $this->booking = $hacerReserva('HH-TEST-0001', $room, 40000);
    }

    private function pagar(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post("/reservas/{$this->booking->code}/pago", [
            'payment_method_id' => $this->debito->id,
            'amount' => 40000,
            'request_token' => (string) Str::uuid(),
            'voucher_number' => '206',
            ...$extra,
        ]);
    }

    public function test_it_warns_when_the_voucher_number_is_already_used_this_month(): void
    {
        $this->pagar()->assertSessionHasErrors('voucher_number');

        $this->assertSame(0, $this->booking->fresh()->payments()->count());
        $this->assertStringContainsString('HH-TEST-0002 (PLUS 206)', session('errors')->first('voucher_number'));
        $this->assertTrue(session('voucher_repetido'));
    }

    public function test_a_different_voucher_number_goes_straight_through(): void
    {
        $this->pagar(['voucher_number' => '207'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->booking->fresh()->payments()->count());
    }

    public function test_one_machine_charge_for_two_rooms_can_be_confirmed(): void
    {
        $this->pagar(['voucher_ok' => '1'])->assertSessionHasNoErrors();

        $this->assertSame('206', $this->booking->fresh()->payments()->first()->voucher_number);
    }

    public function test_a_voucher_from_last_month_does_not_warn(): void
    {
        Payment::where('voucher_number', '206')->update(['created_at' => now()->subMonth()]);

        $this->pagar()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->booking->fresh()->payments()->count());
    }
}
