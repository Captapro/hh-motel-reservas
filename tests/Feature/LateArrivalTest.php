<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\LateArrivalSetting;
use App\Models\RateRule;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Booking\LateArrivalPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PaysBookings;
use Tests\TestCase;

/**
 * Regla de atrasos al check-in (Administración → Atrasos): dentro de la
 * tolerancia el contador parte de cero; pasada, se descuenta el exceso o todo
 * el atraso, y la salida nunca se corre más allá del cierre ni encima de la
 * siguiente reserva.
 */
class LateArrivalTest extends TestCase
{
    use PaysBookings;

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
            '2026_08_18_120005_create_rate_rule_windows_table.php',
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120007_create_calendar_overrides_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_09_11_090000_add_identity_fields_to_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_10_26_220000_create_late_arrival_settings_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090003_add_consumption_offered_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));

        $category = RoomCategory::create(['name' => 'LITE', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'LITE 301', 'operational_status' => 'activa']);
        $this->customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111', 'document_type' => 'rut', 'rut' => '11111111-1', 'nationality' => 'Chilena']);

        // Lunes 10:00 a 23:30.
        $rule = RateRule::create(['name' => 'HH', 'is_active' => true]);
        RateRuleWindow::create(['rate_rule_id' => $rule->id, 'weekday' => 1, 'start_time' => '10:00:00', 'end_time' => '23:30:00', 'wraps_midnight' => false]);
    }

    /** Reserva de 3 h, lunes 20:00 a 23:00. */
    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'code' => 'HH-TEST-'.random_int(10000, 99999),
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-10-05 20:00'),
            'ends_at' => Carbon::parse('2026-10-05 23:00'),
            'duration_minutes' => 180,
            'guests_count' => 1,
            'booking_status' => 'CONFIRMADA',
            'payment_status' => 'PAGADA',
            'price_original' => 20000,
            'price_final' => 20000,
        ], $overrides));
    }

    private function resolveAt(Booking $booking, string $arrival): array
    {
        return app(LateArrivalPolicy::class)->resolve($booking->load('room'), Carbon::parse($arrival));
    }

    public function test_within_tolerance_the_counter_starts_from_zero(): void
    {
        $late = $this->resolveAt($this->booking(), '2026-10-05 20:12');

        $this->assertSame(12, $late['delay']);
        $this->assertSame(0, $late['lost']);
        $this->assertSame('23:12', $late['ends_at']->format('H:i'));
    }

    public function test_past_tolerance_only_the_excess_is_discounted_by_default(): void
    {
        $late = $this->resolveAt($this->booking(), '2026-10-05 20:40');

        $this->assertSame(25, $late['lost']);
        $this->assertSame('23:15', $late['ends_at']->format('H:i'));
    }

    public function test_mode_todo_discounts_the_whole_delay(): void
    {
        LateArrivalSetting::current()->update(['mode' => 'todo']);

        $late = $this->resolveAt($this->booking(), '2026-10-05 20:40');

        $this->assertSame(40, $late['lost']);
        $this->assertSame('23:00', $late['ends_at']->format('H:i'));
    }

    public function test_exit_is_never_moved_past_the_closing_time(): void
    {
        // 21:00 a 23:30 (cierre): llega 10 min tarde, pero no se puede correr la salida.
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 21:00'), 'ends_at' => Carbon::parse('2026-10-05 23:30'), 'duration_minutes' => 150]);

        $late = $this->resolveAt($booking, '2026-10-05 21:10');

        $this->assertTrue($late['blocked']);
        $this->assertSame(10, $late['lost']);
        $this->assertSame('23:30', $late['ends_at']->format('H:i'));
    }

    public function test_check_in_applies_the_rule(): void
    {
        $booking = $this->booking();
        $this->travelTo(Carbon::parse('2026-10-05 20:40'));

        $this->payRoom($booking)->post("/reservas/{$booking->code}/checkin", [])->assertSessionHasNoErrors();

        $this->assertSame('23:15', $booking->fresh()->ends_at->format('H:i'));
    }
}
