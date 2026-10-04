<?php

namespace Tests\Feature;

use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BookingCancelController;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\OperationalSetting;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\UpsellOffer;
use App\Models\User;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\ConsumptionService;
use App\Services\Booking\PaymentService;
use App\Services\Integrations\GhlBookingSync;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            '2026_10_04_130000_add_max_capacity_to_room_categories.php',
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

    public function test_booking_over_the_category_max_capacity_is_rejected(): void
    {
        $this->room->category->update(['max_capacity' => 4]);

        $ok = $this->booking(['guests_count' => 4]);
        $this->assertSame(4, $ok->guests_count);

        $this->expectException(RoomNotAvailableException::class);
        $this->expectExceptionMessage('admite como máximo 4 personas');
        $this->booking(['guests_count' => 5, 'starts_at' => Carbon::parse('2026-10-12 12:00')]);
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

    public function test_internal_form_rejects_disabled_category_and_floor(): void
    {
        (require database_path('migrations/2026_09_11_090000_add_identity_fields_to_customers_table.php'))->up();
        $data = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 12, 'time_minute' => 0,
            'duration_minutes' => 60, 'guests_count' => 1, 'customer_first_name' => 'Cliente',
            'customer_last_name' => 'Prueba', 'customer_phone' => $this->customer->phone_e164,
            'customer_email' => 'test@example.test', 'document_type' => 'pasaporte', 'document_number' => 'TEST123',
        ];
        $this->room->category->update(['is_active' => false]);
        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());
        $this->room->category->update(['is_active' => true]);
        OperationalSetting::current()->update(['piso_1_enabled' => false]);
        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());
        OperationalSetting::current()->update(['piso_1_enabled' => true]);
        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
    }

    public function test_reschedule_rejects_disabled_category_and_floor_without_changes(): void
    {
        $booking = $this->booking();
        $originalStart = $booking->starts_at->copy();
        $data = ['room' => $this->room, 'starts_at' => $originalStart->copy()->addHour(), 'duration_minutes' => 60, 'guests_count' => 2];
        foreach (['category', 'floor'] as $disabled) {
            $this->room->category->update(['is_active' => $disabled !== 'category']);
            OperationalSetting::current()->update(['piso_1_enabled' => $disabled !== 'floor']);
            try {
                app(BookingService::class)->reschedule($booking, $data);
                $this->fail('No debe aceptar un destino deshabilitado');
            } catch (RoomNotAvailableException $e) {
                $this->assertTrue($originalStart->eq($booking->fresh()->starts_at));
            }
        }
    }

    public function test_future_upgrade_allows_guest_today_but_checked_in_move_does_not(): void
    {
        $booking = $this->booking();
        $category = RoomCategory::create(['name' => 'PLUS', 'base_capacity' => 2, 'is_active' => true]);
        $target = Room::create(['name' => 'PLUS 102', 'room_category_id' => $category->id, 'operational_status' => 'activa']);
        $occupied = $booking->replicate();
        $occupied->fill([
            'code' => 'TODAY', 'pass_token' => Str::random(40), 'room_id' => $target->id,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
            'checked_in_at' => now()->subHour(), 'booking_status' => 'CHECK_IN',
        ])->save();
        $offer = UpsellOffer::create([
            'name' => 'Upgrade', 'type' => 'category_upgrade', 'from_room_category_id' => $this->room->room_category_id,
            'to_room_category_id' => $category->id, 'price' => 1000, 'is_active' => true,
        ]);
        $booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        $this->assertFalse(app(BookingAllocationService::class)->upgrade($booking, $offer, auth()->id()));
        $this->assertSame(0, $booking->addonsTotal());
        $booking->update(['checked_in_at' => null, 'booking_status' => 'CONFIRMADA']);
        $this->assertTrue(app(BookingAllocationService::class)->upgrade($booking, $offer, auth()->id()));
        $this->assertSame($target->id, $booking->fresh()->room_id);
        $this->assertSame(1000, $booking->addonsTotal());
    }

    public function test_saving_without_changes_does_not_reprice_an_upgraded_booking(): void
    {
        $booking = $this->booking(); // GO, 60 min, price_final = 20000
        $maxCategory = RoomCategory::create(['name' => 'MAX', 'base_capacity' => 2, 'is_active' => true]);
        $maxRoom = Room::create(['name' => 'MAX 301', 'room_category_id' => $maxCategory->id, 'operational_status' => 'activa']);
        // No hay tarifa MAX cargada a propósito: si reschedule() volviera a
        // cotizar por la categoría actual de la pieza (MAX) en vez de saltarse
        // el recálculo cuando no hay cambios reales, esto tiraría
        // PricingException en vez de silenciosamente subir el precio -- de
        // cualquier forma de las dos, guardar sin tocar nada no debe fallar
        // ni recotizar.
        $booking->update(['room_id' => $maxRoom->id]); // simula el traslado que hace un upgrade, sin tocar price_final
        $originalPriceFinal = $booking->price_final;

        $updated = app(BookingService::class)->reschedule($booking->fresh(), [
            'room' => $maxRoom, 'starts_at' => $booking->starts_at, 'duration_minutes' => $booking->duration_minutes, 'guests_count' => $booking->guests_count,
        ]);

        $this->assertSame($originalPriceFinal, $updated->price_final);
        $this->assertSame($maxRoom->id, $updated->room_id);
    }

    public function test_reschedule_with_real_changes_still_reprices(): void
    {
        $booking = $this->booking();
        $updated = app(BookingService::class)->reschedule($booking, [
            'room' => $this->room, 'starts_at' => $booking->starts_at->copy()->addHour(), 'duration_minutes' => 60, 'guests_count' => 2,
        ]);
        $this->assertTrue($booking->starts_at->copy()->addHour()->eq($updated->starts_at));
    }

    public function test_repeated_reference_from_a_different_form_is_rejected_not_a_500(): void
    {
        $bookingA = $this->booking();
        $bookingB = $this->booking(['starts_at' => Carbon::parse('2026-10-12 12:00')]); // otro lunes, misma ventana horaria
        $method = PaymentMethod::firstOrCreate(['code' => 'transferencia'], ['name' => 'Transferencia']);

        $this->post('/reservas/'.$bookingA->code.'/pago', [
            'payment_method_id' => $method->id, 'amount' => 5000, 'receipt_number' => '123',
            'external_id' => 'TRANSF-9999', 'request_token' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Formulario DISTINTO (otro request_token, como si fuera otra pestaña
        // u otra reserva) con la MISMA referencia -- antes chocaba contra el
        // índice único de external_id sin capturar y tiraba 500.
        $response = $this->post('/reservas/'.$bookingB->code.'/pago', [
            'payment_method_id' => $method->id, 'amount' => 5000, 'receipt_number' => '456',
            'external_id' => 'TRANSF-9999', 'request_token' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors('external_id');
        $this->assertSame(0, $bookingB->fresh()->paidAmount());
    }

    public function test_cancellation_rechecks_checkin_after_request_validation(): void
    {
        $booking = $this->booking();
        // Simula el check-in de otra petición mientras se valida el formulario.
        $request = new class($booking->id) extends Request
        {
            public function __construct(private int $bookingId)
            {
                parent::__construct();
            }

            public function validate(array $rules, ...$params): array
            {
                DB::table('bookings')->where('id', $this->bookingId)->update([
                    'checked_in_at' => now(), 'booking_status' => 'CHECK_IN',
                ]);

                return [];
            }
        };
        app(BookingCancelController::class)->store($request, $booking->code);
        $this->assertSame('CHECK_IN', $booking->fresh()->booking_status);
        $this->assertNotNull($booking->fresh()->checked_in_at);
        $this->assertFalse(AuditLog::where('action', 'reserva.cancelar')->exists());
    }

    public function test_early_checkin_rejects_an_intervening_booking_without_side_effects(): void
    {
        $earlier = $this->booking(['guests_count' => 1]);
        $later = $this->booking(['starts_at' => Carbon::parse('2026-10-05 14:00'), 'guests_count' => 1]);
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $this->post('/reservas/'.$later->code.'/checkin')->assertRedirect()->assertSessionHasErrors('booking');
        $this->assertNull($later->fresh()->checked_in_at);
        $this->assertSame('PENDIENTE_PAGO', $later->fresh()->booking_status);
        $this->assertSame(0, $later->guests()->count());
        $this->travelTo(Carbon::parse('2026-10-05 12:00'));
        $this->post('/reservas/'.$earlier->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
    }

    public function test_allowed_early_checkin_protects_the_actual_occupancy_interval(): void
    {
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 14:00'), 'guests_count' => 1]);
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $this->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $this->assertTrue($booking->fresh()->checked_in_at->eq(now()));
        $this->assertSame('14:00', $booking->fresh()->starts_at->format('H:i'));
        $this->assertSame(20000, $booking->fresh()->price_final);
        $this->expectException(RoomNotAvailableException::class);
        $this->booking(['starts_at' => Carbon::parse('2026-10-05 11:15')]);
    }

    public function test_early_checkin_respects_exact_cleaning_buffer(): void
    {
        $this->booking(['starts_at' => Carbon::parse('2026-10-05 10:00'), 'guests_count' => 1]);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 14:00'), 'guests_count' => 1]);
        $this->travelTo(Carbon::parse('2026-10-05 11:29'));
        $this->post('/reservas/'.$booking->code.'/checkin')->assertSessionHasErrors('booking');
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->travelTo(Carbon::parse('2026-10-05 11:30'));
        $this->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
    }

    public function test_early_checkin_upgrade_rejects_intervening_booking_in_destination(): void
    {
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 14:00'), 'guests_count' => 1]);
        $category = RoomCategory::create(['name' => 'PLUS', 'is_active' => true]);
        $target = Room::create(['name' => 'PLUS 102', 'room_category_id' => $category->id, 'operational_status' => 'activa']);
        $neighbor = $booking->replicate();
        $neighbor->fill(['code' => 'INTERMEDIATE', 'pass_token' => Str::random(40), 'room_id' => $target->id,
            'starts_at' => Carbon::parse('2026-10-05 12:00'), 'ends_at' => Carbon::parse('2026-10-05 13:00')])->save();
        $offer = UpsellOffer::create(['name' => 'Upgrade', 'type' => 'category_upgrade', 'is_active' => true,
            'from_room_category_id' => $this->room->room_category_id, 'to_room_category_id' => $category->id, 'price' => 1000]);
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $this->post('/reservas/'.$booking->code.'/checkin', ['accepted_upsells' => [$offer->id]])
            ->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $this->assertSame($this->room->id, $booking->fresh()->room_id);
        $this->assertSame(0, $booking->addonsTotal());
        $this->expectException(RoomNotAvailableException::class);
        app(BookingService::class)->reschedule($booking, [
            'room' => $target, 'starts_at' => $booking->starts_at, 'duration_minutes' => 60, 'guests_count' => 1,
        ]);
    }

    private function reserve(string $time = '12:00', array $extra = []): Booking
    {
        return app(BookingService::class)->create(array_merge(['room' => Room::first(), 'customer' => Customer::first(), 'starts_at' => Carbon::parse('2026-10-05 '.$time), 'duration_minutes' => 60, 'guests_count' => 1], $extra));
    }

    public function test_regression_early_departure_releases_actual_occupancy(): void
    {
        foreach (['2026_08_19_090003_add_consumption_offered_to_bookings_table.php', '2026_08_26_120001_add_aseo_override_to_rooms_table.php', '2026_08_26_140001_add_aseo_started_to_rooms_table.php'] as $f) {
            (require database_path('migrations/'.$f))->up();
        }
        $b = $this->reserve('14:00');
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $this->post('/reservas/'.$b->code.'/checkin')->assertRedirect()->assertSessionHasNoErrors();
        $method = PaymentMethod::create(['code' => 'test', 'name' => 'Prueba']);
        app(PaymentService::class)->register($b, $method, 20000, 'PAID', null, auth()->id());
        $this->travelTo(Carbon::parse('2026-10-05 12:00'));
        $this->post('/reservas/'.$b->code.'/finalizar', ['confirm_room_checked' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame('FINALIZADA', $b->booking_status);
        $this->assertSame('12:00', $b->ends_at->format('H:i'));
        $this->assertSame('11:00', $b->starts_at->format('H:i'));
        $this->assertTrue(app(AvailabilityChecker::class)->isAvailable($b->room, Carbon::parse('2026-10-05 12:30'), Carbon::parse('2026-10-05 13:30')));
    }

    public function test_regression_late_checkin_rejects_expired_stay_when_extension_conflicts(): void
    {
        $late = $this->reserve();
        $next = $this->reserve('14:00');
        $this->travelTo(Carbon::parse('2026-10-05 13:50'));
        $this->post('/reservas/'.$late->code.'/checkin')->assertRedirect()->assertSessionHasErrors('booking');
        $this->assertSame('PENDIENTE_PAGO', $late->fresh()->booking_status);
        $this->assertNull($late->fresh()->checked_in_at);
        $this->travelTo(Carbon::parse('2026-10-05 14:00'));
        $this->post('/reservas/'.$next->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
    }

    public function test_regression_upgrade_preserves_base_when_guest_count_changes(): void
    {
        $b = $this->reserve();
        $cat = RoomCategory::create(['name' => 'PLUS', 'base_capacity' => 2, 'is_active' => true]);
        $target = Room::create(['name' => 'PLUS 102', 'room_category_id' => $cat->id, 'operational_status' => 'activa']);
        RateRule::first()->prices()->create(['room_category_id' => $cat->id, 'duration_minutes' => 60, 'price' => 30000]);
        $offer = UpsellOffer::create(['name' => 'Upgrade', 'type' => 'category_upgrade', 'is_active' => true, 'from_room_category_id' => $b->room->room_category_id, 'to_room_category_id' => $cat->id, 'price' => 1000]);
        $this->assertTrue(app(BookingAllocationService::class)->upgrade($b, $offer, auth()->id()));
        $this->assertSame(21000, $b->price_final + $b->addonsTotal());
        $b = app(BookingService::class)->reschedule($b, ['room' => $target, 'starts_at' => $b->starts_at, 'duration_minutes' => 60, 'guests_count' => 2]);
        $this->assertSame(0, $b->extra_guests_fee);
        $this->assertSame(21000, $b->price_final + $b->addonsTotal());
        $b = app(BookingService::class)->reschedule($b, ['room' => $target, 'starts_at' => $b->starts_at, 'duration_minutes' => 60, 'guests_count' => 3]);
        $this->assertSame(10000, $b->extra_guests_fee);
        $this->assertSame(31000, $b->price_final + $b->addonsTotal());
        $b = app(BookingService::class)->reschedule($b, ['room' => $target, 'starts_at' => $b->starts_at, 'duration_minutes' => 60, 'guests_count' => 1]);
        $this->assertSame(21000, $b->price_final + $b->addonsTotal());
        $this->assertSame(1, $b->addons()->count());
    }

    public function test_regression_restock_reloads_stock_after_sale(): void
    {
        (require database_path('migrations/2026_08_19_150001_add_inventory_to_products_table.php'))->up();
        $b = $this->reserve();
        $product = Product::create(['name' => 'Bebida', 'price' => 1000, 'track_inventory' => true, 'stock' => 10, 'is_active' => true]);
        $request = new class($b, $product) extends Request
        {
            public function __construct(private Booking $booking, private Product $product)
            {
                parent::__construct();
            }

            public function validate(array $rules, ...$params): array
            {
                app(ConsumptionService::class)->addProduct($this->booking, $this->product, 1, auth()->id());

                return ['delta' => 5];
            }
        };
        app(ProductController::class)->restock($request, $product);
        $this->assertSame(14, $product->fresh()->stock);
        $this->assertSame(1, $b->addons()->count());
    }

    public function test_regression_public_availability_requires_price_for_duration(): void
    {
        $other = RateRule::create(['name' => 'OTRA', 'is_active' => false]);
        $other->prices()->create(['room_category_id' => Room::first()->room_category_id, 'duration_minutes' => 180, 'price' => 40000]);
        $data = ['room_category_id' => Room::first()->room_category_id, 'date' => '2026-10-05', 'time_hour' => 12, 'time_minute' => 0, 'duration_minutes' => 180];
        $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($data))->assertOk()->assertJsonPath('available', false);
        $this->post('/catalogo/reservar', $data + ['guests_count' => 1, 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'phone' => Customer::first()->phone_e164])
            ->assertRedirect()->assertSessionHasErrors('duration_minutes');
        $this->assertSame(0, Booking::count());
        RateRule::where('is_active', true)->first()->prices()->create(['room_category_id' => Room::first()->room_category_id, 'duration_minutes' => 180, 'price' => 40000]);
        $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($data))->assertOk()->assertJsonPath('available', true);
        $this->post('/catalogo/reservar', $data + ['guests_count' => 1, 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'phone' => Customer::first()->phone_e164])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
    }

    public function test_late_arrival_extends_when_there_is_room_and_opening_time(): void
    {
        $booking = $this->reserve();
        $this->travelTo(Carbon::parse('2026-10-05 13:50'));
        $this->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $this->assertSame('14:50', $booking->fresh()->ends_at->format('H:i'));
        $this->assertSame(20000, $booking->fresh()->price_final);
    }

    public function test_late_arrival_after_closing_is_rejected_before_registering_checkin(): void
    {
        $booking = $this->reserve();
        RateRule::first()->windows()->update(['start_time' => '10:00:00', 'end_time' => '13:00:00', 'wraps_midnight' => false]);
        $this->travelTo(Carbon::parse('2026-10-05 13:50'));
        $this->post('/reservas/'.$booking->code.'/checkin')->assertSessionHasErrors('booking');
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertSame(0, AuditLog::where('action', 'reserva.check_in')->count());
    }

    public function test_guest_only_edit_preserves_base_and_recalculates_existing_offer(): void
    {
        $offer = $this->offer(['max_uses_total' => 1]);
        $booking = $this->reserve();
        $this->assertSame(16000, $booking->price_final);
        // Simula cambio del tarifario después de reservar: editar personas
        // debe conservar la base guardada, no recotizarla a 30.000.
        RateRule::first()->prices()->update(['price' => 30000]);
        $booking = app(BookingService::class)->reschedule($booking, [
            'room' => $this->room, 'starts_at' => $booking->starts_at, 'duration_minutes' => 60, 'guests_count' => 3,
        ]);
        $this->assertSame(30000, $booking->price_original);
        $this->assertSame(10000, $booking->extra_guests_fee);
        $this->assertSame(24000, $booking->price_final);
        $this->assertSame(1, $offer->redemptions()->count());
        $this->assertSame(6000, $offer->redemptions()->first()->discount_amount);
    }
}
