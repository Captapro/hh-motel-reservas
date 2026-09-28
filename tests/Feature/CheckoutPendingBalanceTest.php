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
use Tests\TestCase;

/**
 * Booking::balanceDue() y el bloqueo de check-out en BookingFinalizeController
 * son la garantía de que nunca se cierra una reserva con plata pendiente --
 * ninguno de los dos tenía un test que lo dejara fijo.
 */
class CheckoutPendingBalanceTest extends TestCase
{
    private Booking $booking;

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
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_19_090003_add_consumption_offered_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_26_120001_add_aseo_override_to_rooms_table.php',
            '2026_08_26_140001_add_aseo_started_to_rooms_table.php',
            '2026_09_15_233000_add_voucher_receipt_to_payments.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        $this->booking = Booking::create([
            'code' => 'HH-TEST-0001',
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'starts_at' => Carbon::parse('2026-09-21 15:00'),
            'ends_at' => Carbon::parse('2026-09-21 16:00'),
            'duration_minutes' => 60,
            'guests_count' => 2,
            'booking_status' => 'CHECK_IN',
            'payment_status' => 'NO_PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
            'checked_in_at' => now(),
        ]);
    }

    public function test_balance_due_reflects_unpaid_amount(): void
    {
        $this->assertSame(20000, $this->booking->balanceDue());

        PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        Payment::create([
            'booking_id' => $this->booking->id,
            'payment_method_id' => PaymentMethod::first()->id,
            'amount' => 8000,
            'status' => 'aprobado',
        ]);

        // Pago incompleto: quedó parte sin cubrir.
        $this->assertSame(12000, $this->booking->fresh()->balanceDue());
    }

    public function test_checkout_is_blocked_while_balance_is_pending(): void
    {
        $response = $this->post("/reservas/{$this->booking->code}/finalizar", [
            'confirm_room_checked' => '1',
        ]);

        $response->assertForbidden();
        $this->assertSame('CHECK_IN', $this->booking->fresh()->booking_status);
    }

    public function test_checkout_is_blocked_when_a_consumption_added_in_the_same_submit_leaves_a_balance(): void
    {
        // La reserva llega pagada al 100% a esta pantalla -- el saldo se
        // valida ANTES de agregar consumos, así que ese primer chequeo
        // pasa. El bug era que no se revalidaba DESPUÉS de agregar los
        // extras de este mismo envío.
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        Payment::create([
            'booking_id' => $this->booking->id,
            'payment_method_id' => $method->id,
            'amount' => 20000,
            'status' => 'aprobado',
        ]);
        $this->assertSame(0, $this->booking->fresh()->balanceDue());

        $product = \App\Models\Product::create(['name' => 'Espumante', 'price' => 15000, 'is_active' => true]);

        $response = $this->post("/reservas/{$this->booking->code}/finalizar", [
            'confirm_room_checked' => '1',
            'quantities' => [$product->id => 1],
        ]);

        $response->assertSessionHasErrors('booking');
        // back() mandaba de vuelta al mismo formulario de cierre, que
        // aborta con 403 en cuanto ve saldo pendiente -- una pantalla en
        // blanco en vez del error. Tiene que mandar a donde se resuelve.
        $response->assertRedirect(route('payments.create', $this->booking->code));
        $this->assertSame('CHECK_IN', $this->booking->fresh()->booking_status);
        $this->assertSame(15000, $this->booking->fresh()->balanceDue());
    }

    public function test_checkout_succeeds_once_fully_paid(): void
    {
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        Payment::create([
            'booking_id' => $this->booking->id,
            'payment_method_id' => $method->id,
            'amount' => 20000,
            'status' => 'aprobado',
        ]);

        $response = $this->post("/reservas/{$this->booking->code}/finalizar", [
            'confirm_room_checked' => '1',
        ]);

        $response->assertRedirect();
        $fresh = $this->booking->fresh();
        $this->assertSame('FINALIZADA', $fresh->booking_status);
        // Al finalizar, la habitación queda en aseo -- no vuelve a "activa" sola.
        $this->assertSame('aseo', $fresh->room->fresh()->operational_status);
    }

    public function test_extra_hour_and_consumption_are_rejected_on_a_finalized_booking(): void
    {
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        Payment::create([
            'booking_id' => $this->booking->id,
            'payment_method_id' => $method->id,
            'amount' => 20000,
            'status' => 'aprobado',
        ]);
        $this->post("/reservas/{$this->booking->code}/finalizar", ['confirm_room_checked' => '1'])->assertRedirect();
        $finalized = $this->booking->fresh();
        $this->assertSame('FINALIZADA', $finalized->booking_status);
        $originalEndsAt = $finalized->ends_at;

        $hourResponse = $this->post("/reservas/{$this->booking->code}/hora-adicional");
        $hourResponse->assertSessionHasErrors('booking');
        $this->assertTrue($originalEndsAt->eq($finalized->fresh()->ends_at));

        $product = \App\Models\Product::create(['name' => 'Espumante', 'price' => 15000, 'is_active' => true]);
        $addonResponse = $this->post("/reservas/{$this->booking->code}/consumo", ['item' => 'product:'.$product->id, 'quantity' => 1]);
        $addonResponse->assertSessionHasErrors('booking');
        $this->assertSame(0, $finalized->fresh()->addons()->count());
    }
}
