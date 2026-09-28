<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\RateRule;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Vender 1 hora adicional" (BookingAddonController::extraHour) solo
 * comprobaba que la pieza no chocara con otra reserva -- no que la nueva
 * hora de salida siguiera dentro del horario operativo de la tarifa. Se
 * podía cobrar y dejar una reserva "abierta" en el sistema después de la
 * hora de cierre, algo que ni crear ni reprogramar una reserva permiten.
 */
class ExtraHourClosingTimeTest extends TestCase
{
    private Booking $booking;

    private Carbon $closesAt;

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
            '2026_08_18_120005_create_rate_rule_windows_table.php',
            '2026_08_18_120007_create_calendar_overrides_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_19_090003_add_consumption_offered_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_09_16_001000_add_extra_hour_price_to_rate_rules.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        $starts = Carbon::parse('2026-10-05 01:00'); // lunes
        $this->closesAt = Carbon::parse('2026-10-05 03:00');

        $rateRule = RateRule::create(['name' => 'HH', 'is_active' => true]);
        RateRuleWindow::create([
            'rate_rule_id' => $rateRule->id,
            'weekday' => $starts->dayOfWeek,
            'start_time' => '00:00:00',
            'end_time' => '03:00:00',
            'wraps_midnight' => false,
        ]);

        $this->booking = Booking::create([
            'code' => 'HH-TEST-HOUR',
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'starts_at' => $starts,
            'ends_at' => Carbon::parse('2026-10-05 02:30'),
            'duration_minutes' => 90,
            'guests_count' => 2,
            'booking_status' => 'CHECK_IN',
            'payment_status' => 'PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
            'rate_rule_name_snapshot' => 'HH',
            'checked_in_at' => now(),
        ]);
    }

    public function test_extra_hour_is_rejected_when_it_crosses_the_closing_time(): void
    {
        // 02:30 + 1h = 03:30, pasa el cierre de las 03:00.
        $response = $this->post("/reservas/{$this->booking->code}/hora-adicional");

        $response->assertSessionHasErrors('booking');
        $this->assertTrue($this->closesAt->copy()->subMinutes(30)->eq($this->booking->fresh()->ends_at));
        $this->assertSame(0, $this->booking->fresh()->addons()->count());
    }

    public function test_extra_hour_succeeds_when_it_stays_within_the_closing_time(): void
    {
        $this->booking->update(['ends_at' => Carbon::parse('2026-10-05 01:30')]);

        // 01:30 + 1h = 02:30, todavía antes del cierre de las 03:00.
        $response = $this->post("/reservas/{$this->booking->code}/hora-adicional");

        $response->assertSessionDoesntHaveErrors();
        $this->assertTrue(Carbon::parse('2026-10-05 02:30')->eq($this->booking->fresh()->ends_at));
        $this->assertSame(1, $this->booking->fresh()->addons()->count());
    }
}
