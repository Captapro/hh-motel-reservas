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
 * BookingCheckInController::store() solo comprobaba operational_status de la
 * habitación, no si YA había otro huésped adentro (check-in real sin
 * check-out) -- si el anterior se pasaba de horario, se podía registrar el
 * check-in de la siguiente reserva encima. No había ningún test que lo
 * dejara fijo.
 */
class CheckInOccupancyTest extends TestCase
{
    private Room $room;

    private Customer $customer;

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
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090003_add_consumption_offered_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $this->customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'code' => 'HH-TEST-'.random_int(10000, 99999),
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'starts_at' => now()->addMinutes(5),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'guests_count' => 1,
            'booking_status' => 'CONFIRMADA',
            'payment_status' => 'PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
        ], $overrides));
    }

    public function test_check_in_is_blocked_while_room_still_has_a_guest_inside(): void
    {
        $this->makeBooking(['checked_in_at' => now()->subHour(), 'booking_status' => 'CHECK_IN']);
        $late = $this->makeBooking();

        $response = $this->post("/reservas/{$late->code}/checkin", []);

        $response->assertSessionHasErrors('booking');
        $this->assertNull($late->fresh()->checked_in_at);
    }

    public function test_check_in_succeeds_once_the_previous_guest_checked_out(): void
    {
        $this->makeBooking([
            'checked_in_at' => now()->subHours(2),
            'checked_out_at' => now()->subMinutes(10),
            'booking_status' => 'FINALIZADA',
        ]);
        $next = $this->makeBooking();

        $response = $this->post("/reservas/{$next->code}/checkin", []);

        $response->assertRedirect(route('rooms.board'));
        $this->assertNotNull($next->fresh()->checked_in_at);
    }
}
