<?php

namespace Tests\Feature;

use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\OperationalSetting;
use App\Models\PaymentMethod;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\UpsellOffer;
use App\Models\User;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\BookingService;
use App\Services\Integrations\GhlBookingSync;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingIntegrityTest extends TestCase
{
    private Room $room;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->travelTo(Carbon::parse('2026-10-01 12:00'));
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
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_09_10_130000_add_auto_apply_offers_to_coupons.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
            '2026_09_11_090001_add_min_age_to_coupons_table.php',
            '2026_09_15_233000_add_voucher_receipt_to_payments.php',
            '2026_09_16_000000_add_included_extra_guests_to_coupons.php',
            '2026_09_16_040000_add_wing_to_rooms.php',
            '2026_09_16_040100_create_operational_settings_table.php',
            '2026_09_17_002913_add_floor_toggles_to_operational_settings.php',
            '2026_09_21_135116_split_floor_toggles_by_wing_in_operational_settings.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
            '2026_09_29_100000_add_request_token_to_payments.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $category = RoomCategory::create(['name' => 'GO', 'base_capacity' => 2, 'is_active' => true]);
        $this->room = Room::create(['name' => 'GO 101', 'room_category_id' => $category->id, 'operational_status' => 'activa', 'buffer_minutes' => 30]);
        $this->customer = Customer::create(['name' => 'Cliente de prueba', 'phone_e164' => '+56911111111']);
        $rate = RateRule::create(['name' => 'HH', 'is_active' => true, 'extra_person_price' => 10000]);
        $rate->windows()->create(['weekday' => 1, 'start_time' => '00:00:00', 'end_time' => '00:00:00', 'wraps_midnight' => true]);
        $rate->prices()->create(['room_category_id' => $category->id, 'duration_minutes' => 60, 'price' => 20000]);
        $this->mock(GhlBookingSync::class, fn ($mock) => $mock->shouldReceive('sync')->andReturnNull());
    }

    private function booking(array $overrides = []): Booking
    {
        return app(BookingService::class)->create(array_merge([
            'room' => $this->room, 'customer' => $this->customer, 'starts_at' => Carbon::parse('2026-10-05 12:00'),
            'duration_minutes' => 60, 'guests_count' => 2,
        ], $overrides));
    }

    private function offer(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge(['internal_name' => 'Oferta prueba', 'auto_apply' => true, 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 20], $overrides));
    }

    public function test_fixed_price_with_included_guest_is_not_discounted_twice(): void
    {
        $this->offer(['discount_type' => 'precio_fijo', 'discount_value' => 15000, 'included_extra_guests' => 1]);
        $booking = $this->booking(['guests_count' => 3]);
        $this->assertSame(15000, $booking->price_final);
        $this->assertSame(0, $booking->extra_guests_fee);
        $response = $this->getJson('/reservar/precio?'.http_build_query(['room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 12, 'time_minute' => 0, 'duration_minutes' => 60, 'guests_count' => 3]));
        $response->assertOk()->assertJsonPath('applied.price_final', 15000);
    }

    public function test_percentage_is_applied_after_included_guest_and_best_offer_uses_total_saving(): void
    {
        $this->offer(['discount_type' => 'fixed', 'discount_value' => 6000]);
        $best = $this->offer(['discount_value' => 10, 'included_extra_guests' => 1]);
        $booking = $this->booking(['guests_count' => 3]);
        $this->assertSame($best->id, $booking->coupon_id);
        $this->assertSame(18000, $booking->price_final);
    }

    public function test_rescheduling_excludes_own_redemption_from_both_limits(): void
    {
        $offer = $this->offer(['max_uses_total' => 1, 'max_uses_per_customer' => 1]);
        $booking = $this->booking();
        $updated = app(BookingService::class)->reschedule($booking, ['room' => $this->room, 'starts_at' => $booking->starts_at, 'duration_minutes' => 60, 'guests_count' => 2]);
        $this->assertSame(16000, $updated->price_final);
        $this->assertSame($offer->id, $updated->coupon_id);
        $this->assertSame(1, $offer->redemptions()->count());
    }

    public function test_moving_checked_in_booking_to_overdue_occupied_room_is_rejected(): void
    {
        $booking = $this->booking();
        $booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        $target = Room::create(['name' => 'GO 102', 'room_category_id' => $this->room->room_category_id, 'operational_status' => 'activa']);
        $old = $booking->replicate();
        $old->fill(['code' => 'OLD', 'pass_token' => Str::random(40), 'room_id' => $target->id, 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()])->save();
        try {
            app(BookingService::class)->reschedule($booking, ['room' => $target, 'starts_at' => $booking->starts_at, 'duration_minutes' => 60, 'guests_count' => 2]);
            $this->fail('El traslado debe rechazarse');
        } catch (RoomNotAvailableException $e) {
            $this->assertSame($this->room->id, $booking->fresh()->room_id);
        }
    }

    public function test_buffer_is_preserved_and_exact_boundary_is_allowed(): void
    {
        $this->booking();
        try {
            $this->booking(['starts_at' => Carbon::parse('2026-10-05 13:10')]);
            $this->fail('El margen debe respetarse');
        } catch (RoomNotAvailableException $e) {
            $this->assertSame(1, Booking::count());
        }
        $this->booking(['starts_at' => Carbon::parse('2026-10-05 13:30')]);
        $this->assertSame(2, Booking::count());
    }

    private function paymentData(int $amount): array
    {
        $method = PaymentMethod::firstOrCreate(['code' => 'efectivo'], ['name' => 'Efectivo']);

        return ['payment_method_id' => $method->id, 'amount' => $amount, 'receipt_number' => '123', 'request_token' => (string) Str::uuid()];
    }

    public function test_repeated_partial_payment_without_optional_reference_is_idempotent(): void
    {
        $booking = $this->booking();
        $data = $this->paymentData(5000);
        foreach ([1, 2] as $_) {
            $this->post('/reservas/'.$booking->code.'/pago', $data)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(5000, $booking->paidAmount());
        $this->assertSame(1, $booking->payments()->count());
        $this->post('/reservas/'.$booking->code.'/pago', $this->paymentData(5000))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000, $booking->paidAmount());
    }

    public function test_full_payment_replay_succeeds_and_changed_payload_is_rejected(): void
    {
        $booking = $this->booking();
        $data = $this->paymentData(20000);
        $this->post('/reservas/'.$booking->code.'/pago', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/reservas/'.$booking->code.'/pago', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/reservas/'.$booking->code.'/pago', array_replace($data, ['amount' => 10000]))->assertSessionHasErrors('amount');
        $this->assertSame(20000, $booking->paidAmount());
    }

    public function test_cancel_without_optional_reason_succeeds(): void
    {
        $booking = $this->booking();
        $this->post('/reservas/'.$booking->code.'/cancelar')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('CANCELADA', $booking->fresh()->booking_status);
    }

    public function test_checkin_upgrade_revalidates_origin_category_and_floor(): void
    {
        $booking = $this->booking(['guests_count' => 1]);
        $targetCategory = RoomCategory::create(['name' => 'MAX', 'is_active' => true]);
        $wrongOrigin = RoomCategory::create(['name' => 'PLUS']);
        $target = Room::create(['name' => 'MAX 301', 'wing' => 'norte', 'room_category_id' => $targetCategory->id, 'operational_status' => 'activa']);
        $offer = UpsellOffer::create(['name' => 'Upgrade', 'type' => 'category_upgrade', 'from_room_category_id' => $wrongOrigin->id, 'to_room_category_id' => $targetCategory->id, 'price' => 1000, 'is_active' => true]);
        $allocator = app(BookingAllocationService::class);
        $this->assertFalse($allocator->upgrade($booking, $offer, auth()->id()));
        $offer->update(['from_room_category_id' => $this->room->room_category_id]);
        $targetCategory->update(['is_active' => false]);
        $this->assertFalse($allocator->upgrade($booking, $offer, auth()->id()));
        $targetCategory->update(['is_active' => true]);
        OperationalSetting::current()->update(['piso_3_norte_enabled' => false]);
        $this->assertFalse($allocator->upgrade($booking, $offer, auth()->id()));
        OperationalSetting::current()->update(['piso_3_norte_enabled' => true]);
        $this->post('/reservas/'.$booking->code.'/checkin', ['accepted_upsells' => [$offer->id]])->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $this->assertSame($target->id, $booking->fresh()->room_id);
        $this->assertSame(1000, $booking->addonsTotal());
    }
}
