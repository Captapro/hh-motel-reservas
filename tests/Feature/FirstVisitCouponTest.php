<?php

namespace Tests\Feature;

use App\Exceptions\InvalidCouponException;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Pricing\CouponValidator;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El HH Secret Pass (bienvenida que ofrece Cata) solo vale para quien todavía
 * no nos conoce: un cliente con una reserva viva o pasada ya no lo puede usar;
 * un intento cancelado o vencido sin pagar no cuenta como visita.
 */
class FirstVisitCouponTest extends TestCase
{
    private Room $room;

    private Coupon $coupon;

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
            '2026_08_18_120009_create_coupon_rooms_table.php',
            '2026_09_10_130000_add_auto_apply_offers_to_coupons.php',
            '2026_09_11_090001_add_min_age_to_coupons_table.php',
            '2026_09_16_000000_add_included_extra_guests_to_coupons.php',
            '2026_11_01_030000_add_is_public_to_coupons.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        // Columnas customer_id/origin de cupones (su migración trae otras tablas que esta prueba no necesita).
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable();
            $table->string('origin', 30)->nullable();
        });
        (require database_path('migrations/2026_11_12_090000_create_first_visit_welcome_coupon.php'))->up();

        $category = RoomCategory::create(['name' => 'NEW LITE', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'NEW LITE 101', 'operational_status' => 'activa']);
        $this->coupon = Coupon::where('code', 'SECRETPASS')->firstOrFail();
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Cliente', 'phone_e164' => '+569'.random_int(10000000, 99999999)]);
    }

    private function booking(Customer $customer, string $status): Booking
    {
        return Booking::create([
            'code' => 'HH-'.random_int(1000, 9999), 'customer_id' => $customer->id, 'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-11-01 18:00'), 'ends_at' => Carbon::parse('2026-11-01 21:00'),
            'duration_minutes' => 180, 'guests_count' => 2, 'booking_status' => $status, 'payment_status' => 'PAGADA',
            'price_original' => 32000, 'price_final' => 32000, 'origin' => 'catalogo',
        ]);
    }

    private function validate(?Customer $customer, ?int $excludeBookingId = null): int
    {
        return app(CouponValidator::class)->validate($this->coupon, $this->room, Carbon::parse('2026-11-20 18:00'), 180, $customer, 32000, $excludeBookingId);
    }

    public function test_the_welcome_coupon_exists_hidden_from_the_catalog(): void
    {
        $this->assertSame('primera_visita', $this->coupon->origin);
        $this->assertFalse((bool) $this->coupon->is_public);
        $this->assertSame(15, (int) $this->coupon->discount_value);
    }

    public function test_a_new_customer_gets_fifteen_percent(): void
    {
        $this->assertSame(4800, $this->validate($this->customer()));
        $this->assertSame(4800, $this->validate(null));
    }

    public function test_a_cancelled_or_expired_attempt_does_not_count_as_a_visit(): void
    {
        $customer = $this->customer();
        $this->booking($customer, 'CANCELADA');
        $this->booking($customer, 'EXPIRADA');

        $this->assertSame(4800, $this->validate($customer));
    }

    public function test_the_same_booking_being_edited_does_not_block_it(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer, 'CONFIRMADA');

        $this->assertSame(4800, $this->validate($customer, $booking->id));
    }

    public function test_someone_who_already_came_cannot_use_it(): void
    {
        $customer = $this->customer();
        $this->booking($customer, 'FINALIZADA');

        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('primera visita');
        $this->validate($customer);
    }
}
