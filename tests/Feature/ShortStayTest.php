<?php

namespace Tests\Feature;

use App\Exceptions\PricingException;
use App\Models\Customer;
use App\Models\RateRule;
use App\Models\RateRulePrice;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\ShortStaySetting;
use App\Services\Booking\BookingService;
use App\Services\Pricing\ShortStayPricer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Estadía corta por cierre: con menos tiempo para cerrar que la duración
 * mínima de la categoría, recepción la vende con salida a la hora de cierre
 * y precio proporcional (piso 50%).
 */
class ShortStayTest extends TestCase
{
    private Room $room;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-01 12:00'));
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
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_10_26_220000_create_late_arrival_settings_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_09_16_040000_add_wing_to_rooms.php',
            '2026_09_16_040100_create_operational_settings_table.php',
            '2026_09_17_002913_add_floor_toggles_to_operational_settings.php',
            '2026_09_21_135116_split_floor_toggles_by_wing_in_operational_settings.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $category = RoomCategory::create(['name' => 'LITE', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'LITE 301', 'operational_status' => 'activa']);
        $this->customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        // Lunes 20:00 a martes 03:00; lo mínimo que se vende son 3 h a $20.000.
        $rateRule = RateRule::create(['name' => 'HH', 'is_active' => true]);
        RateRuleWindow::create(['rate_rule_id' => $rateRule->id, 'weekday' => Carbon::parse('2026-10-05')->dayOfWeek, 'start_time' => '20:00:00', 'end_time' => '03:00:00', 'wraps_midnight' => true]);
        RateRulePrice::create(['rate_rule_id' => $rateRule->id, 'room_category_id' => $category->id, 'duration_minutes' => 180, 'price' => 20000]);
        RateRulePrice::create(['rate_rule_id' => $rateRule->id, 'room_category_id' => $category->id, 'duration_minutes' => 360, 'price' => 35000]);

        // Reglas amplias para estas pruebas: de 30 min a 2h30 antes del cierre, proporcional con piso 50%.
        ShortStaySetting::current()->update(['min_minutes' => 30, 'max_minutes' => 150, 'pricing_mode' => 'proporcional', 'percent' => 50]);
    }

    private function create(string $startsAt, int $duration, bool $shortStay)
    {
        return app(BookingService::class)->create([
            'room' => $this->room,
            'customer' => $this->customer,
            'starts_at' => Carbon::parse($startsAt),
            'duration_minutes' => $duration,
            'guests_count' => 2,
            'coupon_code' => null,
            'deposit_amount' => 0,
            'created_by' => null,
            'verified_by' => null,
            'notes' => null,
            'short_stay' => $shortStay,
        ]);
    }

    public function test_short_stay_ends_at_closing_with_proportional_price(): void
    {
        // Martes 01:00: quedan 2 h de un mínimo de 3 h -> 2/3 de $20.000, redondeado a la centena.
        $booking = $this->create('2026-10-06 01:00', 180, true);

        $this->assertTrue($booking->short_stay);
        $this->assertTrue(Carbon::parse('2026-10-06 03:00')->eq($booking->ends_at));
        $this->assertSame(180, (int) $booking->duration_minutes);
        $this->assertSame(20000, (int) $booking->price_original);
        $this->assertSame(13400, (int) $booking->price_final);
        $this->assertSame(ShortStayPricer::LABEL, $booking->coupon_code_snapshot);
    }

    public function test_price_never_goes_below_half(): void
    {
        // 02:15: quedan 45 min -> proporcional $5.000, pero el piso es el 50%.
        $booking = $this->create('2026-10-06 02:15', 180, true);

        $this->assertSame(10000, (int) $booking->price_final);
    }

    public function test_without_reception_accepting_it_is_still_rejected(): void
    {
        $this->expectException(PricingException::class);

        $this->create('2026-10-06 01:00', 180, false);
    }

    public function test_not_offered_with_too_little_time_or_a_longer_duration(): void
    {
        $pricer = app(ShortStayPricer::class);

        // Quedan 20 min: menos que el mínimo para ofrecerla.
        $this->assertNull($pricer->quote($this->room, Carbon::parse('2026-10-06 02:40'), 180));
        // Duración larga: solo se ofrece sobre la duración mínima de la categoría.
        $this->assertNull($pricer->quote($this->room, Carbon::parse('2026-10-06 01:00'), 360));
        // Fuera de horario.
        $this->assertNull($pricer->quote($this->room, Carbon::parse('2026-10-06 04:00'), 180));
    }

    public function test_a_flagged_booking_that_fits_completely_stays_normal(): void
    {
        $booking = $this->create('2026-10-05 22:00', 180, true);

        $this->assertFalse($booking->short_stay);
        $this->assertSame(20000, (int) $booking->price_final);
        $this->assertTrue(Carbon::parse('2026-10-06 01:00')->eq($booking->ends_at));
    }

    public function test_default_rule_only_offers_it_with_ninety_minutes_or_less(): void
    {
        ShortStaySetting::current()->update(['max_minutes' => 90]);
        $pricer = app(ShortStayPricer::class);

        // 01:00 -> quedan 2 h: más que la hora y media configurada.
        $this->assertNull($pricer->quote($this->room, Carbon::parse('2026-10-06 01:00'), 180));
        // 01:30 -> quedan 1h30 justo: se ofrece.
        $this->assertNotNull($pricer->quote($this->room, Carbon::parse('2026-10-06 01:30'), 180));
    }

    public function test_fixed_percentage_mode(): void
    {
        ShortStaySetting::current()->update(['pricing_mode' => 'porcentaje', 'percent' => 60]);

        $quote = app(ShortStayPricer::class)->quote($this->room, Carbon::parse('2026-10-06 02:15'), 180);

        $this->assertSame(12000, $quote['price']);
    }

    public function test_channels_can_be_turned_off_separately(): void
    {
        $pricer = app(ShortStayPricer::class);
        $start = Carbon::parse('2026-10-06 01:30');

        ShortStaySetting::current()->update(['catalog_enabled' => false]);
        $this->assertNull($pricer->quote($this->room, $start, 180, 'catalogo'));
        $this->assertNotNull($pricer->quote($this->room, $start, 180, 'recepcion'));

        ShortStaySetting::current()->update(['catalog_enabled' => true, 'enabled' => false]);
        $this->assertNull($pricer->quote($this->room, $start, 180, 'catalogo'));
        $this->assertNull($pricer->quote($this->room, $start, 180, 'recepcion'));
    }
}
