<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cancelar pone la reserva en un estado terminal -- RoomBoardService la deja
 * de contar como ocupación real de inmediato (ver su statusFor()), y
 * operational_status nunca pasa a "aseo" en este flujo. Con check-in ya
 * hecho eso "libera" la pieza en el sistema sin que el huésped haya hecho
 * check-out de verdad ni haya pasado por aseo -- tiene que salir por
 * "Finalizar", no por "Cancelar".
 */
class BookingCancelTest extends TestCase
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
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        $this->booking = Booking::create([
            'code' => 'HH-TEST-CANCEL',
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'starts_at' => Carbon::parse('2026-09-21 15:00'),
            'ends_at' => Carbon::parse('2026-09-21 17:00'),
            'duration_minutes' => 120,
            'guests_count' => 2,
            'booking_status' => 'CHECK_IN',
            'payment_status' => 'PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
            'checked_in_at' => now(),
        ]);
    }

    public function test_cancel_is_blocked_once_checked_in(): void
    {
        $response = $this->post("/reservas/{$this->booking->code}/cancelar", ['reason' => 'intento']);

        $response->assertSessionHasErrors('booking');
        $this->assertSame('CHECK_IN', $this->booking->fresh()->booking_status);
    }

    public function test_cancel_still_works_before_check_in(): void
    {
        $this->booking->update(['checked_in_at' => null, 'booking_status' => 'CONFIRMADA']);

        $response = $this->post("/reservas/{$this->booking->code}/cancelar", ['reason' => 'cliente no llegó']);

        $response->assertRedirect(route('reservations.show', $this->booking->code));
        $this->assertSame('CANCELADA', $this->booking->fresh()->booking_status);
    }
}
