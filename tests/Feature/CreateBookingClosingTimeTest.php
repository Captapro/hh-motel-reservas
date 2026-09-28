<?php

namespace Tests\Feature;

use App\Exceptions\RoomNotAvailableException;
use App\Models\Customer;
use App\Models\RateRule;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BookingService::create() calcula el precio contra baseEndsAt (starts_at +
 * duration_minutes), pero el upsell "más tiempo" (extra_minutes) alarga la
 * ocupación real por encima de eso sin volver a comprobar el horario
 * operativo -- se podía crear una reserva "02:00 a 03:00 + 60 min" con
 * cierre a las 03:00, guardada hasta las 04:00.
 */
class CreateBookingClosingTimeTest extends TestCase
{
    private Room $room;

    private Customer $customer;

    private Carbon $starts;

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
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120007_create_calendar_overrides_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120009_create_coupon_rooms_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $this->customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        $this->starts = Carbon::parse('2026-10-05 23:30'); // lunes 23:30

        $rateRule = RateRule::create(['name' => 'HH', 'is_active' => true]);
        // Ventana lunes 00:00-03:00 y otra el mismo lunes desde las 20:00
        // hasta medianoche, envolviendo al martes -- así 23:30+3h (02:30)
        // cabe, pero 23:30+3h+1h extra (03:30) ya no.
        RateRuleWindow::create(['rate_rule_id' => $rateRule->id, 'weekday' => $this->starts->dayOfWeek, 'start_time' => '20:00:00', 'end_time' => '03:00:00', 'wraps_midnight' => true]);
        \App\Models\RateRulePrice::create(['rate_rule_id' => $rateRule->id, 'room_category_id' => $category->id, 'duration_minutes' => 180, 'price' => 20000]);
    }

    public function test_extra_time_at_booking_creation_is_rejected_when_it_crosses_the_closing_time(): void
    {
        $svc = app(BookingService::class);

        $this->expectException(RoomNotAvailableException::class);
        $this->expectExceptionMessage('pasa la hora de cierre');

        $svc->create([
            'room' => $this->room,
            'customer' => $this->customer,
            'starts_at' => $this->starts,
            'duration_minutes' => 180,
            'extra_minutes' => 60,
            'guests_count' => 2,
            'coupon_code' => null,
            'deposit_amount' => 0,
            'created_by' => null,
            'verified_by' => null,
            'notes' => null,
        ]);
    }

    public function test_booking_creation_without_extra_time_still_works(): void
    {
        $svc = app(BookingService::class);

        $booking = $svc->create([
            'room' => $this->room,
            'customer' => $this->customer,
            'starts_at' => $this->starts,
            'duration_minutes' => 180,
            'extra_minutes' => 0,
            'guests_count' => 2,
            'coupon_code' => null,
            'deposit_amount' => 0,
            'created_by' => null,
            'verified_by' => null,
            'notes' => null,
        ]);

        $this->assertTrue($this->starts->copy()->addMinutes(180)->eq($booking->ends_at));
    }
}
