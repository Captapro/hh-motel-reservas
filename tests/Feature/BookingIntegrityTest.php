<?php

namespace Tests\Feature;

use App\Exceptions\InvalidCouponException;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BookingCancelController;
use App\Http\Controllers\RoomBoardController;
use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\ApplyHostView;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\CoreExtra;
use App\Models\Coupon;
use App\Models\CouponProof;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\GuestReview;
use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
use App\Models\LateArrivalSetting;
use App\Models\OperationalSetting;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\ShiftNote;
use App\Models\RoomCategory;
use App\Models\Staff;
use App\Models\TrackingData;
use App\Models\UpsellOffer;
use App\Models\User;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\ConsumptionService;
use App\Services\Booking\CouponProofs;
use App\Services\Booking\PaymentService;
use App\Services\Booking\RoomBoardService;
use App\Services\Customers\NameSuggestions;
use App\Services\Historical\HistoricalSalesImporter;
use App\Services\Integrations\CustomerActivityTags;
use App\Services\Integrations\GhlAbandonedBookings;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Integrations\GhlClient;
use App\Services\Integrations\GhlMarketingConsent;
use App\Services\Integrations\GhlOpportunitySync;
use App\Services\Integrations\GhlReviewRequests;
use App\Services\Marketing\Attribution;
use App\Services\Marketing\MetaConversions;
use App\Services\Payments\MercadoPagoClient;
use App\Services\Payments\MercadoPagoHistory;
use App\Services\Payments\MercadoPagoPayments;
use App\Services\Pricing\CouponValidator;
use App\Services\Rooms\RoomClosingLog;
use App\Services\Sales\WeekdayAverages;
use App\Support\GuestLine;
use App\Support\Rut;
use App\Support\SalesProjection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Concerns\PaysBookings;
use Tests\TestCase;

class BookingIntegrityTest extends TestCase
{
    use PaysBookings;

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
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120005_create_rate_rule_windows_table.php',
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120007_create_calendar_overrides_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120009_create_coupon_rooms_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_09_11_090000_add_identity_fields_to_customers_table.php',
            '2026_09_16_010000_create_staff_and_shifts_tables.php',
            '2026_08_26_120001_add_aseo_override_to_rooms_table.php',
            '2026_08_26_140001_add_aseo_started_to_rooms_table.php',
            '2026_09_22_004138_add_aseo_report_to_rooms_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_10_26_220000_create_late_arrival_settings_table.php',
            '2026_10_28_120000_add_abandon_notices_to_bookings.php',
            '2026_10_28_130000_add_privacy_consent_to_customers.php',
            '2026_10_28_140000_create_historical_sales_tables.php',
            '2026_10_28_150000_create_core_extras_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120017_create_refunds_table.php',
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
            '2026_08_18_120020_create_tracking_data_table.php',
            '2026_11_03_000000_add_marketing_tracking.php',
            '2026_11_03_100000_add_room_closing_log_and_booked_ends.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $category = RoomCategory::create(['name' => 'GO', 'base_capacity' => 2, 'is_active' => true]);
        $this->room = Room::create(['name' => 'GO 101', 'room_category_id' => $category->id, 'operational_status' => 'activa', 'buffer_minutes' => 30]);
        $this->customer = Customer::create(['name' => 'Cliente de prueba', 'phone_e164' => '+56911111111', 'document_type' => 'rut', 'rut' => '11111111-1', 'nationality' => 'Chilena']);
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

    public function test_a_forgotten_discount_code_can_be_applied_to_an_open_booking(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'recepcion']));
        Coupon::create(['code' => 'MORNING ESCAPE', 'internal_name' => 'Morning', 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 20,
            'allowed_time_start' => '10:30:00', 'allowed_time_end' => '13:00:00']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:40')]);
        $original = (int) $booking->price_original;

        $this->post(route('bookings.coupon.apply', $booking->code), ['coupon_code' => 'morning escape'])->assertRedirect(route('reservations.show', $booking->code));
        $booking->refresh();
        $this->assertSame((int) round($original * 0.2), (int) $booking->discount_amount);
        $this->assertSame($original - (int) $booking->discount_amount, (int) $booking->price_final);
        $this->assertSame(1, CouponRedemption::where('booking_id', $booking->id)->count());

        // No se acumula una segunda promoción, y fuera de horario no aplica.
        $this->post(route('bookings.coupon.apply', $booking->code), ['coupon_code' => 'MORNING ESCAPE'])->assertSessionHasErrors('coupon_code');
        $late = $this->booking(['starts_at' => Carbon::parse('2026-11-02 18:00')]);
        $this->post(route('bookings.coupon.apply', $late->code), ['coupon_code' => 'MORNING ESCAPE'])->assertSessionHasErrors('coupon_code');
        $this->assertSame(0, (int) $late->fresh()->discount_amount);
    }

    public function test_a_new_catalog_booking_alerts_the_board_for_twenty_minutes_but_reception_bookings_do_not(): void
    {
        Cache::flush();
        $online = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'created_by' => null]);
        $this->booking(['starts_at' => Carbon::parse('2026-11-09 12:00'), 'created_by' => auth()->id()]);

        $this->getJson(route('rooms.pass_scans'))->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['type' => 'nueva', 'id' => 'nueva:'.$online->code, 'customer' => 'Cliente de prueba', 'paid' => false, 'amount' => 20000]);

        $this->travel(21)->minutes();
        $this->getJson(route('rooms.pass_scans'))->assertExactJson([]);
    }

    public function test_pass_qr_verifies_on_the_phone_and_alerts_the_desktop_which_then_goes_to_what_is_pending(): void
    {
        Cache::flush();
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'created_by' => auth()->id()]);

        // Celular: solo verifica el pase, no pide hacer nada ahí.
        $this->get(route('bookings.validate', $booking->code))
            ->assertOk()->assertSee('Pase válido')->assertSee('No necesitas hacer nada en el celular');
        // El computador de recepción recibe el aviso.
        $this->getJson(route('rooms.pass_scans'))->assertOk()->assertJsonFragment(['code' => $booking->code]);

        // Desde el aviso: con saldo, primero cobrar, y el pago lleva al check-in completo.
        $go = route('bookings.validate', ['code' => $booking->code, 'ir' => 1]);
        $this->get($go)->assertRedirect(route('payments.create', ['code' => $booking->code, 'after' => 'pase']));
        $this->getJson(route('rooms.pass_scans'))->assertExactJson([]);
        $this->post(route('payments.store', $booking->code), [
            'payment_method_id' => $method->id, 'amount' => $booking->balanceDue(), 'request_token' => (string) Str::uuid(),
            'receipt_number' => '1', 'after' => 'pase',
        ])->assertRedirect(route('bookings.checkin.show', $booking->code));

        // Sin saldo: directo al check-in. Con check-in hecho: el pase ya no es válido y va a la ficha.
        $this->get($go)->assertRedirect(route('bookings.checkin.show', $booking->code));
        $booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        $this->get($go)->assertRedirect(route('reservations.show', $booking->code));
        $this->get(route('bookings.validate', $booking->code))->assertOk()->assertSee('Pase no válido');
        $this->getJson(route('rooms.pass_scans'))->assertExactJson([]);
    }

    public function test_online_booking_asks_mercado_pago_before_expiring_and_records_a_payment_that_never_got_notified(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $paid = $this->booking(['starts_at' => Carbon::parse('2026-10-05 12:00')]);
        $unpaid = $this->booking(['starts_at' => Carbon::parse('2026-10-05 15:00')]);
        foreach ([$paid, $unpaid] as $b) {
            $this->assertSame('catalogo', $b->origin);
            $b->update(['booking_status' => 'PENDIENTE_PAGO', 'expires_at' => now()->subMinute()]);
        }
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => fn ($request) => Http::response(['results' => $request['external_reference'] === $paid->code
                ? [['id' => 555, 'external_reference' => $paid->code, 'status' => 'approved', 'transaction_amount' => $paid->balanceDue()]]
                : []]),
            '*' => Http::response([], 200),
        ]);

        // Sin revisar a Mercado Pago (p. ej. al cargar el tablero) no se vence ninguna del catálogo.
        $this->assertSame(0, Booking::releaseExpired());

        $this->artisan('app:reservas-expirar')->assertSuccessful();

        $this->assertSame('CONFIRMADA', $paid->fresh()->booking_status);
        $this->assertSame(0, $paid->fresh()->balanceDue());
        $this->assertDatabaseHas('payments', ['booking_id' => $paid->id, 'external_id' => 'MP-555', 'status' => 'aprobado']);
        $this->assertSame('EXPIRADA', $unpaid->fresh()->booking_status);
    }

    public function test_mercado_pago_history_crosses_the_account_with_the_system(): void
    {
        Cache::flush();
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $registered = $this->booking(['starts_at' => Carbon::parse('2026-10-05 12:00')]);
        $lost = $this->booking(['starts_at' => Carbon::parse('2026-10-05 15:00')]);
        $mpMethod = PaymentMethod::create(['code' => 'mercado_pago', 'name' => 'Mercado Pago', 'requires_external_id' => true]);
        $debito = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        Payment::create(['booking_id' => $registered->id, 'payment_method_id' => $mpMethod->id, 'amount' => 20000, 'status' => 'aprobado', 'external_id' => 'MP-101']);
        Payment::create(['booking_id' => $registered->id, 'payment_method_id' => $debito->id, 'amount' => 7000, 'status' => 'aprobado', 'external_id' => 'REC-1', 'voucher_number' => '07']);
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [
            ['id' => 101, 'status' => 'approved', 'external_reference' => $registered->code, 'transaction_amount' => 20000, 'payment_type_id' => 'credit_card', 'date_approved' => '2026-10-01T10:00:00.000-03:00', 'fee_details' => [['amount' => 700]], 'transaction_details' => ['net_received_amount' => 19300]],
            ['id' => 102, 'status' => 'approved', 'external_reference' => $lost->code, 'transaction_amount' => 20000, 'payment_type_id' => 'debit_card', 'date_approved' => '2026-10-01T11:00:00.000-03:00'],
            ['id' => 103, 'status' => 'approved', 'authorization_code' => '012345', 'external_reference' => 'Venta presencial', 'transaction_amount' => 7000, 'payment_type_id' => 'debit_card', 'point_of_interaction' => ['type' => 'POINT'], 'date_approved' => '2026-10-01T11:50:00.000-03:00'],
            // Mismo monto pero otra hora (más de 20 min): no se cruza con el mismo pago de caja.
            ['id' => 106, 'status' => 'approved', 'external_reference' => 'Venta presencial', 'transaction_amount' => 7000, 'payment_type_id' => 'debit_card', 'point_of_interaction' => ['type' => 'POINT'], 'date_approved' => '2026-10-01T09:00:00.000-03:00'],
            ['id' => 105, 'status' => 'refunded', 'external_reference' => 'HH-20260901-00009', 'transaction_amount' => 250, 'date_created' => '2026-10-01T08:00:00.000-03:00'],
            ['id' => 104, 'status' => 'rejected', 'transaction_amount' => 5000, 'date_created' => '2026-10-01T09:00:00.000-03:00'],
        ]])]);

        $data = $this->get(route('admin.mercadopago.index', ['mes' => '2026-10']))->assertOk()
            ->assertSee('Faltan en el sistema')->assertSee($lost->code)->viewData('data');

        $this->assertSame(54000, $data['totals']['gross']);
        $this->assertSame(40000, $data['totals']['online']);
        $this->assertSame(14000, $data['totals']['point']);
        $this->assertSame(1, $data['totals']['missing']);
        $this->assertTrue($data['rows']->firstWhere('id', '102')['missing']);
        $this->assertTrue($data['rows']->firstWhere('id', '101')['in_system']);
        // Sin elegir día, el detalle es el de hoy; abajo, el resumen por día con máquina vs caja.
        $this->assertCount(6, $data['rows']);
        $this->assertSame(['count' => 6, 'gross' => 54000, 'machine' => 14000, 'system' => 7000], collect($data['days']->first())->only(['count', 'gross', 'machine', 'system'])->all());

        $this->assertSame('Máquina (Point)', $data['rows']->firstWhere('id', '103')['channel']);
        // La máquina se cruza con el pago de tarjeta de caja por monto y hora, y muestra el correlativo del motel.
        $this->assertTrue($data['rows']->firstWhere('id', '103')['in_system']);
        $this->assertSame('07', $data['rows']->firstWhere('id', '103')['voucher']);
        $this->assertSame($registered->code, $data['rows']->firstWhere('id', '103')['booking']->code);
        $this->assertFalse($data['rows']->firstWhere('id', '106')['in_system']);
        // Correlativo de la máquina en el mes, como el voucher interno: 106 (09:00) es el 01, 103 (11:50) el 02.
        $this->assertSame('01', $data['rows']->firstWhere('id', '106')['correlative']);
        $this->assertSame('02', $data['rows']->firstWhere('id', '103')['correlative']);
        $this->assertNull($data['rows']->firstWhere('id', '101')['correlative']);
        $this->assertSame('Reserva online', $data['rows']->firstWhere('id', '105')['channel']);
        $this->assertTrue($data['rows']->firstWhere('id', '105')['deleted_booking']);

        // Filtro por día: el detalle se acota, los totales siguen siendo del mes.
        $other = $this->get(route('admin.mercadopago.index', ['mes' => '2026-10', 'dia' => '2026-10-02']))->assertOk()->viewData('data');
        $this->assertCount(0, $other['rows']);
        $this->assertSame(54000, $other['totals']['gross']);

        // Sin credencial: avisa en vez de romperse. Anfitrión no entra.
        config(['services.mercadopago.access_token' => null]);
        $this->get(route('admin.mercadopago.index'))->assertOk()->assertSee('no está conectado');
        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->get(route('admin.mercadopago.index'))->assertForbidden();
    }

    public function test_split_payment_returns_to_the_form_with_the_rest_before_check_in(): void
    {
        $debito = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        $efectivo = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $total = $booking->balanceDue();

        // Primera parte con débito: vuelve al formulario con lo que falta, sin error.
        $this->post(route('payments.store', $booking->code), [
            'payment_method_id' => $debito->id, 'amount' => $total - 5000, 'request_token' => (string) Str::uuid(),
            'voucher_number' => 'V1', 'after' => 'checkin',
        ])->assertRedirect(route('payments.create', ['code' => $booking->code, 'after' => 'checkin']))
            ->assertSessionHasNoErrors()->assertSessionHas('split');

        $this->get(route('payments.create', ['code' => $booking->code, 'after' => 'checkin']))
            ->assertOk()->assertSee('Falta $5.000')->assertSee('value="5000"', false);

        // El resto en efectivo: ahora sí pasa al check-in.
        $this->post(route('payments.store', $booking->code), [
            'payment_method_id' => $efectivo->id, 'amount' => 5000, 'request_token' => (string) Str::uuid(),
            'receipt_number' => 'B1', 'after' => 'checkin',
        ])->assertRedirect(route('bookings.checkin.show', ['code' => $booking->code, 'flujo' => 'ingreso']));

        $this->assertSame(0, $booking->fresh()->balanceDue());
        $this->assertSame(2, $booking->payments()->count());

        // Ingreso presencial: con todo listo entra solo; si falta un acompañante, se detiene a pedirlo.
        $express = route('bookings.checkin.show', ['code' => $booking->code, 'flujo' => 'ingreso']);
        $this->get($express)->assertOk()->assertDontSee('id="auto-checkin"', false);
        BookingGuest::create(['booking_id' => $booking->id, 'name' => 'Luz Prueba']);
        $this->get($express)->assertOk()->assertSee('id="auto-checkin"', false);
        $this->get(route('bookings.checkin.show', $booking->code))->assertOk()->assertDontSee('id="auto-checkin"', false);
    }

    public function test_student_coupon_needs_a_photo_of_the_credential_or_is_removed_before_check_in(): void
    {
        (require database_path('migrations/2026_10_31_150000_create_coupon_proofs_table.php'))->up();
        Coupon::create(['code' => 'ESTUDIANTE', 'internal_name' => 'Estudiante', 'is_active' => true, 'discount_type' => 'percentage',
            'discount_value' => 20, 'requires_verification' => true, 'verification_note' => 'Credencial de estudiante vigente']);
        $student = $this->booking(['coupon_code' => 'ESTUDIANTE', 'guests_count' => 1]);
        $this->assertSame(4000, $student->discount_amount);

        // Sin foto: el check-in muestra el QR y no deja pasar.
        $this->payRoom($student)->get(route('bookings.checkin.show', $student->code))
            ->assertOk()->assertSee('Credencial de estudiante vigente')->assertSee(route('bookings.coupon_proof.qr', $student->code));
        $this->post(route('bookings.checkin', $student->code))->assertSessionHasErrors('booking');
        $this->assertNull($student->fresh()->checked_in_at);

        // El celular sube la foto: queda guardada comprimida y el check-in pasa.
        $this->getJson(route('bookings.coupon_proof.status', $student->code))->assertExactJson(['ok' => false]);
        $this->post(route('bookings.coupon_proof.store', $student->code), ['photo' => UploadedFile::fake()->image('credencial.jpg', 800, 500)])
            ->assertRedirect(route('bookings.coupon_proof.create', $student->code));
        $this->getJson(route('bookings.coupon_proof.status', $student->code))->assertExactJson(['ok' => true]);
        $this->get(route('bookings.coupon_proof.show', $student->code))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->post(route('bookings.checkin', $student->code))->assertSessionHasNoErrors();
        $this->assertNotNull($student->fresh()->checked_in_at);

        // Otra reserva sin credencial: se quita el cupón y hay que cobrar la diferencia.
        $other = $this->booking(['coupon_code' => 'ESTUDIANTE', 'guests_count' => 1, 'starts_at' => Carbon::parse('2026-10-05 15:00')]);
        $this->payRoom($other)->post(route('bookings.coupon_proof.remove', $other->code))
            ->assertRedirect(route('payments.create', ['code' => $other->code, 'after' => 'pase']))->assertSessionHas('split');
        $other->refresh();
        $this->assertNull($other->coupon_id);
        $this->assertSame(0, $other->discount_amount);
        $this->assertSame(4000, $other->roomBalanceDue());
        $this->assertDatabaseMissing('coupon_redemptions', ['booking_id' => $other->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cupon.quitado_sin_respaldo', 'entity_id' => $other->id]);

        // Las fotos se borran solas a los 30 días.
        $this->travel(31)->days();
        $this->assertSame(1, app(CouponProofs::class)->prune());
    }

    public function test_reception_can_keep_a_pending_booking_without_expiry_and_payment_clears_the_hold(): void
    {
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $held = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $held->update(['expires_at' => now()->addMinutes(10)]);
        $paid = $this->booking(['starts_at' => Carbon::parse('2026-11-02 15:00')]);
        $paid->update(['expires_at' => now()->addMinutes(10)]);

        // Recepción: "pagará en recepción" -> sin vencimiento.
        $this->post(route('bookings.hold', $held->code), ['reason' => 'recepcion'])->assertSessionHasNoErrors();
        $this->assertNull($held->fresh()->expires_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reserva.mantener_retencion', 'entity_id' => $held->id]);

        // Un pago registrado también deja la reserva firme.
        app(PaymentService::class)->register($paid, $method, 1000, null, null, auth()->id());
        $this->assertNull($paid->fresh()->expires_at);

        // Pasado el límite, ninguna de las dos se libera.
        $this->travel(30)->minutes();
        $this->assertSame(0, Booking::releaseExpired());
        $this->assertSame('PENDIENTE_PAGO', $held->fresh()->booking_status);
        $this->assertSame('CONFIRMADA', $paid->fresh()->booking_status);
    }

    public function test_sale_detail_panel_shows_payments_and_consumptions(): void
    {
        $method = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        app(PaymentService::class)->register($booking, $method, 1000, null, null, auth()->id(), '4455', null);

        $this->get(route('sales.detail', $booking->code))
            ->assertOk()->assertSee($booking->code)->assertSee('Débito')->assertSee('Voucher 4455')->assertSee('Abrir la reserva completa');
        $this->get(route('sales.detail', 'NO-EXISTE'))->assertNotFound();
    }

    public function test_each_booking_becomes_a_ghl_opportunity_that_follows_its_status_with_amount_and_source(): void
    {
        config(['services.ghl.private_token' => 'pit-prueba', 'services.ghl.location_id' => 'loc-prueba', 'services.ghl.opportunities_since' => '2026-09-01']);
        $stages = collect(GhlOpportunitySync::STAGES)->map(fn ($s, $k) => ['id' => 'st-'.$k, 'name' => $s['name']])->values()->all();
        Http::fake([
            'services.leadconnectorhq.com/opportunities/pipelines*' => Http::response(['pipelines' => [
                ['id' => 'p-otro', 'name' => 'REMATE 7 sep', 'stages' => []],
                ['id' => 'p-hh', 'name' => 'Reservas HH', 'stages' => $stages],
            ]]),
            'services.leadconnectorhq.com/opportunities/' => Http::response(['opportunity' => ['id' => 'opp-1']]),
            'services.leadconnectorhq.com/opportunities/opp-1' => Http::response(['ok' => true]),
        ]);
        $this->customer->update(['ghl_contact_id' => 'c-123']);
        $sinTelefono = Customer::create(['name' => 'Sin teléfono', 'document_type' => 'rut', 'rut' => '22222222-2']);
        $this->booking(['starts_at' => Carbon::parse('2026-10-12 12:00'), 'customer' => $sinTelefono]);

        $online = $this->booking(['created_by' => null]);
        TrackingData::create(['booking_id' => $online->id, 'utm_source' => 'ig', 'utm_campaign' => 'bio', 'source_label' => 'Instagram · bio']);
        $sync = app(GhlOpportunitySync::class);

        $this->assertSame(1, $sync->syncPending());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/opportunities/')
            && $r['pipelineId'] === 'p-hh' && $r['pipelineStageId'] === 'st-reservo' && $r['status'] === 'open'
            && $r['contactId'] === 'c-123' && $r['monetaryValue'] === 20000 && $r['source'] === 'Instagram · bio');
        $this->assertSame('opp-1', $online->fresh()->ghl_opportunity_id);
        // Ya al día: la pasada siguiente no vuelve a llamar a GHL.
        $this->assertSame(0, $sync->syncPending());

        $online->update(['booking_status' => 'FINALIZADA']);
        $this->assertSame(1, $sync->syncPending());
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/opportunities/opp-1')
            && $r['pipelineStageId'] === 'st-vino' && $r['status'] === 'won');
    }

    public function test_a_returning_customer_keeps_one_ghl_opportunity_that_follows_the_latest_booking(): void
    {
        config(['services.ghl.private_token' => 'pit-prueba', 'services.ghl.location_id' => 'loc-prueba', 'services.ghl.opportunities_since' => '2026-09-01']);
        $stages = collect(GhlOpportunitySync::STAGES)->map(fn ($s, $k) => ['id' => 'st-'.$k, 'name' => $s['name']])->values()->all();
        Http::fake([
            'services.leadconnectorhq.com/opportunities/pipelines*' => Http::response(['pipelines' => [['id' => 'p-hh', 'name' => 'Reservas HH', 'stages' => $stages]]]),
            'services.leadconnectorhq.com/opportunities/search*' => Http::response(['opportunities' => [['id' => 'opp-viejo']]]),
            'services.leadconnectorhq.com/opportunities/' => Http::response(['statusCode' => 400, 'message' => 'Can not create duplicate opportunity for the contact.', 'code' => 'OPPORTUNITY_NO_DUPLICATE'], 400),
            'services.leadconnectorhq.com/opportunities/opp-viejo' => Http::response(['ok' => true]),
        ]);
        $this->customer->update(['ghl_contact_id' => 'c-123']);
        $first = $this->booking(['starts_at' => Carbon::parse('2026-10-05 12:00')]);
        $first->update(['booking_status' => 'FINALIZADA']);
        $second = $this->booking(['starts_at' => Carbon::parse('2026-10-12 12:00')]);

        // Ninguna traba el embudo: las dos quedan en la oportunidad que el cliente ya tenía,
        // y solo la reserva más nueva la actualiza (la vieja no la devuelve a "Vino").
        $this->assertSame(2, app(GhlOpportunitySync::class)->syncPending());
        $this->assertSame(['opp-viejo', 'opp-viejo'], [$first->fresh()->ghl_opportunity_id, $second->fresh()->ghl_opportunity_id]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/opportunities/search') && $r['contact_id'] === 'c-123' && $r['pipeline_id'] === 'p-hh');
        $updates = Http::recorded(fn ($r) => $r->method() === 'PUT')->map(fn ($pair) => $pair[0]['pipelineStageId'])->values()->all();
        $this->assertSame(['st-reservo'], $updates);
        $this->assertNull(Cache::get(GhlOpportunitySync::ERROR_KEY));
    }

    public function test_the_host_can_not_register_an_entry_with_a_past_time_but_the_administrator_can(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 16:25'));
        $data = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 15, 'time_minute' => 0,
            'duration_minutes' => 60, 'guests_count' => 1, 'customer_first_name' => 'Hora', 'customer_last_name' => 'Atrasada',
            'document_type' => 'rut', 'document_number' => '12.345.678-5', 'nationality' => 'Chilena', 'walk_in' => 1,
        ];
        $admin = auth()->user();
        $host = User::factory()->create(['role' => 'recepcion']);

        $this->actingAs($host);
        $this->get(route('reservations.create', ['modo' => 'ingreso']))->assertOk()->assertDontSee('¿Entró antes?')->assertDontSee('ya salieron');
        // Tampoco se puede forzando el formulario, ni con la reserva normal, ni con "ya salieron".
        $this->post('/reservar', $data)->assertSessionHasErrors('date');
        $this->post('/reservar', ['walk_in' => 0] + $data)->assertSessionHasErrors('date');
        $this->room->update(['operational_status' => 'aseo']);
        $this->post('/reservar', ['time_hour' => 16, 'time_minute' => 25, 'aseo_mode' => 'ya_salieron'] + $data)->assertSessionHasErrors('date');
        $this->room->update(['operational_status' => 'activa']);
        $this->assertSame(0, Booking::count());

        // En el momento sí.
        $this->post('/reservar', ['time_hour' => 16, 'time_minute' => 25] + $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
        Booking::query()->delete();

        $this->actingAs($admin);
        $this->get(route('reservations.create', ['modo' => 'ingreso']))->assertOk()->assertSee('¿Entró antes?');
        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Booking::firstOrFail()->isLateRegistration());
    }

    public function test_the_administrator_corrects_the_real_times_of_a_stay_without_touching_the_room(): void
    {
        foreach (['consumption_offered_at', 'consumption_offered_by', 'review_requested_at'] as $column) {
            if (! Schema::hasColumn('bookings', $column)) {
                Schema::table('bookings', fn ($t) => $t->string($column)->nullable());
            }
        }
        $this->travelTo(Carbon::parse('2026-10-05 19:00'));
        $url = fn (Booking $b) => route('bookings.real_times', $b->code);
        $times = ['checked_in_at' => '2026-10-05T17:30', 'checked_out_at' => '2026-10-05T18:05'];
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 19:00'), 'created_by' => auth()->id()]);
        $booking->update(['payment_status' => 'PAGADA', 'booking_status' => 'CONFIRMADA', 'price_final' => 0, 'price_original' => 0]);

        // Solo administrador, y con salida posterior a la entrada y ya ocurrida.
        $admin = auth()->user();
        $this->actingAs(User::factory()->create(['role' => 'recepcion']));
        $this->post($url($booking), $times)->assertForbidden();
        $this->actingAs($admin);
        $this->post($url($booking), ['checked_out_at' => '2026-10-05T17:00'] + $times)->assertSessionHasErrors('checked_out_at');
        $this->post($url($booking), ['checked_out_at' => '2026-10-05T20:00'] + $times)->assertSessionHasErrors('checked_out_at');

        // Nunca tuvo check-in: el horario se mueve a la entrada real, con su misma duración.
        $this->post($url($booking), $times)->assertRedirect(route('reservations.show', $booking->code))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('FINALIZADA', $booking->booking_status);
        $this->assertSame(['17:30', '18:05', '17:30', '18:05', '18:30'], [
            $booking->checked_in_at->format('H:i'), $booking->checked_out_at->format('H:i'),
            $booking->starts_at->format('H:i'), $booking->ends_at->format('H:i'), $booking->bookedEndsAt()->format('H:i'),
        ]);
        $this->assertNotNull($booking->review_requested_at);
        $this->assertSame('activa', $this->room->fresh()->operational_status);
        $this->assertSame(1, AuditLog::where('action', 'reserva.corregir_horas')->count());

        // Una estadía ya cerrada: se corrige solo la salida, y el contratado no cambia.
        $this->post($url($booking), ['checked_out_at' => '2026-10-05T18:50'] + $times)->assertSessionHasNoErrors();
        $this->assertSame(['18:50', '18:30', '18:30'], [$booking->fresh()->checked_out_at->format('H:i'), $booking->fresh()->ends_at->format('H:i'), $booking->fresh()->bookedEndsAt()->format('H:i')]);
    }

    public function test_core_extras_are_reported_apart_from_products_and_the_day_still_adds_up(): void
    {
        $method = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        $product = Product::create(['name' => 'Agua', 'category' => 'bebidas', 'price' => 1500, 'stock_quantity' => 5, 'is_active' => true]);
        $this->travelTo(Carbon::parse('2026-11-02 11:00'));
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'created_by' => auth()->id()]);
        $booking->addons()->create(['description' => 'Hora adicional', 'quantity' => 1, 'amount' => 15000]);
        $booking->addons()->create(['product_id' => $product->id, 'description' => 'Agua', 'quantity' => 1, 'amount' => 1500]);
        app(PaymentService::class)->register($booking->fresh(), $method, 36500, null, null, auth()->id());

        $totals = $this->get(route('sales.monthly', '2026-11'))->assertOk()->viewData('totals');
        $this->assertSame([20000, 1500, 15000, 36500], [(int) $totals['lodging'], (int) $totals['extras'], (int) $totals['core'], (int) $totals['total']]);
        $board = $this->boardDailySummary();
        $this->assertSame([1, 1500, 15000], [$board['extras_count'], $board['extras_revenue'], $board['core_revenue']]);
        $this->get(route('sales.daily', '2026-11-02'))->assertOk()->assertSee('Extras core');
    }

    public function test_the_credential_qr_works_on_a_phone_without_login_and_expires(): void
    {
        (require database_path('migrations/2026_10_31_150000_create_coupon_proofs_table.php'))->up();
        $coupon = $this->offer(['requires_verification' => true, 'verification_note' => 'Credencial de estudiante vigente']);
        $booking = $this->booking();
        $booking->update(['coupon_id' => $coupon->id, 'coupon_code_snapshot' => 'ESTUDIANTE']);
        $staff = auth()->id();
        $link = URL::temporarySignedRoute('coupon_proof.phone', now()->addHours(2), ['code' => $booking->code, 'por' => $staff]);
        auth()->logout();

        // Sin firma no entra nadie; con el enlace del QR, el celular sin sesión sube la foto.
        $this->get('/credencial/'.$booking->code)->assertForbidden();
        $this->get($link)->assertOk()->assertSee('Sacar foto')->assertSee('action="'.e($link).'"', false);
        $this->post($link, ['photo' => UploadedFile::fake()->image('credencial.jpg', 800, 500)])->assertRedirect($link);
        $this->get($link)->assertOk()->assertSee('Foto guardada');
        $this->assertSame($staff, CouponProof::where('booking_id', $booking->id)->value('uploaded_by'));

        // El enlace vence.
        $this->travel(3)->hours();
        $this->get($link)->assertForbidden();
    }

    public function test_account_movements_without_machine_or_booking_stay_out_of_the_motel_totals(): void
    {
        config(['services.mercadopago.access_token' => 'tok']);
        $this->travelTo(Carbon::parse('2026-11-03 20:00'));
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['paging' => ['total' => 2], 'results' => [
            ['id' => 1, 'status' => 'approved', 'transaction_amount' => 32000, 'date_approved' => '2026-11-03T18:31:00-03:00', 'payment_type_id' => 'debit_card', 'point_of_interaction' => ['type' => 'POINT'], 'fee_details' => [['amount' => 605]]],
            ['id' => 2, 'status' => 'approved', 'transaction_amount' => 79944, 'date_approved' => '2026-11-03T05:17:00-03:00', 'payment_type_id' => 'credit_card', 'external_reference' => '2000015834486205'],
        ]])]);

        $data = $this->get(route('admin.mercadopago.index', ['mes' => '2026-11']))->assertOk()->assertSee('Otros movimientos de la cuenta')->viewData('data');
        $this->assertSame([32000, 32000, 79944, 1], [$data['totals']['gross'], $data['totals']['point'], $data['totals']['other'], $data['totals']['other_count']]);
        $this->assertSame(32000, $data['days']->first()['gross']);
        $this->assertCount(2, $data['rows']);
    }

    public function test_attribution_names_the_channel_and_the_catalog_remembers_where_the_visitor_came_from(): void
    {
        $this->assertSame('Meta Ads · HH | MENSAJES', Attribution::labelFor(new TrackingData(['utm_source' => 'meta', 'utm_campaign' => 'HH | MENSAJES'])));
        $this->assertSame('Meta Ads', Attribution::labelFor(new TrackingData(['fbclid' => 'abc'])));
        $this->assertSame('Instagram', Attribution::labelFor(new TrackingData(['referrer' => 'https://l.instagram.com/?u=x'])));
        $this->assertSame('Sitio hhmotel.cl', Attribution::labelFor(new TrackingData(['referrer' => 'https://www.hhmotel.cl/playrooms'])));
        $this->assertSame('Directo', Attribution::labelFor(new TrackingData));
        $this->assertSame('Presencial', Attribution::sourceOf($this->booking(['walk_in' => true, 'created_by' => auth()->id()])));

        $session = app('session')->driver('array');
        $visit = function (string $url, ?string $referrer = null) use ($session) {
            $request = Request::create($url, 'GET', server: $referrer ? ['HTTP_REFERER' => $referrer] : []);
            $request->setLaravelSession($session);
            (new CaptureAttribution)->handle($request, fn () => response('ok'));
        };
        $visit('https://app.hhmotel.cl/catalogo?utm_source=ig&utm_campaign=bio&fbclid=XYZ');
        // Seguir navegando dentro del catálogo no borra el origen.
        $visit('https://app.hhmotel.cl/catalogo/reservar', 'https://app.hhmotel.cl/catalogo');
        $data = $session->get(CaptureAttribution::SESSION_KEY);
        $this->assertSame('ig', $data['utm_source']);
        $this->assertSame('bio', $data['utm_campaign']);
        $this->assertStringStartsWith('fb.1.', $data['fbc']);
        $this->assertStringEndsWith('.XYZ', $data['fbc']);
    }

    public function test_a_paid_sale_is_reported_to_meta_once_with_hashed_contact_data(): void
    {
        config(['services.meta.pixel_id' => '123', 'services.meta.capi_token' => 'token-prueba']);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $this->customer->update(['email' => 'Cliente@Correo.cl']);
        $walkIn = $this->booking(['walk_in' => true, 'created_by' => auth()->id()]);
        $meta = app(MetaConversions::class);

        $this->assertTrue($meta->sendPurchase($walkIn->fresh()));
        Http::assertSent(function ($r) use ($walkIn) {
            $event = $r['data'][0];

            return str_contains($r->url(), '/123/events') && $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'purchase-'.$walkIn->code && $event['action_source'] === 'physical_store'
                && $event['custom_data']['value'] === 20000 && $event['custom_data']['currency'] === 'CLP'
                && $event['user_data']['ph'] === [hash('sha256', '56911111111')]
                && $event['user_data']['em'] === [hash('sha256', 'cliente@correo.cl')];
        });
        $this->assertNotNull($walkIn->fresh()->meta_purchase_sent_at);
        $this->assertFalse($meta->sendPurchase($walkIn->fresh()));
        Http::assertSentCount(1);
    }

    public function test_booking_remembers_how_it_was_born_walk_in_team_or_catalog(): void
    {
        $walkIn = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'walk_in' => true, 'created_by' => auth()->id()]);
        $team = $this->booking(['starts_at' => Carbon::parse('2026-11-02 15:00'), 'created_by' => auth()->id()]);
        $online = $this->booking(['starts_at' => Carbon::parse('2026-11-02 18:00'), 'created_by' => null]);

        $this->assertSame('presencial', $walkIn->fresh()->origin);
        $this->assertSame('recepcion', $team->fresh()->origin);
        $this->assertSame('catalogo', $online->fresh()->origin);
        $this->assertSame('Presencial', $walkIn->fresh()->originLabel());
        $this->assertSame('Reserva en línea', $online->fresh()->originLabel());
        $this->assertSame('Reserva asistida', $team->fresh()->originLabel());
    }

    private function mpWebhook(string $paymentId, string $secret = 'sekret', ?string $signature = null)
    {
        $ts = '1700000000';
        $manifest = 'id:'.strtolower($paymentId).';request-id:req-1;ts:'.$ts.';';
        $v1 = $signature ?? hash_hmac('sha256', $manifest, $secret);

        return $this->postJson('/webhooks/mercadopago?data.id='.$paymentId.'&type=payment', ['type' => 'payment', 'data' => ['id' => $paymentId]], [
            'x-signature' => 'ts='.$ts.',v1='.$v1, 'x-request-id' => 'req-1',
        ]);
    }

    public function test_mercado_pago_webhook_confirms_the_booking_once_and_rejects_bad_signatures(): void
    {
        Cache::flush();
        config(['services.mercadopago.access_token' => 'TEST-token', 'services.mercadopago.webhook_secret' => 'sekret']);
        PaymentMethod::create(['code' => 'mercado_pago', 'name' => 'Mercado Pago', 'requires_external_id' => true]);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $booking->update(['expires_at' => now()->addMinutes(10)]);
        Http::fake(['api.mercadopago.com/v1/payments/777' => Http::response([
            'id' => 777, 'status' => 'approved', 'external_reference' => $booking->code, 'transaction_amount' => 20000.0, 'payment_type_id' => 'credit_card',
        ])]);

        $this->mpWebhook('777', signature: 'firma-falsa')->assertStatus(401);
        $this->assertSame(0, $booking->payments()->count());

        $this->mpWebhook('777')->assertOk();
        $this->mpWebhook('777')->assertOk();   // aviso repetido: no duplica el pago

        $booking->refresh();
        $this->assertSame(1, $booking->payments()->count());
        $this->assertSame('MP-777', $booking->payments()->first()->external_id);
        $this->assertSame(20000, $booking->paidAmount());
        $this->assertSame('CONFIRMADA', $booking->booking_status);
        $this->assertNull($booking->expires_at);

        // El pago aparece en el banner de avisos del tablero.
        $this->getJson(route('rooms.pass_scans'))->assertOk()
            ->assertJsonFragment(['type' => 'pago', 'code' => $booking->code, 'amount' => 20000, 'review' => false]);
    }

    public function test_returning_from_mercado_pago_confirms_the_booking_without_waiting_for_the_webhook(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        PaymentMethod::create(['code' => 'mercado_pago', 'name' => 'Mercado Pago', 'requires_external_id' => true]);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $booking->update(['expires_at' => now()->addMinutes(10)]);
        $other = $this->booking(['starts_at' => Carbon::parse('2026-11-09 12:00')]);
        Http::fake([
            'api.mercadopago.com/v1/payments/901' => Http::response(['id' => 901, 'status' => 'approved', 'external_reference' => $booking->code, 'transaction_amount' => 20000]),
            'api.mercadopago.com/v1/payments/902' => Http::response(['id' => 902, 'status' => 'approved', 'external_reference' => $other->code, 'transaction_amount' => 20000]),
        ]);

        // Un pago de otra reserva no confirma esta.
        $this->get(route('catalog.booked', $booking->pass_token).'?payment_id=902')->assertOk();
        $this->assertSame('PENDIENTE_PAGO', $booking->fresh()->booking_status);

        $this->get(route('catalog.booked', $booking->pass_token).'?payment_id=901')->assertOk();
        $this->get(route('catalog.booked', $booking->pass_token).'?payment_id=901')->assertOk();

        $booking->refresh();
        $this->assertSame('CONFIRMADA', $booking->booking_status);
        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_mercado_pago_payment_fills_the_customer_rut_only_when_missing_and_valid(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token', 'services.mercadopago.webhook_secret' => 'sekret']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $booking->customer->update(['rut' => null, 'document_type' => null]);
        Http::fake(['api.mercadopago.com/v1/payments/701' => Http::response([
            'id' => 701, 'status' => 'approved', 'external_reference' => $booking->code, 'transaction_amount' => 20000,
            'payer' => ['identification' => ['type' => 'RUT', 'number' => '11.111.111-1']],
        ])]);

        $this->mpWebhook('701')->assertOk();

        $customer = $booking->customer->fresh();
        $this->assertSame('11111111-1', $customer->rut);
        $this->assertSame('rut', $customer->document_type);

        // Con documento ya cargado, el de Mercado Pago no lo reemplaza.
        $other = $this->booking(['starts_at' => Carbon::parse('2026-11-02 18:00')]);
        $other->customer->update(['rut' => '22222222-2', 'document_type' => 'rut']);
        Http::fake(['api.mercadopago.com/v1/payments/702' => Http::response([
            'id' => 702, 'status' => 'approved', 'external_reference' => $other->code, 'transaction_amount' => 20000,
            'payer' => ['identification' => ['type' => 'RUT', 'number' => '11.111.111-1']],
        ])]);
        $this->mpWebhook('702')->assertOk();
        $this->assertSame('22222222-2', $other->customer->fresh()->rut);
    }

    public function test_mercado_pago_webhook_ignores_merchant_order_notices_without_signature(): void
    {
        config(['services.mercadopago.webhook_secret' => 'sekret']);

        $this->postJson('/webhooks/mercadopago?id=45568092227&topic=merchant_order')
            ->assertOk()->assertJsonPath('ignored', true);
    }

    public function test_mercado_pago_payment_after_the_hold_expired_is_recorded_and_flagged(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token', 'services.mercadopago.webhook_secret' => 'sekret']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $booking->update(['booking_status' => 'EXPIRADA']);
        Http::fake(['api.mercadopago.com/v1/payments/888' => Http::response([
            'id' => 888, 'status' => 'approved', 'external_reference' => $booking->code, 'transaction_amount' => 20000,
        ])]);

        $this->mpWebhook('888')->assertOk()->assertJsonPath('result', 'registrado_revisar');

        $booking->refresh();
        $this->assertSame(20000, $booking->paidAmount());
        $this->assertSame('EXPIRADA', $booking->booking_status);
        $this->assertStringContainsString('Pago de Mercado Pago recibido', (string) $booking->notes);
        $this->getJson(route('rooms.pass_scans'))->assertJsonFragment(['type' => 'pago', 'review' => true]);
    }

    public function test_pay_now_opens_the_mercado_pago_checkout_for_the_full_balance(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['init_point' => 'https://www.mercadopago.cl/checkout/v1/redirect?pref_id=abc'])]);

        $this->get(route('catalog.pay', $booking->pass_token))->assertRedirect('https://www.mercadopago.cl/checkout/v1/redirect?pref_id=abc');

        Http::assertSent(fn ($request) => $request['external_reference'] === $booking->code
            && (int) $request['items'][0]['unit_price'] === 20000 && $request['items'][0]['currency_id'] === 'CLP');
    }

    public function test_refund_marks_the_payment_returned_and_the_caja_stops_counting_it(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $payment = app(PaymentService::class)->register($booking, $method, 20000, null, null, auth()->id(), null, '123');

        $this->post(route('payments.refund', ['code' => $booking->code, 'payment' => $payment]), [])->assertSessionHasErrors('reason');
        $this->post(route('payments.refund', ['code' => $booking->code, 'payment' => $payment]), ['reason' => 'Cliente canceló'])->assertSessionHasNoErrors();

        $this->assertSame('reembolsado', $payment->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paidAmount());
        $this->assertSame('NO_PAGADA', $booking->fresh()->payment_status);
        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 20000, 'status' => 'procesado', 'reason' => 'Cliente canceló']);
    }

    public function test_online_payment_is_refunded_through_mercado_pago(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $method = PaymentMethod::create(['code' => 'mercado_pago', 'name' => 'Mercado Pago']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $payment = app(PaymentService::class)->register($booking, $method, 20000, 'MP-555', null, auth()->id());
        Http::fake(['api.mercadopago.com/v1/payments/555/refunds' => Http::response(['id' => 1, 'status' => 'approved'])]);

        $this->post(route('payments.refund', ['code' => $booking->code, 'payment' => $payment]), ['reason' => 'Pagó dos veces'])->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/payments/555/refunds'));
        $this->assertSame('reembolsado', $payment->fresh()->status);
    }

    public function test_partial_refund_keeps_the_rest_as_paid_on_the_original_day(): void
    {
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $payment = app(PaymentService::class)->register($booking, $method, 20000, null, null, auth()->id(), null, '123');
        $paidAt = $payment->fresh()->created_at;

        $this->post(route('payments.refund', ['code' => $booking->code, 'payment' => $payment]), ['reason' => 'x', 'amount' => 25000])->assertSessionHasErrors('amount');

        $this->travel(2)->days();
        $this->post(route('payments.refund', ['code' => $booking->code, 'payment' => $payment]), ['reason' => 'Se fue antes', 'amount' => 5000])->assertSessionHasNoErrors();

        $this->assertSame('reembolsado', $payment->fresh()->status);
        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 5000]);
        $rest = Payment::where('booking_id', $booking->id)->where('status', 'aprobado')->sole();
        $this->assertSame(15000, (int) $rest->amount);
        $this->assertSame('123', $rest->receipt_number);
        $this->assertTrue($paidAt->eq($rest->created_at), 'el resto queda en el día del pago original');
        $this->assertSame(15000, $booking->fresh()->paidAmount());
    }

    public function test_partial_refund_of_an_online_payment_sends_the_amount_to_mercado_pago(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $method = PaymentMethod::create(['code' => 'mercado_pago', 'name' => 'Mercado Pago']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $payment = app(PaymentService::class)->register($booking, $method, 20000, 'MP-777', null, auth()->id());
        Http::fake(['api.mercadopago.com/v1/payments/777/refunds' => Http::response(['id' => 2, 'status' => 'approved'])]);

        $this->post(route('payments.refund', ['code' => $booking->code, 'payment' => $payment]), ['reason' => 'Descuento olvidado', 'amount' => 4000])->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/payments/777/refunds') && (int) $request['amount'] === 4000);
        $rest = Payment::where('booking_id', $booking->id)->where('status', 'aprobado')->sole();
        $this->assertSame(16000, (int) $rest->amount);
        $this->assertStringStartsWith('MP-777-RESTO-', $rest->external_id);

        // Si después Mercado Pago informa la devolución total, el resto también queda devuelto.
        app(MercadoPagoPayments::class)->record(['id' => 777, 'external_reference' => $booking->code, 'status' => 'refunded']);
        $this->assertSame('reembolsado', $rest->fresh()->status);
    }

    public function test_ghl_is_told_only_when_the_booking_is_confirmed_and_skips_walk_ins_but_not_clients_without_email(): void
    {
        config(['services.ghl.webhook_confirmada' => 'https://services.leadconnectorhq.com/hooks/confirmada']);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $this->customer->update(['email' => 'cliente@example.com', 'name' => 'Camila Rojas']);

        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        app(PaymentService::class)->register($booking, $method, 10000, null, null, auth()->id(), null, '1');   // confirma
        app(PaymentService::class)->register($booking, $method, 10000, null, null, auth()->id(), null, '2');   // segundo pago: no manda nada

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/hooks/confirmada') && $r['codigo'] === $booking->code
            && $r['email'] === 'cliente@example.com' && $r['first_name'] === 'Camila' && str_ends_with($r['fecha_hora'], ' a las 12:00') && str_starts_with($r['fecha_hora'], $r['fecha']) && $r['pagado'] === '$10.000' && $r['saldo'] === '$10.000' && $r['token'] === $booking->pass_token);

        // Ingreso presencial: no se avisa, el cliente está en recepción.
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $walkIn = $this->booking(['starts_at' => Carbon::parse('2026-11-09 12:00'), 'walk_in' => true, 'created_by' => auth()->id()]);
        app(PaymentService::class)->register($walkIn, $method, 20000, null, null, auth()->id(), null, '3');
        Http::assertNothingSent();

        // Cliente sin correo pero con teléfono: se avisa igual, para el WhatsApp.
        $this->customer->update(['email' => null]);
        $noEmail = $this->booking(['starts_at' => Carbon::parse('2026-11-16 12:00')]);
        app(PaymentService::class)->register($noEmail, $method, 20000, null, null, auth()->id(), null, '4');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['codigo'] === $noEmail->code && $r['email'] === '' && $r['phone'] === '+56911111111');
    }

    public function test_closing_the_stay_frees_the_client_for_the_whatsapp_welcome_unless_another_booking_is_alive(): void
    {
        config(['services.ghl.private_token' => 'pit-prueba', 'services.ghl.location_id' => 'loc-prueba']);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $this->customer->update(['ghl_contact_id' => 'c-123']);
        $sync = new GhlBookingSync(new GhlClient);

        $first = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $second = $this->booking(['starts_at' => Carbon::parse('2026-11-09 12:00')]);

        // Con otra reserva viva no se toca nada: sigue siendo cliente en curso.
        $first->update(['booking_status' => 'CANCELADA']);
        $sync->releaseStay($first);
        Http::assertNothingSent();

        $second->update(['booking_status' => 'FINALIZADA']);
        $sync->releaseStay($second);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/contacts/c-123/tags')
            && $r['tags'] === [GhlBookingSync::ACTIVE_STAY_TAG, GhlBookingSync::WELCOME_TAG]);
        // Vino de verdad (FINALIZADA): queda marcado para los públicos de Meta; la cancelada no.
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts/c-123/tags') && $r['tags'] === [GhlBookingSync::ATTENDED_TAG]);
        Http::assertSentCount(2);
    }

    public function test_marketing_permission_is_reflected_in_ghl_and_ghl_can_unsubscribe(): void
    {
        config(['services.ghl.private_token' => 'pit-prueba', 'services.ghl.location_id' => 'loc-prueba', 'services.ghl.inbound_secret' => 'token-ghl']);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $this->customer->update(['ghl_contact_id' => 'c-123', 'marketing_opt_in' => true]);
        $consent = new GhlMarketingConsent(new GhlClient);

        // Con permiso: lleva el tag de marketing y se le saca el de "no".
        $this->assertTrue($consent->sync($this->customer));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts/c-123/tags') && $r['tags'] === [GhlMarketingConsent::MARKETING_YES]);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/contacts/c-123/tags') && $r['tags'] === [GhlMarketingConsent::MARKETING_NO]);

        // GHL avisa la baja: sin token no se acepta; con token queda sin permiso y con el tag "no".
        $this->postJson(route('webhooks.ghl.marketing_optout'), ['phone' => '+56 9 1111 1111'])->assertStatus(401);
        $this->assertTrue($this->customer->fresh()->marketing_opt_in);
        $this->postJson(route('webhooks.ghl.marketing_optout', ['token' => 'token-ghl']), ['phone' => '+56 9 1111 1111'])->assertOk()->assertJsonPath('result', 'baja');
        $this->assertFalse($this->customer->fresh()->marketing_opt_in);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts/c-123/tags') && $r['tags'] === [GhlMarketingConsent::MARKETING_NO]);
    }

    public function test_after_checkout_the_guest_is_asked_once_and_gets_a_personal_ten_percent_coupon(): void
    {
        foreach (['2026_09_23_193653_create_guest_reviews_table.php', '2026_10_31_120000_add_review_rewards.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        config(['services.ghl.webhook_pedir_opinion' => 'https://services.leadconnectorhq.com/hooks/opinion', 'services.ghl.webhook_cupon_opinion' => 'https://services.leadconnectorhq.com/hooks/cupon']);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $next = $this->booking(['starts_at' => Carbon::parse('2026-11-02 18:00')]);
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:00'));
        $booking->update(['booking_status' => 'FINALIZADA', 'checked_in_at' => Carbon::parse('2026-11-02 12:00'), 'checked_out_at' => Carbon::parse('2026-11-02 14:50')]);
        $requests = app(GhlReviewRequests::class);

        // Antes de los 15 minutos no se pide; después, una sola vez.
        $requests->sendDue();
        Http::assertNothingSent();
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:06'));
        $requests->sendDue();
        $requests->sendDue();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/hooks/opinion') && str_ends_with($r['link_opinion'], '/opinion/'.$booking->code));

        // Opina (aunque sea con nota baja): recibe su cupón personal del 10%, que no sale en el catálogo.
        $this->post(route('reviews.store', $booking->code), ['rating' => 2])->assertRedirect();
        $coupon = Coupon::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertSame(10, (int) $coupon->discount_value);
        $this->assertSame(1, $coupon->max_uses_total);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/hooks/cupon') && $r['cupon_codigo'] === $coupon->code);
        $review = GuestReview::firstOrFail();
        $this->get(route('reviews.thanks', $review))->assertOk()->assertSee($coupon->code);

        // Volver a opinar no da otro cupón; y a quien ya opinó no se le vuelve a pedir.
        $this->post(route('reviews.store', $booking->code), ['rating' => 5]);
        $this->assertSame(1, Coupon::where('customer_id', $this->customer->id)->count());
        $next->update(['booking_status' => 'FINALIZADA', 'checked_out_at' => Carbon::parse('2026-11-02 14:40')]);
        $requests->sendDue();
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/hooks/opinion') && ($r['codigo'] ?? null) === $next->code);

        // Es personal: otro cliente no lo puede usar.
        $other = Customer::create(['name' => 'Otra persona', 'phone_e164' => '+56977777777']);
        $this->expectException(InvalidCouponException::class);
        app(CouponValidator::class)->validate($coupon, $this->room, Carbon::parse('2026-11-05 12:00'), 60, $other, 20000);
    }

    public function test_the_ghl_page_shows_what_happened_to_each_checkout_and_a_failing_step_does_not_block_review_requests(): void
    {
        foreach (['2026_09_23_193653_create_guest_reviews_table.php', '2026_10_31_120000_add_review_rewards.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        config(['services.ghl.webhook_pedir_opinion' => 'https://services.leadconnectorhq.com/hooks/opinion', 'services.ghl.webhook_cupon_opinion' => null]);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:06'));
        $booking->update(['booking_status' => 'FINALIZADA', 'checked_in_at' => Carbon::parse('2026-11-02 12:00'), 'checked_out_at' => Carbon::parse('2026-11-02 14:50')]);

        // El rescate de reservas sin pagar falla: igual se pide la opinión y el error queda a la vista.
        $this->mock(GhlAbandonedBookings::class)->shouldReceive('run')->andThrow(new \RuntimeException('falla de prueba'));
        $this->artisan('app:reservas-expirar')->assertSuccessful();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/hooks/opinion'));

        $this->actingAs(User::factory()->create(['role' => 'soporte']));
        $this->get(route('admin.ghl.index'))->assertOk()
            ->assertSee('GHL_WEBHOOK_PEDIR_OPINION configurado')
            ->assertSee('GHL_WEBHOOK_CUPON_OPINION vacío')
            ->assertSee('Proceso automático corriendo')
            ->assertSee('falla de prueba')
            ->assertSee($booking->code)
            ->assertSee('Enviado a GHL')
            ->assertSee('GHL respondió 200')
            ->assertDontSee('services.leadconnectorhq.com');
    }

    public function test_customer_activity_tags_follow_days_since_last_visit_and_only_change_when_the_bucket_changes(): void
    {
        (require database_path('migrations/2026_10_31_140000_add_activity_tag_to_customers.php'))->up();
        config(['services.ghl.private_token' => 'pit-prueba', 'services.ghl.location_id' => 'loc-prueba']);
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['ok' => true])]);
        $this->customer->update(['ghl_contact_id' => 'c-123']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $booking->update(['booking_status' => 'FINALIZADA', 'checked_out_at' => Carbon::parse('2026-11-02 13:00')]);
        $tags = new CustomerActivityTags(new GhlClient);

        $this->assertSame('hh_pausa', CustomerActivityTags::tagForDays(3));
        $this->assertNull(CustomerActivityTags::tagForDays(20));
        $this->assertSame('hh_inactivo_45', CustomerActivityTags::tagForDays(50));
        $this->assertSame('hh_inactivo_90', CustomerActivityTags::tagForDays(200));

        // Recién vino: en pausa. Una segunda pasada sin cambios no llama a GHL.
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00'));
        $this->assertSame(1, $tags->sync());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['tags'] === ['hh_pausa']);
        $this->assertSame(0, $tags->sync());

        // A los 31 días pasa a "30 días sin venir" y se le saca la pausa.
        Carbon::setTestNow(Carbon::parse('2026-12-03 10:00'));
        $this->assertSame(1, $tags->sync());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['tags'] === ['hh_inactivo_30']);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && in_array('hh_pausa', $r['tags'], true));
        $this->assertSame('hh_inactivo_30', $this->customer->fresh()->activity_tag);
        $this->assertSame(1, $tags->report()['hh_inactivo_30']['customers']->count());
    }

    public function test_stay_in_progress_is_not_counted_as_an_upcoming_booking(): void
    {
        $current = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $this->booking(['starts_at' => Carbon::parse('2026-11-02 15:00')]);
        $current->update(['checked_in_at' => Carbon::parse('2026-11-02 12:05')]);

        $status = app(RoomBoardService::class)->statusFor($this->room, Carbon::parse('2026-11-02 12:20'));

        $this->assertSame('ocupada', $status['occupancy']);
        $this->assertSame(1, $status['upcoming_count']);
    }

    public function test_several_products_can_be_added_at_once_during_the_stay_all_or_nothing(): void
    {
        (require database_path('migrations/2026_08_19_150001_add_inventory_to_products_table.php'))->up();
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        $booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        $water = Product::create(['name' => 'Agua', 'price' => 2000, 'track_inventory' => true, 'stock' => 5, 'is_active' => true]);
        $beer = Product::create(['name' => 'Cerveza', 'price' => 3000, 'track_inventory' => true, 'stock' => 1, 'is_active' => true]);

        // Una cerveza de más: no se agrega nada, ni siquiera el agua.
        $this->post(route('addons.store', $booking->code), ['quantities' => [$water->id => 2, $beer->id => 2]])->assertSessionHasErrors('booking');
        $this->assertSame(0, $booking->fresh()->addons()->count());
        $this->assertSame(5, $water->fresh()->stock);

        $this->post(route('addons.store', $booking->code), ['quantities' => [$water->id => 2, $beer->id => 1]])
            ->assertRedirect(route('payments.create', ['code' => $booking->code, 'after' => 'board']));
        $this->assertSame(2, $booking->fresh()->addons()->count());
        $this->assertSame(7000, (int) $booking->fresh()->addons()->sum('amount'));
        $this->assertSame(3, $water->fresh()->stock);
    }

    public function test_cleanup_can_remove_all_sales_history_and_orphan_clients_restoring_stock(): void
    {
        (require database_path('migrations/2026_08_19_150001_add_inventory_to_products_table.php'))->up();
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $product = Product::create(['name' => 'Agua', 'price' => 2000, 'track_inventory' => true, 'stock' => 10, 'is_active' => true]);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);
        app(ConsumptionService::class)->addProduct($booking, $product, 3, auth()->id());
        app(PaymentService::class)->register($booking->fresh(), $method, 5000, null, null, auth()->id());
        $this->assertSame(7, $product->fresh()->stock);
        $extra = Customer::create(['name' => 'Sin reservas', 'phone_e164' => '+56977777777']);

        $this->actingAs(User::factory()->create(['role' => 'soporte']));
        $this->delete(route('admin.cleanup.destroy'), ['what' => ['ventas', 'clientes'], 'confirm_text' => 'ELIMINAR'])->assertRedirect(route('admin.cleanup.index'));

        $this->assertSame(0, Booking::count());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('booking_addons', 0);
        $this->assertSame(10, $product->fresh()->stock);
        // Se van los clientes que quedaron sin reservas, tanto el de la reserva borrada como el suelto.
        $this->assertDatabaseMissing('customers', ['id' => $extra->id]);
        $this->assertDatabaseMissing('customers', ['id' => $this->customer->id]);
    }

    public function test_companion_document_is_optional_and_saved_when_given(): void
    {
        $booking = $this->booking([
            'starts_at' => Carbon::parse('2026-11-02 12:00'),
            'guest_names' => ['Ana Pérez | 12.345.678-5', 'Luis Gómez'],
        ]);

        $guests = $booking->guests()->orderBy('id')->get();
        $this->assertSame(['Ana Pérez', 'Luis Gómez'], $guests->pluck('name')->all());
        $this->assertSame('12.345.678-5', $guests[0]->id_document);
        $this->assertNull($guests[1]->id_document);
        $this->assertSame('Ana Pérez | 12.345.678-5', GuestLine::format($guests[0]->name, $guests[0]->id_document));
    }

    public function test_planilla_goloso_and_extras_on_their_own_rows_join_the_stay_above(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $row = fn (int $n, string $type, int $amount, array $more = []) => DB::table('historical_sales')->insertGetId($more + ['source' => 'planilla-test', 'source_row' => $n, 'sold_on' => '2026-06-12', 'sale_type' => $type, 'amount_main' => $amount]);
        $row(1, 'ALOJAMIENTO', 30000, ['guests_count' => 2, 'starts_time' => '20:00']);
        $row(2, 'EXTRA CORE', 17500, ['detail' => 'GOLOSO', 'quantity' => 1]);
        $row(3, 'EXTRAS', 7000, ['detail' => 'Cerveza']);
        $row(4, 'ALOJAMIENTO', 36000, ['guests_count' => 2, 'starts_time' => '22:00']);
        $row(5, 'EXTRA CORE', 15000, ['detail' => 'HORA EXTRA', 'quantity' => 1]);
        // Otro día: no se une a la estadía de arriba.
        $row(6, 'EXTRA CORE', 17500, ['detail' => 'GOLOSO', 'sold_on' => '2026-06-13']);

        $page = $this->get(route('admin.analytics.customers'))->assertOk();
        $this->assertSame(1, $page->viewData('party')->firstWhere('label', '3 o más personas')['count']);
        $added = $page->viewData('added')->pluck('count', 'label');
        $this->assertSame([1, 1, 1], [$added['Persona adicional (goloso)'], $added['Hora extra'], $added['Consumos (bebidas, preservativos…)']]);
        // Venta por estadía: (30.000 + 17.500 + 7.000 + 36.000 + 15.000) / 2.
        $this->assertSame(52750, $page->viewData('kpis')['ticket']);
    }

    public function test_a_customer_found_in_the_old_planilla_by_rut_is_not_counted_as_new(): void
    {
        foreach (['2026_09_22_020000_add_blacklist_to_customers_and_segment_rules.php', '2026_09_22_030000_add_window_and_stars_to_customer_segment_rules.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $known = Customer::create(['name' => 'Ya Venía', 'phone_e164' => '+56955555511', 'rut' => '12345678-5', 'document_type' => 'rut']);
        $fresh = Customer::create(['name' => 'Primera Vez', 'phone_e164' => '+56955555512', 'rut' => '11111111-1', 'document_type' => 'rut']);
        // En la planilla (antes del sistema) el primero vino en junio, con el RUT escrito con puntos.
        $saleId = DB::table('historical_sales')->insertGetId(['source' => 'resumen-junio', 'source_row' => 1, 'sold_on' => '2026-06-12', 'sale_type' => 'ALOJAMIENTO', 'amount_main' => 30000]);
        DB::table('historical_guests')->insert(['historical_sale_id' => $saleId, 'name' => 'Ya Venía', 'document' => Rut::normalize('12.345.678-5'), 'document_kind' => 'rut', 'is_holder' => true]);

        $today = now();
        $this->travelTo(Carbon::parse('2026-03-01 09:00'));
        $this->booking(['customer' => $known, 'starts_at' => Carbon::parse('2026-08-03 12:00')]);
        $this->booking(['customer' => $fresh, 'starts_at' => Carbon::parse('2026-08-10 12:00')]);
        $this->travelTo($today);

        $page = $this->get(route('admin.analytics.customers', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();
        $kpis = $page->viewData('kpis');
        // Un solo historial: la vuelta de junio (planilla) a agosto (sistema) son 52 días.
        $this->assertSame(52, $kpis['median_gap']);
        $this->assertSame(1, $page->viewData('gapBuckets')->firstWhere('label', '31 a 60 días')['count']);
        $august = $page->viewData('monthly')->firstWhere('month', '2026-08');
        $this->assertSame([2, 1, 1, 50], [$august['people'], $august['new'], $august['returning'], $august['rate']]);
        // Con ventana fija: en los 30 días previos nadie había venido; 60 y 90 días antes de agosto aún no había datos.
        $this->assertSame(['back' => 0, 'pct' => 0], $august['windows'][30]);
        $this->assertNull($august['windows'][60]);
        $this->assertNull($august['windows'][90]);
        $this->assertSame(['days' => 15, 'eligible' => 1, 'back' => 0, 'pct' => 0], $page->viewData('retention')->first());
        $this->assertSame(2, $kpis['people']);
        $this->assertSame(1, $kpis['new']);       // solo el que nunca vino
        $this->assertSame(1, $kpis['repeaters']); // el de la planilla ya había venido
        // La etiqueta de la ficha (CLIENTE NUEVO) también cuenta la planilla.
        $this->assertNotSame('nuevo', $known->segment()['type']);
        $this->assertSame(2, $known->segment()['total_stays']);
        $this->assertSame('nuevo', $fresh->segment()['type']);
    }

    public function test_customer_behavior_page_counts_new_returning_and_at_risk_clients(): void
    {
        foreach (['2026_09_22_020000_add_blacklist_to_customers_and_segment_rules.php', '2026_09_22_030000_add_window_and_stars_to_customer_segment_rules.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $loyal = $this->customer;
        $newbie = Customer::create(['name' => 'Cliente Nuevo', 'phone_e164' => '+56955555501']);
        $returning = Customer::create(['name' => 'Cliente Que Vuelve', 'phone_e164' => '+56955555502']);

        // Las estadías son del pasado: se crean viajando a esa fecha y se vuelve al "hoy" del test.
        $today = now();
        $this->travelTo(Carbon::parse('2026-03-01 09:00'));
        // El "fiel" vino 3 veces cada ~2 semanas y lleva meses sin volver; el "que vuelve" ya había venido antes del rango.
        foreach (['2026-04-06', '2026-04-20', '2026-05-04'] as $day) {
            $this->booking(['customer' => $loyal, 'starts_at' => Carbon::parse($day.' 12:00')]);
        }
        $this->booking(['customer' => $returning, 'starts_at' => Carbon::parse('2026-07-06 12:00')]);
        $this->booking(['customer' => $returning, 'starts_at' => Carbon::parse('2026-08-03 12:00')]);
        $this->booking(['customer' => $newbie, 'starts_at' => Carbon::parse('2026-08-10 12:00')]);

        $this->travelTo($today);
        $page = $this->get(route('admin.analytics.customers', ['from' => '2026-08-01', 'to' => '2026-08-31']));
        $page->assertOk()->assertSee('Comportamiento de clientes');
        $data = $page->viewData('kpis');
        $this->assertSame(2, $data['people']);      // newbie + returning vinieron en agosto
        $this->assertSame(1, $data['new']);         // solo newbie es la primera vez
        $this->assertSame(1, $data['repeaters']);   // returning ya había venido antes
        $this->assertSame(50, $data['repeat_rate']);
        // En riesgo: el fiel (3 visitas cada ~14 días, meses sin volver) y el que vuelve (cada ~28 días, ya van 58); el recién llegado no.
        $this->assertSame(2, $data['at_risk']);
        $this->assertSame($loyal->id, $page->viewData('risk')->first()['customer']->id);
    }

    public function test_clients_that_return_lives_inside_analytics_for_soporte_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'soporte']));
        $this->get(route('admin.analytics.customers'))->assertOk()->assertSee('Clientes que vuelven');
        $this->get(route('admin.analytics.returning'))->assertOk()->assertSee('Clientes que vuelven');
        $this->get(route('admin.analytics.records'))->assertOk()->assertSee('Récords de venta');
        $this->get(route('admin.analytics.extras'))->assertOk()->assertSee('Extras por mes');
        app(HistoricalSalesImporter::class)->import(__DIR__.'/../fixtures/planilla_ventas_ejemplo.csv', 'planilla-2026-10', 2026);
        $this->get(route('admin.analytics.records'))->assertOk()->assertSee('Récord por día de la semana')->assertSee('Los 10 días de mayor venta');
        $this->get(route('admin.analytics.extras'))->assertOk()->assertSee('Extras vendidos por mes')->assertSee('Extras por estadía');
        $this->get('/admin/historico/clientes')->assertRedirect('/admin/analytics/clientes-que-vuelven');

        // Muestra nombres y RUT: el administrador no ve la pestaña ni entra.
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('admin.analytics.customers'))->assertOk()->assertDontSee('Clientes que vuelven');
        $this->get(route('admin.analytics.returning'))->assertForbidden();
    }

    public function test_booking_code_does_not_collide_after_deleting_one_from_the_middle(): void
    {
        $first = $this->booking(['starts_at' => Carbon::parse('2026-10-05 12:00')]);
        $second = $this->booking(['starts_at' => Carbon::parse('2026-10-05 14:00')]);
        $third = $this->booking(['starts_at' => Carbon::parse('2026-10-05 16:00')]);
        $this->assertSame(['00001', '00002', '00003'], [substr($first->code, -5), substr($second->code, -5), substr($third->code, -5)]);

        $first->delete();
        // Antes: contaba 2 reservas y volvía a generar ...00003, que ya existe (error 500).
        $fourth = $this->booking(['starts_at' => Carbon::parse('2026-10-05 18:00')]);
        $this->assertSame('00004', substr($fourth->code, -5));
    }

    public function test_monthly_summary_lists_each_sale_and_exports_csv(): void
    {
        // Cliente sin documento: el detalle lo marca como dato faltante.
        $this->customer->update(['rut' => null, 'document_type' => null]);
        $booking = $this->booking();
        $method = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        $payment = app(PaymentService::class)->register($booking, $method, 20000, null, null, auth()->id());

        $page = $this->get(route('sales.monthly', '2026-10'));
        $page->assertOk()->assertSee($booking->code)->assertSee('Detalle de ventas')->assertSee('Tarjeta');

        $csv = $this->get(route('sales.monthly', ['month' => '2026-10', 'export' => 'detalle']));
        $csv->assertOk();
        $this->assertStringContainsString($booking->code, $csv->streamedContent());
        $this->assertStringContainsString('RUT / documento', $csv->streamedContent());

        $this->put(route('payments.document', $payment), ['voucher_number' => ' 4521 ', 'receipt_number' => ''])->assertSessionHasNoErrors();
        $payment->refresh();
        $this->assertSame('4521', $payment->voucher_number);
        $this->assertNull($payment->receipt_number);

        $again = $this->get(route('sales.monthly', '2026-10'));
        $again->assertSee('Voucher')->assertSee('4521');
    }

    public function test_analytics_ranks_categories_by_number_of_bookings(): void
    {
        $other = RoomCategory::create(['name' => 'MAX', 'display_order' => 9, 'base_capacity' => 2, 'max_capacity' => 4, 'extra_guest_from' => 3]);
        $maxRoom = Room::create(['room_category_id' => $other->id, 'name' => 'MAX 999', 'operational_status' => 'activa']);
        $make = fn (Room $room, int $price) => (new Booking(['price_final' => $price]))->setRelation('room', $room->load('category'))->setRelation('addons', collect());
        $current = collect([$make($this->room, 20000), $make($this->room, 20000), $make($maxRoom, 50000)]);
        $previous = collect([$make($this->room, 20000), $make($maxRoom, 50000), $make($maxRoom, 50000)]);

        $method = new \ReflectionMethod(AnalyticsController::class, 'byCategory');
        $rows = $method->invoke(app(AnalyticsController::class), $current, $previous);

        $this->assertSame([$this->room->category->name, 'MAX'], $rows->pluck('label')->all());
        $this->assertSame([2, 1], $rows->pluck('count')->all());
        $this->assertSame([1, 2], $rows->pluck('prev')->all());
        $this->assertSame(40000, $rows->first()['value']);
        $this->assertEqualsWithDelta(2 / 3, $rows->first()['share'], 0.001);
    }

    public function test_month_end_projection_counts_only_closed_days_by_itself(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 15:00', 'America/Santiago'));
        $first = Carbon::parse('2026-11-01', 'America/Santiago');
        $sales = [1 => 300000, 2 => 100000, 3 => 100000, 4 => 999999]; // el 4 es hoy: no cuenta todavía
        $daily = collect(range(1, 30))->map(fn ($d) => ['date' => $first->copy()->day($d), 'total' => $sales[$d] ?? 0]);
        $p = SalesProjection::forMonth($daily, $first);
        $this->assertSame(3, $p['closed_days']);
        $this->assertSame(5000000, $p['simple']); // 500.000 ÷ 3 × 30
        // Domingos a 300.000, lunes y martes a 100.000, el resto al promedio general.
        $this->assertSame(5066667, $p['by_weekday']);

        // Un mes que no es el actual no se proyecta.
        $this->assertNull(SalesProjection::forMonth($daily, Carbon::parse('2026-10-01', 'America/Santiago')));
    }

    public function test_only_fully_paid_stays_count_as_sales(): void
    {
        $paid = $this->booking(['starts_at' => Carbon::parse('2026-10-05 12:00')]);
        $unpaid = $this->booking(['starts_at' => Carbon::parse('2026-10-12 12:00')]);
        $expired = $this->booking(['starts_at' => Carbon::parse('2026-10-19 12:00')]);
        $expired->update(['booking_status' => 'EXPIRADA']);
        $method = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        app(PaymentService::class)->register($paid, $method, (int) $paid->price_final, null, null, auth()->id());
        app(PaymentService::class)->register($unpaid, $method, 1000, null, null, auth()->id());   // solo un anticipo

        $totals = $this->get(route('sales.monthly', '2026-10'))->assertOk()->viewData('totals');

        $this->assertSame(1, $totals['rooms']);
        $this->assertSame((int) $paid->price_final, (int) $totals['total']);
        $this->assertSame((int) $paid->price_final + 1000, (int) $totals['cash_total']);   // la caja sí tiene los dos pagos
    }

    public function test_unpaid_consumption_does_not_remove_the_room_from_sales(): void
    {
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 12:00')]);
        $method = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        app(PaymentService::class)->register($booking, $method, (int) $booking->price_final, null, null, auth()->id());
        $booking->addons()->create(['description' => 'Agua', 'quantity' => 1, 'amount' => 3000]);

        // Habitación pagada, consumo aún sin pagar: la habitación cuenta, el consumo no.
        $totals = $this->get(route('sales.monthly', '2026-10'))->viewData('totals');
        $this->assertSame(1, $totals['rooms']);
        $this->assertSame((int) $booking->price_final, (int) $totals['total']);
        $this->assertSame(0, (int) $totals['extras']);

        app(PaymentService::class)->register($booking->fresh(), $method, 3000, null, null, auth()->id());

        $totals = $this->get(route('sales.monthly', '2026-10'))->viewData('totals');
        $this->assertSame(3000, (int) $totals['extras'] + (int) $totals['core']);
        $this->assertSame((int) $booking->price_final + 3000, (int) $totals['total']);
    }

    public function test_room_marked_occupied_by_hand_is_not_sellable(): void
    {
        $this->post(route('rooms.status', $this->room), ['operational_status' => 'ocupada', 'operational_note' => 'Entró con la planilla'])
            ->assertRedirect(route('rooms.board'));
        $this->assertSame('ocupada', $this->room->fresh()->operational_status);
        $this->assertFalse($this->room->fresh()->isOperational());
    }

    public function test_maintenance_needs_a_reason_that_is_cleared_when_the_room_is_back(): void
    {
        $this->from(route('rooms.board'))->post(route('rooms.status', $this->room), ['operational_status' => 'mantencion'])
            ->assertSessionHasErrors('operational_note');
        $this->assertSame('activa', $this->room->fresh()->operational_status);

        $this->post(route('rooms.status', $this->room), ['operational_status' => 'mantencion', 'operational_note' => 'Aire acondicionado en reparación'])
            ->assertRedirect(route('rooms.board'));
        $this->assertSame('Aire acondicionado en reparación', $this->room->fresh()->operational_note);

        $this->post(route('rooms.status', $this->room), ['operational_status' => 'activa']);
        $this->assertSame('activa', $this->room->fresh()->operational_status);
        $this->assertNull($this->room->fresh()->operational_note);
    }

    /** Resumen del día del tablero (método privado): se lee directo para no depender de toda la vista. */
    private function boardDailySummary(): array
    {
        $method = new \ReflectionMethod(RoomBoardController::class, 'dailySummary');

        return $method->invoke(app(RoomBoardController::class));
    }

    public function test_full_lifecycle_online_payment_then_consumption_keeps_sales_cash_and_board_consistent(): void
    {
        Cache::flush();
        config(['services.mercadopago.access_token' => 'TEST-token', 'services.mercadopago.webhook_secret' => 'sekret']);
        PaymentMethod::create(['code' => 'mercado_pago', 'name' => 'Mercado Pago', 'requires_external_id' => true]);
        $cash = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $this->travelTo(Carbon::parse('2026-11-02 11:00'));

        // 1) Reserva del catálogo: retenida y todavía sin pago -> no es venta.
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'origin' => 'catalogo']);
        $booking->update(['expires_at' => now()->addMinutes(10)]);
        $this->assertSame('PENDIENTE_PAGO', $booking->booking_status);
        $this->assertSame(0, $this->get(route('sales.monthly', '2026-11'))->viewData('totals')['rooms']);

        // 2) Mercado Pago avisa el pago completo -> confirmada, sin vencimiento, ya es venta y entra a caja.
        Http::fake(['api.mercadopago.com/v1/payments/9001' => Http::response(['id' => 9001, 'status' => 'approved', 'external_reference' => $booking->code, 'transaction_amount' => 20000])]);
        $this->mpWebhook('9001')->assertOk();
        $booking->refresh();
        $this->assertSame('CONFIRMADA', $booking->booking_status);
        $this->assertNull($booking->expires_at);
        $totals = $this->get(route('sales.monthly', '2026-11'))->viewData('totals');
        $this->assertSame([1, 20000, 20000], [$totals['rooms'], (int) $totals['total'], (int) $totals['cash_total']]);

        // 3) Check-in y consumo sin pagar: la habitación sigue siendo venta, el consumo no.
        $booking->update(['booking_status' => 'CHECK_IN', 'checked_in_at' => now()]);
        $booking->addons()->create(['description' => 'Cerveza', 'quantity' => 2, 'amount' => 5000]);
        $this->assertSame(5000, $booking->fresh()->balanceDue());
        $board = $this->boardDailySummary();
        $this->assertSame([1, 20000, 0], [$board['rooms_count'], $board['rooms_revenue'], $board['extras_revenue']]);

        // 4) Se paga el consumo en efectivo -> suma a la venta y a la caja, sin deuda.
        app(PaymentService::class)->register($booking->fresh(), $cash, 5000, null, null, auth()->id());
        $this->assertSame(0, $booking->fresh()->balanceDue());
        $totals = $this->get(route('sales.monthly', '2026-11'))->viewData('totals');
        $this->assertSame([25000, 5000, 25000], [(int) $totals['total'], (int) $totals['extras'] + (int) $totals['core'], (int) $totals['cash_total']]);
        $board = $this->boardDailySummary();
        $this->assertSame(5000, $board['extras_revenue'] + $board['core_revenue']);
    }

    public function test_late_online_payment_after_hold_expired_is_cash_but_not_a_sale_and_frees_the_slot(): void
    {
        Cache::flush();
        config(['services.mercadopago.access_token' => 'TEST-token', 'services.mercadopago.webhook_secret' => 'sekret']);
        $this->travelTo(Carbon::parse('2026-11-02 11:00'));
        $held = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'origin' => 'catalogo']);
        $held->update(['expires_at' => now()->addMinutes(10)]);

        // Pasados los 10 minutos (y revisado en Mercado Pago que no se pagó), otro cliente puede tomar el mismo horario.
        $this->travelTo(now()->addMinutes(11));
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []])]);
        $this->artisan('app:reservas-expirar')->assertSuccessful();
        $other = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'customer' => Customer::create(['name' => 'Otro', 'phone_e164' => '+56922222222'])]);
        $this->assertSame('EXPIRADA', $held->fresh()->booking_status);
        $this->assertNotSame($held->id, $other->id);

        // El pago del primero llega tarde: queda registrado y marcado para revisar, pero no es venta.
        Http::fake(['api.mercadopago.com/v1/payments/9002' => Http::response(['id' => 9002, 'status' => 'approved', 'external_reference' => $held->code, 'transaction_amount' => 20000])]);
        $this->mpWebhook('9002')->assertOk()->assertJsonPath('result', 'registrado_revisar');
        $this->assertSame('EXPIRADA', $held->fresh()->booking_status);
        $totals = $this->get(route('sales.monthly', '2026-11'))->viewData('totals');
        $this->assertSame(0, $totals['rooms']);
        $this->assertSame(20000, (int) $totals['cash_total']);
        $this->getJson(route('rooms.pass_scans'))->assertJsonFragment(['type' => 'pago', 'review' => true]);
    }

    public function test_expired_booking_can_be_reactivated_with_same_or_new_time_and_offers_the_pay_link(): void
    {
        Cache::flush();
        foreach (['2026_09_22_020000_add_blacklist_to_customers_and_segment_rules.php', '2026_09_22_030000_add_window_and_stars_to_customer_segment_rules.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $this->travelTo(Carbon::parse('2026-11-02 11:00'));
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 14:00'), 'origin' => 'catalogo']);
        $booking->update(['booking_status' => 'EXPIRADA', 'expires_at' => now()->subMinutes(5)]);

        // La ficha ofrece reactivar, con la hora actual de la reserva.
        $this->get(route('reservations.show', $booking->code))->assertOk()
            ->assertSee('Reactivar reserva')->assertSee('value="14:00"', false);

        // Misma hora: vuelve a quedar pendiente con retención nueva y ofrece el link de pago.
        $this->post(route('bookings.reactivate', $booking->code), ['date' => '2026-11-02', 'time' => '14:00', 'hold_minutes' => 30])
            ->assertRedirect(route('reservations.show', $booking->code));
        $fresh = $booking->fresh();
        $this->assertSame('PENDIENTE_PAGO', $fresh->booking_status);
        $this->assertEquals(now()->addMinutes(30)->toDateTimeString(), $fresh->expires_at->toDateTimeString());
        $this->get(route('reservations.show', $booking->code))->assertSee(route('catalog.pay', $booking->pass_token), false);

        // Vence otra vez y esta vez se reactiva con otra hora: se reprograma.
        $fresh->update(['booking_status' => 'EXPIRADA', 'expires_at' => now()->subMinute()]);
        $this->post(route('bookings.reactivate', $booking->code), ['date' => '2026-11-02', 'time' => '16:00', 'hold_minutes' => 15])
            ->assertRedirect(route('reservations.show', $booking->code));
        $moved = $booking->fresh();
        $this->assertSame('PENDIENTE_PAGO', $moved->booking_status);
        $this->assertSame('16:00', $moved->starts_at->format('H:i'));
        $this->assertSame('17:00', $moved->ends_at->format('H:i'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'reserva.reactivar', 'entity_id' => $booking->id]);
    }

    public function test_reactivation_is_refused_when_the_slot_was_taken_or_the_time_already_passed(): void
    {
        Cache::flush();
        $this->travelTo(Carbon::parse('2026-11-02 11:00'));
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 14:00'), 'origin' => 'catalogo']);
        $booking->update(['booking_status' => 'EXPIRADA', 'expires_at' => now()->subMinutes(5)]);

        // Otro cliente tomó el horario mientras tanto.
        $this->booking(['starts_at' => Carbon::parse('2026-11-02 14:00'), 'customer' => Customer::create(['name' => 'Otro', 'phone_e164' => '+56922222222'])]);
        $this->post(route('bookings.reactivate', $booking->code), ['date' => '2026-11-02', 'time' => '14:00', 'hold_minutes' => 30])
            ->assertSessionHasErrors('reactivar');
        $this->assertSame('EXPIRADA', $booking->fresh()->booking_status);

        // Un horario que ya pasó tampoco sirve.
        $this->post(route('bookings.reactivate', $booking->code), ['date' => '2026-11-02', 'time' => '09:00', 'hold_minutes' => 30])
            ->assertSessionHasErrors('reactivar');
        $this->assertSame('EXPIRADA', $booking->fresh()->booking_status);

        // Una reserva activa no se "reactiva".
        $active = $this->booking(['starts_at' => Carbon::parse('2026-11-02 18:00'), 'customer' => Customer::create(['name' => 'Tercero', 'phone_e164' => '+56933333333'])]);
        $this->post(route('bookings.reactivate', $active->code), ['date' => '2026-11-02', 'time' => '18:00', 'hold_minutes' => 30])
            ->assertSessionHasErrors('reactivar');
    }

    public function test_historical_sales_upload_is_support_only_simulates_without_saving_and_can_be_removed(): void
    {
        $file = fn () => new UploadedFile(base_path('tests/fixtures/planilla_ventas_ejemplo.csv'), 'planilla.csv', 'text/csv', null, true);
        $payload = fn (string $action) => ['archivo' => $file(), 'fuente' => 'planilla-2026-10', 'anio' => 2026, 'accion' => $action];

        // Un administrador no entra: es solo de soporte.
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('admin.historical.index'))->assertForbidden();
        $this->post(route('admin.historical.store'), $payload('cargar'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'soporte']));
        $this->get(route('admin.historical.index'))->assertOk()->assertSee('Todavía no hay planillas cargadas');

        // Simular muestra el resumen y no guarda nada.
        $this->post(route('admin.historical.store'), $payload('simular'))->assertRedirect(route('admin.historical.index'));
        $this->assertSame(0, HistoricalSale::count());
        $this->get(route('admin.historical.index'))->assertSee('Revisión (no se guardó nada)')->assertSee('Venta total');

        // Un Excel no sirve (tiene que ser CSV) y un archivo sin ventas tampoco.
        $excel = new UploadedFile(base_path('tests/fixtures/planilla_ventas_ejemplo.csv'), 'planilla.xlsx', 'application/octet-stream', null, true);
        $this->post(route('admin.historical.store'), ['archivo' => $excel] + $payload('cargar'))->assertSessionHasErrors('archivo');
        $empty = UploadedFile::fake()->createWithContent('vacio.csv', "A,B\n1,2\n");
        $this->post(route('admin.historical.store'), ['archivo' => $empty] + $payload('simular'))->assertSessionHasErrors('archivo');
        $this->assertSame(0, HistoricalSale::count());

        // Cargar guarda; el nombre se arregla solo ("Planilla 2026 10" queda "planilla-2026-10").
        $this->post(route('admin.historical.store'), ['fuente' => 'Planilla 2026 10'] + $payload('cargar'))->assertRedirect(route('admin.historical.index'));
        $this->assertSame(5, HistoricalSale::count());
        $this->get(route('admin.historical.index'))->assertSee('planilla-2026-10');

        // Detalle por día: el mes día a día, y al elegir un día cada una de sus ventas.
        $this->get(route('admin.historical.sales', ['mes' => '2026-10']))->assertOk()->assertSee('02/10')->assertSee('Total del mes')->assertSee('$139.000');
        $this->get(route('admin.historical.sales', ['mes' => '2026-10', 'dia' => '2026-10-02']))->assertOk()->assertSee('Ventas del')->assertSee('R0001')->assertSee('Boleta 900')->assertSee('Estudiantes $5000 OFF');

        // Ventas ("Historial de ventas"): el mes aparece en el historial y trae su bloque de la planilla.
        $this->get(route('sales.monthly', '2026-10'))->assertOk()
            ->assertSee('Planilla de recepción')->assertSee('Hora extra')->assertSee('Persona adicional')->assertSee('Otros extra core')->assertSee('Extras (consumos)')->assertSee('$139.000')->assertSee('Total planilla')->assertSee('planilla</span>', false)
            ->assertSeeInOrder(['Registro diario', 'Detalle de ventas', '<details class="planilla">'], false);

        // Un mes sin planilla no muestra el bloque.
        $this->get(route('sales.monthly', '2026-08'))->assertOk()->assertDontSee('<details class="planilla">', false);

        // Ventas del día: las ventas de la planilla de ese día, con su detalle en el panel; los nombres solo los ve soporte.
        $sale = HistoricalSale::where('source_row', 3)->firstOrFail();
        $this->get(route('sales.daily', '2026-10-02'))->assertOk()->assertSee('Planilla de recepción')->assertSee('Ana Prueba Uno')->assertSee(route('sales.historical', $sale), false);
        $this->get(route('sales.historical', $sale))->assertOk()->assertSee('Ana Prueba Uno')->assertSee('Boleta 900')->assertSee('R0001')->assertSee('Estudiantes $5000 OFF');
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('sales.daily', '2026-10-02'))->assertOk()->assertSee('Planilla de recepción')->assertDontSee('Ana Prueba Uno');
        $this->get(route('sales.historical', $sale))->assertOk()->assertDontSee('Ana Prueba Uno')->assertSee('solo los ve soporte');
        $this->actingAs(User::factory()->create(['role' => 'soporte']));

        // Quitarla borra sus ventas y a las personas ligadas.
        $this->delete(route('admin.historical.destroy'), ['fuente' => 'planilla-2026-10'])->assertRedirect();
        $this->assertSame(0, HistoricalSale::count());
        $this->assertSame(0, HistoricalGuest::count());
    }

    public function test_core_extras_list_starts_empty_and_can_take_names_found_in_the_sheets(): void
    {
        (new HistoricalSalesImporter)->import(base_path('tests/fixtures/planilla_ventas_ejemplo.csv'), 'planilla-2026-10', 2026);

        $this->get(route('admin.core_extras.index'))->assertOk()
            ->assertSee('El listado está vacío')->assertSee('HORA EXTRA')->assertSee('Agregar al listado');

        // Agregar uno de los de la planilla, y uno propio; no se repiten.
        $this->post(route('admin.core_extras.store'), ['name' => 'Hora extra'])->assertRedirect(route('admin.core_extras.index'));
        $this->post(route('admin.core_extras.store'), ['name' => 'Toalla adicional', 'price' => 3000])->assertSessionHasNoErrors();
        $this->post(route('admin.core_extras.store'), ['name' => '  toalla   ADICIONAL '])->assertSessionHasErrors('name');
        $this->assertSame(2, CoreExtra::count());
        $this->get(route('admin.core_extras.index'))->assertSee('En el listado')->assertSee('Toalla adicional');

        // Editar y quitar.
        $towel = CoreExtra::where('name', 'Toalla adicional')->firstOrFail();
        $this->put(route('admin.core_extras.update', $towel), ['name' => 'Toalla extra', 'price' => 3500, 'is_active' => 1])->assertRedirect();
        $this->assertSame(3500, $towel->fresh()->price);
        $this->delete(route('admin.core_extras.destroy', $towel))->assertRedirect();
        $this->assertSame(1, CoreExtra::count());
    }

    public function test_payment_screen_offers_the_mercado_pago_link_to_send_by_whatsapp(): void
    {
        PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);

        // Sin Mercado Pago configurado no se ofrece.
        $this->get(route('payments.create', $booking->code))->assertOk()->assertDontSee('Enviar por WhatsApp');

        config(['services.mercadopago.access_token' => 'TEST-token']);
        $page = $this->get(route('payments.create', $booking->code))->assertOk();
        $page->assertSeeInOrder(['Resumen de la reserva', 'Datos del pago', 'O cobra por link de pago', 'Cobrar por link (Mercado Pago)']);
        $page->assertSee('Cobrar por link (Mercado Pago)')
            ->assertSee(route('catalog.pay', $booking->pass_token), false)
            ->assertSee('https://wa.me/56911111111?text=', false);

        // Saldado: ya no hay nada que cobrar por link.
        app(PaymentService::class)->register($booking, PaymentMethod::where('code', 'efectivo')->first(), (int) $booking->price_final, null, null, auth()->id());
        $this->get(route('payments.create', $booking->code))->assertDontSee('Enviar por WhatsApp');
    }

    public function test_day_agenda_lists_every_reservation_with_its_operational_state(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 09:00'));
        $day = fn (string $time) => ['starts_at' => Carbon::parse('2026-11-02 '.$time)];
        $late = $this->booking($day('12:00'));
        $coming = $this->booking($day('14:00'));
        $inside = $this->booking($day('16:00'));
        $done = $this->booking($day('18:00'));
        $pending = $this->booking($day('20:00'));
        $lapsed = $this->booking($day('22:00'));
        foreach ([$late, $coming] as $b) {
            $b->update(['booking_status' => 'CONFIRMADA']);
        }
        $inside->update(['booking_status' => 'CHECK_IN', 'checked_in_at' => Carbon::parse('2026-11-02 16:05')]);
        $done->update(['booking_status' => 'FINALIZADA', 'checked_in_at' => Carbon::parse('2026-11-02 18:02'), 'checked_out_at' => Carbon::parse('2026-11-02 19:01')]);
        $lapsed->update(['booking_status' => 'EXPIRADA']);

        // 13:00: la de las 12:00 ya debería haber llegado y no hizo check-in.
        $this->travelTo(Carbon::parse('2026-11-02 13:00'));
        $page = $this->get(route('calendar.index', ['view' => 'day', 'date' => '2026-11-02']))->assertOk();

        $page->assertSeeInOrder(['Check-in', 'Check-out', 'Salida real', 'Hab.']);

        $states = $page->viewData('agenda')->mapWithKeys(fn ($b) => [$b->code => $b->agendaState()['key']]);
        $this->assertSame('atrasada', $states[$late->code]);
        $this->assertSame('por_llegar', $states[$coming->code]);
        $this->assertSame('en_alojamiento', $states[$inside->code]);
        $this->assertSame('finalizada', $states[$done->code]);
        $this->assertSame('pendiente', $states[$pending->code]);
        $this->assertSame('vencida', $states[$lapsed->code]);

        $page->assertSee('Reservas del día')->assertSee('Atrasada · sin check-in')->assertSee('En alojamiento')
            ->assertSee('Finalizada')->assertSee('Vencida (sin pago)')->assertSee('16:05')->assertSee('19:01')
            ->assertSee(route('bookings.checkin.show', $coming->code), false)
            ->assertSee(route('bookings.finalize.show', $inside->code), false);
    }

    public function test_public_confirmation_shows_qr_and_payment_data_when_configured(): void
    {
        $booking = $this->booking();

        $this->get(route('catalog.booked', $booking->pass_token))
            ->assertOk()->assertSee('data:image/png;base64', false)->assertSee('Descargar mi pase')
            ->assertDontSee('O paga por transferencia');

        config(['services.hh_payment.bank_titular' => 'Titular de prueba', 'services.hh_payment.bank_account_number' => '000111', 'services.hh_payment.mercadopago_link' => 'https://mpago.test/pago']);
        $this->get(route('catalog.booked', $booking->pass_token))
            ->assertSee('O paga por transferencia')->assertSee('Titular de prueba')->assertSee('000111')->assertSee('https://mpago.test/pago', false)->assertSee('ahora →', false);
    }

    public function test_public_page_puts_payment_first_and_hides_the_qr_while_the_online_hold_runs(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        $booking = $this->booking();
        $booking->update(['expires_at' => now()->addMinutes(10)]);

        $html = $this->get(route('catalog.booked', $booking->pass_token))->assertOk()
            ->assertSee('Falta un paso: paga tu reserva')->assertSee('hold-countdown', false)
            ->assertDontSee('data:image/png;base64', false)->assertSee('aparecerán aquí apenas se confirme el pago')
            ->getContent();
        $this->assertLessThan(strpos($html, 'Código</span>'), strpos($html, 'pay-btn'), 'el botón de pago va antes del resumen');
    }

    public function test_extra_hour_is_blocked_when_the_rate_has_no_price(): void
    {
        (require database_path('migrations/2026_09_16_001000_add_extra_hour_price_to_rate_rules.php'))->up();
        $booking = $this->booking();
        // La hora adicional se vende durante la estadía, no antes del check-in.
        $this->post(route('addons.extra-hour', $booking->code))->assertSessionHasErrors('booking');
        $booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        $endsAt = $booking->ends_at->copy();
        $rule = RateRule::where('name', 'HH')->first();

        $rule->update(['extra_hour_price' => 0]);
        $this->post(route('addons.extra-hour', $booking->code))->assertSessionHasErrors('booking');
        $this->assertTrue($booking->fresh()->ends_at->eq($endsAt));
        $this->assertSame(0, $booking->fresh()->addons()->count());

        $rule->update(['extra_hour_price' => 10000]);
        // Lo agregado en la estadía queda por cobrar: va directo a registrar el pago.
        $this->post(route('addons.extra-hour', $booking->code))->assertSessionHasNoErrors()->assertRedirect(route('payments.create', ['code' => $booking->code, 'after' => 'board']));
        $this->assertSame(10000, (int) $booking->fresh()->addons()->sum('amount'));
    }

    public function test_only_soporte_can_delete_bookings_even_after_check_in_and_stock_is_restored(): void
    {
        $booking0 = $this->booking(['starts_at' => Carbon::parse('2026-10-26 12:00')]);
        // Un administrador común no puede eliminar reservas.
        $this->delete(route('bookings.destroy', $booking0->code), ['confirm_text' => 'ELIMINAR'])->assertForbidden();
        $this->assertDatabaseHas('bookings', ['id' => $booking0->id]);
        $this->actingAs(User::factory()->create(['role' => 'soporte']));

        (require database_path('migrations/2026_08_19_150001_add_inventory_to_products_table.php'))->up();
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-12 12:00')]);
        app(PaymentService::class)->register($booking, $method, 20000, null, null, auth()->id());

        // Sin la palabra de confirmación: no se borra, aunque esté vigente.
        $this->delete(route('bookings.destroy', $booking->code), ['confirm_text' => 'si'])->assertSessionHasErrors('booking');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        $this->delete(route('bookings.destroy', $booking->code), ['confirm_text' => 'ELIMINAR'])->assertRedirect(route('rooms.board'));
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reserva.eliminar', 'entity_id' => $booking->id]);

        // Una estadía con check-in, finalizada, con pago y consumo también se puede limpiar (puesta en marcha).
        $product = Product::create(['name' => 'Bebida prueba', 'price' => 2000, 'track_inventory' => true, 'stock' => 10, 'is_active' => true]);
        $stay = $this->booking(['starts_at' => Carbon::parse('2026-10-19 12:00')]);
        app(ConsumptionService::class)->addProduct($stay, $product, 3, auth()->id());
        $this->assertSame(7, $product->fresh()->stock);
        app(PaymentService::class)->register($stay->fresh(), $method, $stay->fresh()->balanceDue(), null, null, auth()->id());
        $stay->update(['booking_status' => 'FINALIZADA', 'checked_in_at' => now(), 'checked_out_at' => now()]);

        $this->delete(route('bookings.destroy', $stay->code), ['confirm_text' => 'ELIMINAR'])->assertRedirect(route('rooms.board'));
        $this->assertDatabaseMissing('bookings', ['id' => $stay->id]);
        $this->assertDatabaseMissing('payments', ['booking_id' => $stay->id]);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reserva.eliminar', 'entity_id' => $stay->id]);
    }

    public function test_force_delete_cleans_test_clients_but_never_real_stays(): void
    {
        foreach (['2026_09_22_020000_add_blacklist_to_customers_and_segment_rules.php', '2026_09_22_030000_add_window_and_stars_to_customer_segment_rules.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);

        // Cliente de prueba: reserva cancelada con un pago.
        $test = Customer::create(['name' => 'Prueba pagada', 'phone_e164' => '+56944444444']);
        $cancelled = $this->booking(['customer' => $test, 'starts_at' => Carbon::parse('2026-10-12 12:00')]);
        app(PaymentService::class)->register($cancelled, $method, 20000, null, null, auth()->id());
        $cancelled->update(['booking_status' => 'CANCELADA']);

        $this->get(route('admin.customers.show', $test))->assertOk()->assertSee('Eliminar cliente y su historial de prueba')->assertSee('ELIMINAR');
        $this->delete(route('admin.customers.destroy', $test), ['with_bookings' => 1])->assertSessionHasErrors('customer');
        $this->delete(route('admin.customers.destroy', $test), ['force' => 1, 'confirm_text' => 'eliminar'])->assertSessionHasErrors('customer');
        $this->assertDatabaseHas('customers', ['id' => $test->id]);

        $this->delete(route('admin.customers.destroy', $test), ['force' => 1, 'confirm_text' => 'ELIMINAR'])->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $test->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $cancelled->id]);
        $this->assertDatabaseMissing('payments', ['booking_id' => $cancelled->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cliente.eliminar', 'entity_id' => $test->id]);

        // Cliente con una estadía que sí ocurrió: nunca, ni con force.
        $real = Customer::create(['name' => 'Estadía real', 'phone_e164' => '+56955555555']);
        $stay = $this->booking(['customer' => $real, 'starts_at' => Carbon::parse('2026-10-19 12:00')]);
        $stay->update(['booking_status' => 'FINALIZADA', 'checked_in_at' => now()]);
        $this->delete(route('admin.customers.destroy', $real), ['force' => 1, 'confirm_text' => 'ELIMINAR'])->assertSessionHasErrors('customer');
        $this->assertDatabaseHas('customers', ['id' => $real->id]);
        $this->assertDatabaseHas('bookings', ['id' => $stay->id]);
    }

    public function test_customer_deletion_respects_history(): void
    {
        $booking = $this->booking();

        // Con reservas, sin confirmar que se borran también: se rechaza.
        $this->delete(route('admin.customers.destroy', $this->customer))->assertSessionHasErrors('customer');
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);

        // Con un pago registrado nunca se borra, aunque se confirme.
        $method = PaymentMethod::create(['code' => 'test', 'name' => 'Prueba']);
        app(PaymentService::class)->register($booking, $method, 5000, null, null, auth()->id());
        $this->delete(route('admin.customers.destroy', $this->customer), ['with_bookings' => 1])->assertSessionHasErrors('customer');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        // Sin pagos ni consumos, con confirmación, se borra el cliente y sus reservas.
        $other = Customer::create(['name' => 'Solo prueba', 'phone_e164' => '+56933333333']);
        $test = $this->booking(['customer' => $other, 'starts_at' => Carbon::parse('2026-10-12 12:00')]);
        $this->delete(route('admin.customers.destroy', $other), ['with_bookings' => 1])->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $other->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $test->id]);

        // Un cliente que nunca reservó se elimina directo.
        $fresh = Customer::create(['name' => 'Sin reservas', 'phone_e164' => '+56922222222']);
        $this->delete(route('admin.customers.destroy', $fresh))->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $fresh->id]);
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
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin', ['accepted_upsells' => [$offer->id]])->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $this->assertSame($target->id, $booking->fresh()->room_id);
        $this->assertSame(1000, $booking->addonsTotal());
    }

    public function test_internal_form_rejects_disabled_category_and_floor(): void
    {
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

    public function test_reception_can_register_a_stay_that_started_up_to_12_hours_ago_but_the_catalog_cannot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 15:00'));
        $staff = User::factory()->create(['role' => 'recepcion']);

        $late = $this->booking(['starts_at' => Carbon::parse('2026-10-05 13:00'), 'created_by' => $staff->id]);
        $this->assertSame('2026-10-05 13:00', $late->starts_at->format('Y-m-d H:i'));

        try {
            $this->booking(['starts_at' => Carbon::parse('2026-10-05 02:00'), 'created_by' => $staff->id]);
            $this->fail('Más de 12 horas atrás no debería registrarse.');
        } catch (RoomNotAvailableException $e) {
            $this->assertStringContainsString('menos de 12 horas', $e->getMessage());
        }

        try {
            $this->booking(['starts_at' => Carbon::parse('2026-10-05 17:00')->subHours(3)]);
            $this->fail('El catálogo no debería aceptar una hora pasada.');
        } catch (RoomNotAvailableException $e) {
            $this->assertStringContainsString('en el pasado', $e->getMessage());
        }
    }

    public function test_a_walk_in_can_use_a_room_still_in_aseo_or_marked_occupied_without_booking(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        Staff::create(['name' => 'Dubraska', 'role' => 'Mucama', 'is_active' => true]);
        $this->room->update(['operational_status' => 'aseo', 'aseo_started_at' => now()->subHour()]);
        $this->assertStringContainsString('Estoy registrando a pasajeros que', $this->get('/reservar?modo=ingreso')->getContent());

        $data = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 10, 'time_minute' => 30,
            'duration_minutes' => 60, 'guests_count' => 2, 'customer_first_name' => 'Entro',
            'customer_last_name' => 'Antes', 'document_type' => 'rut', 'document_number' => '12.345.678-5', 'walk_in' => 1,
        ];
        // Sin decir quién hizo el aseo no se registra.
        $this->post('/reservar', $data)->assertSessionHasErrors('aseo_cleaned_by');
        $this->assertSame(0, Booking::count());

        $this->post('/reservar', $data + ['aseo_cleaned_by' => 'Dubraska'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
        $this->assertSame('activa', $this->room->fresh()->operational_status);
        $log = AuditLog::where('action', 'habitacion.aseo_listo')->firstOrFail();
        $this->assertSame('Dubraska', $log->new_value['cleaned_by']);

        // Marcada "Ocupada (sin reserva)" para registrar después: se puede ingresar y vuelve a activa.
        Booking::query()->delete();
        $this->room->update(['operational_status' => 'ocupada', 'operational_note' => 'Registrar luego']);
        $this->assertStringContainsString('Marcada ocupada sin reserva', $this->get('/reservar?modo=ingreso')->getContent());
        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
        $this->assertSame('activa', $this->room->fresh()->operational_status);
        $this->assertNull($this->room->fresh()->operational_note);

        // Una pieza en mantención no se puede elegir.
        Booking::query()->delete();
        $this->room->update(['operational_status' => 'mantencion']);
        $this->post('/reservar', $data)->assertSessionHasErrors();
        $this->assertSame(0, Booking::count());
    }

    public function test_registering_guests_who_already_left_keeps_the_room_in_aseo_and_checks_in_at_their_real_time(): void
    {
        (require database_path('migrations/2026_08_19_090003_add_consumption_offered_to_bookings_table.php'))->up();
        // 12:55 entraron, 15:55 salieron; a las 16:25 recepción los registra.
        $this->travelTo(Carbon::parse('2026-10-05 16:25'));
        $this->room->update(['operational_status' => 'aseo', 'aseo_started_at' => now()->subMinutes(20)]);
        $data = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 12, 'time_minute' => 55,
            'duration_minutes' => 60, 'guests_count' => 1, 'customer_first_name' => 'Ya', 'customer_last_name' => 'Salieron',
            'document_type' => 'rut', 'document_number' => '12.345.678-5', 'nationality' => 'Chilena', 'walk_in' => 1, 'aseo_mode' => 'ya_salieron',
        ];

        // Con la hora de ahora no tiene sentido: pide la hora real de entrada.
        $this->post('/reservar', ['time_hour' => 16, 'time_minute' => 30] + $data)->assertSessionHasErrors('aseo_mode');

        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasNoErrors();
        $booking = Booking::firstOrFail();
        $this->assertTrue($booking->isLateRegistration());
        $this->assertSame('aseo', $this->room->fresh()->operational_status);
        $this->assertSame(0, AuditLog::where('action', 'habitacion.aseo_listo')->count());

        // El check-in no los da por atrasados ni bloquea por horario terminado: entraron a las 12:55.
        $this->payRoom($booking)->get('/reservas/'.$booking->code.'/checkin')->assertOk()->assertDontSee('min tarde');
        $this->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('12:55', $booking->checked_in_at->format('H:i'));
        $this->assertSame('13:55', $booking->ends_at->format('H:i'));
        // Ya salieron y está todo pagado: queda finalizada y el Playroom sigue en aseo.
        $this->assertSame('FINALIZADA', $booking->booking_status);
        $this->assertSame('16:25', $booking->checked_out_at->format('H:i'));
        $this->assertSame('aseo', $this->room->fresh()->operational_status);
    }

    public function test_reservation_and_walk_in_forms_render_with_optional_phone_and_email(): void
    {
        foreach (['/reservar', '/reservar?presencial=1'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('name="customer_phone"', $html);
            $this->assertDoesNotMatchRegularExpression('/name="customer_phone"[^>]*required/', $html);
            $this->assertDoesNotMatchRegularExpression('/name="customer_email"[^>]*required/', $html);
        }
    }

    public function test_without_phone_or_email_the_client_is_identified_by_document_in_walk_ins_and_reservations(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $data = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 12, 'time_minute' => 0,
            'duration_minutes' => 60, 'guests_count' => 1, 'customer_first_name' => 'Sin',
            'customer_last_name' => 'Telefono', 'customer_phone' => '', 'customer_email' => '',
            'document_type' => 'rut', 'document_number' => '12.345.678-5', 'walk_in' => 1,
        ];

        // Una reserva para más tarde tampoco los exige.
        $this->post('/reservar', ['walk_in' => 0, 'time_hour' => 16] + $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
        Booking::query()->delete();

        $this->post('/reservar', $data)->assertRedirect()->assertSessionHasNoErrors();
        $customer = Booking::firstOrFail()->customer;
        $this->assertNull($customer->phone_e164);
        $this->assertNull($customer->email);
        $this->assertSame('12345678-5', $customer->rut);

        // Si vuelve sin teléfono, se reconoce por el RUT y no se duplica.
        Booking::query()->delete();
        $this->post('/reservar', ['time_hour' => 14] + $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($customer->id, Booking::firstOrFail()->customer_id);
    }

    public function test_a_planilla_sale_is_loaded_finished_and_paid_without_touching_the_room(): void
    {
        foreach (['2026_09_23_193653_create_guest_reviews_table.php', '2026_10_31_120000_add_review_rewards.php', '2026_08_19_150001_add_inventory_to_products_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->travelTo(Carbon::parse('2026-10-05 15:00', 'America/Santiago'));
        $debit = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        $water = Product::create(['name' => 'Agua con gas', 'category' => 'bebida', 'price' => 1500, 'track_inventory' => true, 'stock' => 5, 'low_stock_threshold' => 1, 'display_order' => 1, 'is_active' => true]);
        $this->room->update(['operational_status' => 'aseo']);
        $sale = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'start_time' => '10:45', 'end_time' => '13:30',
            'guests_count' => 2, 'customer_name' => 'Cliente Planilla', 'document_type' => 'rut', 'document_number' => '12.345.678-5',
            'amount' => 45000, 'payment_method' => 'debito', 'voucher_number' => '11',
        ];

        // Una estadía que todavía no termina no es una venta pasada.
        $this->post(route('admin.past_sales.store'), ['end_time' => '16:00'] + $sale)->assertSessionHasErrors('end_time');

        $this->post(route('admin.past_sales.store'), $sale)->assertRedirect()->assertSessionHasNoErrors();
        $booking = Booking::sole();
        $this->assertSame('FINALIZADA', $booking->booking_status);
        $this->assertSame('PAGADA', $booking->payment_status);
        $this->assertSame(165, $booking->duration_minutes);
        $this->assertNotNull($booking->review_requested_at);
        $payment = Payment::sole();
        $this->assertSame('11', $payment->voucher_number);
        $this->assertSame($debit->id, $payment->payment_method_id);
        $this->assertTrue($payment->created_at->eq(Carbon::parse('2026-10-05 10:45', 'America/Santiago')));
        $this->assertSame('aseo', $this->room->fresh()->operational_status);
        // Cargarla dos veces choca con la que ya está.
        $this->post(route('admin.past_sales.store'), $sale)->assertSessionHasErrors('room_id');
        $this->assertSame(1, Booking::count());

        // El consumo va con su propio pago y no descuenta stock (el conteo del turno ya lo refleja).
        $this->post(route('admin.past_sales.addon'), [
            'booking_code' => $booking->code, 'product_id' => $water->id, 'quantity' => 1, 'sold_at' => '11:20',
            'payment_method' => 'debito', 'voucher_number' => '12',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(46500, $booking->fresh()->paidAmount());
        $this->assertSame(5, $water->fresh()->stock);

        // Un cobro libre (ej. la diferencia de una hora adicional) va con descripción y monto, sin producto.
        $this->post(route('admin.past_sales.addon'), [
            'booking_code' => $booking->code, 'custom_description' => 'Diferencia hora adicional HOT', 'custom_amount' => 5000,
            'quantity' => 1, 'sold_at' => '12:40', 'payment_method' => 'debito', 'voucher_number' => '12', 'notes' => 'Ajuste de prueba',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(51500, $booking->fresh()->paidAmount());
        $this->assertSame('Diferencia hora adicional HOT', $booking->addons()->latest('id')->first()->description);
        $this->assertSame('Ajuste de prueba', Payment::latest('id')->first()->notes);
        // Sin producto ni monto no se carga nada.
        $this->post(route('admin.past_sales.addon'), ['booking_code' => $booking->code, 'quantity' => 1, 'sold_at' => '12:40', 'payment_method' => 'debito'])
            ->assertSessionHasErrors('product_id');

        // Recepción no puede cargar ventas pasadas.
        $this->actingAs(User::factory()->create(['role' => 'recepcion']))
            ->get(route('admin.past_sales.create'))->assertForbidden();
    }

    public function test_an_unused_coupon_can_be_deleted_and_a_used_one_is_kept_for_history(): void
    {
        (require database_path('migrations/2026_11_01_030000_add_is_public_to_coupons.php'))->up();
        $unused = Coupon::create(['code' => 'BORRAME', 'internal_name' => 'Sin uso', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true]);
        $used = Coupon::create(['code' => 'USADO', 'internal_name' => 'Con uso', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true]);
        $booking = $this->booking(['coupon_code' => 'USADO']);
        $this->assertSame($used->id, $booking->coupon_id);

        $this->delete(route('admin.coupons.destroy', $unused))->assertRedirect(route('admin.coupons.index'));
        $this->assertNull(Coupon::find($unused->id));

        $this->from(route('admin.coupons.index'))->delete(route('admin.coupons.destroy', $used))->assertSessionHasErrors('coupon');
        $this->assertNotNull(Coupon::find($used->id));
    }

    public function test_machine_charges_match_by_voucher_number_even_when_registered_late_or_shared_by_two_rooms(): void
    {
        Cache::flush();
        $this->travelTo(Carbon::parse('2026-10-05 20:00'));
        $debit = PaymentMethod::create(['code' => 'debito', 'name' => 'Débito']);
        $second = Room::create(['name' => 'GO 102', 'room_category_id' => $this->room->room_category_id, 'operational_status' => 'activa', 'buffer_minutes' => 30]);
        $a = $this->booking(['starts_at' => Carbon::parse('2026-10-05 21:00')]);
        $b = $this->booking(['room' => $second, 'starts_at' => Carbon::parse('2026-10-05 21:00')]);
        // Un solo cobro de máquina por las dos piezas, anotado una hora después y con voucher "08".
        foreach ([$a, $b] as $booking) {
            Payment::create(['booking_id' => $booking->id, 'payment_method_id' => $debit->id, 'amount' => 20000, 'status' => 'aprobado', 'voucher_number' => '8']);
        }
        $point = fn (int $i, string $at, int $amount) => ['id' => 900 + $i, 'status' => 'approved', 'transaction_amount' => $amount, 'date_approved' => $at,
            'payment_type_id' => 'debit_card', 'point_of_interaction' => ['type' => 'POINT'], 'external_reference' => 'Venta presencial'];
        $charges = [];
        for ($i = 1; $i <= 7; $i++) {
            $charges[] = $point($i, '2026-10-05T1'.$i.':00:00-03:00', 1000 + $i);
        }
        $charges[] = $point(8, '2026-10-05T18:50:00-03:00', 40000);
        $this->mock(MercadoPagoClient::class, fn ($m) => $m->shouldReceive('searchPayments')->andReturn($charges, []));

        $rows = app(MercadoPagoHistory::class)->month(\Illuminate\Support\Carbon::parse('2026-10-01', 'America/Santiago'))['rows'];
        $eight = $rows->firstWhere('correlative', '08');

        $this->assertTrue($eight['in_system']);
        $this->assertSame('GO 101 + GO 102', $eight['rooms']);
        $this->assertFalse($rows->firstWhere('correlative', '01')['in_system']);
    }

    public function test_walk_in_booking_goes_to_payment_then_check_in(): void
    {
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $data = [
            'room_id' => $this->room->id, 'date' => '2026-10-05', 'time_hour' => 12, 'time_minute' => 0,
            'duration_minutes' => 60, 'guests_count' => 1, 'customer_first_name' => 'Cliente',
            'customer_last_name' => 'Prueba', 'customer_phone' => $this->customer->phone_e164,
            'customer_email' => 'test@example.test', 'document_type' => 'pasaporte', 'document_number' => 'TEST123',
        ];

        // Reserva normal: sigue yendo al pago y de ahí al tablero.
        $this->post('/reservar', $data)->assertRedirect(route('payments.create', ['code' => Booking::first()->code, 'after' => 'board']));
        $this->assertSame('PENDIENTE_PAGO', Booking::first()->booking_status);

        // Ingreso presencial: pago y de ahí directo al check-in.
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        Booking::query()->delete();
        $this->post('/reservar', $data + ['walk_in' => 1])
            ->assertRedirect(route('payments.create', ['code' => Booking::first()->code, 'after' => 'checkin']));
        $booking = Booking::first();
        // Un ingreso presencial no queda "pendiente de gestionar": nace confirmado.
        $this->assertSame('CONFIRMADA', $booking->booking_status);
        $this->assertSame('NO_PAGADA', $booking->payment_status);

        $this->post(route('payments.store', $booking->code), [
            'payment_method_id' => $method->id, 'amount' => $booking->balanceDue(), 'request_token' => (string) Str::uuid(),
            'receipt_number' => '4411', 'after' => 'checkin',
        ])->assertRedirect(route('bookings.checkin.show', ['code' => $booking->code, 'flujo' => 'ingreso']));
        $this->assertSame(0, $booking->fresh()->balanceDue());
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
        $this->payRoom($later)->post('/reservas/'.$later->code.'/checkin')->assertRedirect()->assertSessionHasErrors('booking');
        $this->assertNull($later->fresh()->checked_in_at);
        $this->assertSame('PENDIENTE_PAGO', $later->fresh()->booking_status);
        $this->assertSame(0, $later->guests()->count());
        $this->travelTo(Carbon::parse('2026-10-05 12:00'));
        $this->payRoom($earlier)->post('/reservas/'.$earlier->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
    }

    public function test_allowed_early_checkin_protects_the_actual_occupancy_interval(): void
    {
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-10-05 14:00'), 'guests_count' => 1]);
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
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
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin')->assertSessionHasErrors('booking');
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->travelTo(Carbon::parse('2026-10-05 11:30'));
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
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
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin', ['accepted_upsells' => [$offer->id]])
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
        (require database_path('migrations/2026_08_19_090003_add_consumption_offered_to_bookings_table.php'))->up();
        $b = $this->reserve('14:00');
        $this->travelTo(Carbon::parse('2026-10-05 11:00'));
        $this->payRoom($b)->post('/reservas/'.$b->code.'/checkin')->assertRedirect()->assertSessionHasNoErrors();
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
        $this->payRoom($late)->post('/reservas/'.$late->code.'/checkin')->assertRedirect()->assertSessionHasErrors('booking');
        $this->assertSame('PENDIENTE_PAGO', $late->fresh()->booking_status);
        $this->assertNull($late->fresh()->checked_in_at);
        $this->travelTo(Carbon::parse('2026-10-05 14:00'));
        $this->payRoom($next)->post('/reservas/'.$next->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
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
        $this->post('/catalogo/reservar', $data + ['guests_count' => 1, 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'phone' => Customer::first()->phone_e164, 'email' => 'cliente@ejemplo.cl', 'document_type' => 'rut', 'document_number' => '11.111.111-1', 'accept_privacy' => 1])
            ->assertRedirect()->assertSessionHasErrors('duration_minutes');
        $this->assertSame(0, Booking::count());
        RateRule::where('is_active', true)->first()->prices()->create(['room_category_id' => Room::first()->room_category_id, 'duration_minutes' => 180, 'price' => 40000]);
        $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($data))->assertOk()->assertJsonPath('available', true);
        $this->post('/catalogo/reservar', $data + ['guests_count' => 1, 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'phone' => Customer::first()->phone_e164, 'email' => 'cliente@ejemplo.cl', 'document_type' => 'rut', 'document_number' => '11.111.111-1', 'accept_privacy' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
    }

    public function test_late_arrival_extends_when_there_is_room_and_opening_time(): void
    {
        // Tolerancia amplia: 110 min de atraso quedan dentro, el contador parte de cero.
        LateArrivalSetting::current()->update(['tolerance_minutes' => 120]);
        $booking = $this->reserve();
        $this->travelTo(Carbon::parse('2026-10-05 13:50'));
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin')->assertRedirect(route('rooms.board'))->assertSessionHasNoErrors();
        $this->assertSame('14:50', $booking->fresh()->ends_at->format('H:i'));
        $this->assertSame(20000, $booking->fresh()->price_final);
    }

    public function test_late_arrival_past_tolerance_cannot_check_in_after_its_time_ended(): void
    {
        // Regla por defecto (15 min, se descuenta el exceso): reserva de 1 h que
        // terminaba 13:00 sale 13:15; llegando 13:50 su horario ya terminó.
        $booking = $this->reserve();
        $this->travelTo(Carbon::parse('2026-10-05 13:50'));
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin')->assertSessionHasErrors('booking');
        $this->assertNull($booking->fresh()->checked_in_at);
    }

    public function test_late_arrival_after_closing_is_rejected_before_registering_checkin(): void
    {
        $booking = $this->reserve();
        RateRule::first()->windows()->update(['start_time' => '10:00:00', 'end_time' => '13:00:00', 'wraps_midnight' => false]);
        $this->travelTo(Carbon::parse('2026-10-05 13:50'));
        $this->payRoom($booking)->post('/reservas/'.$booking->code.'/checkin')->assertSessionHasErrors('booking');
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

    public function test_an_early_check_out_keeps_the_booked_departure_and_shows_both_times(): void
    {
        $booking = $this->booking(['created_by' => auth()->id()]);
        $booking->update(['booking_status' => 'CHECK_IN', 'checked_in_at' => Carbon::parse('2026-10-05 12:05')]);
        // Como al finalizar: salieron 12:40 y ends_at se acorta para liberar el Playroom.
        $booking->update(['booking_status' => 'FINALIZADA', 'checked_out_at' => Carbon::parse('2026-10-05 12:40'), 'ends_at' => Carbon::parse('2026-10-05 12:40')]);

        $booking->refresh();
        $this->assertSame('13:00', $booking->bookedEndsAt()->format('H:i'));
        $this->assertSame('12:40', $booking->ends_at->format('H:i'));
        $this->assertSame(-20, $booking->checkOutDiffMinutes());

        $this->get(route('rooms.bookings', $this->room))->assertOk()
            ->assertSee('Entró 12:05')->assertSee('Salió 12:40 (contratada 13:00 · 20 min antes)');
    }

    public function test_room_closing_snapshot_records_the_state_at_three_and_when_each_room_was_enabled_next_day(): void
    {
        $aseoRoom = Room::create(['name' => 'GO 102', 'room_category_id' => $this->room->room_category_id, 'operational_status' => 'activa', 'buffer_minutes' => 30]);
        $closing = app(RoomClosingLog::class);

        $this->travelTo(Carbon::parse('2026-11-02 20:00'));
        $aseoRoom->update(['operational_status' => 'aseo']);
        AuditLog::record(auth()->id(), 'habitacion.enviar_aseo', 'Room', $aseoRoom->id, ['operational_status' => 'activa'], ['operational_status' => 'aseo']);
        $this->assertSame(0, $closing->takeDueSnapshot(), 'el 1/11 cerró hace horas: no se toma foto atrasada');

        // Pasado el corte de las 03:00: foto del día 2/11, una sola vez.
        $this->travelTo(Carbon::parse('2026-11-03 03:05'));
        $this->assertSame(2, $closing->takeDueSnapshot());
        $this->assertSame(0, $closing->takeDueSnapshot());

        // En la mañana se habilita el que quedó en aseo, y se apaga un piso.
        $this->travelTo(Carbon::parse('2026-11-03 11:15'));
        $aseoRoom->update(['operational_status' => 'activa']);
        AuditLog::record(auth()->id(), 'habitacion.aseo_listo', 'Room', $aseoRoom->id, ['operational_status' => 'aseo'], ['operational_status' => 'activa', 'cleaned_by' => 'Ana']);
        $this->post(route('admin.floors.toggle', 'piso_2_sur_enabled'))->assertRedirect();

        $this->travelTo(Carbon::parse('2026-11-04 09:00'));
        $page = $this->get(route('aseo.closing', '2026-11-02'))->assertOk()->assertSee('Cierre del Lunes 2/11')->assertSee('Piso 2 Sur');
        $report = $page->viewData('report');
        $this->assertSame('foto', $report['source']);
        $this->assertSame([1, 1, 1, '11:15'], [$report['summary']['ready'], $report['summary']['aseo'], $report['summary']['opened'], $report['summary']['first_opened']->format('H:i')]);
        $row = $report['rows']->firstWhere('room.id', $aseoRoom->id);
        $this->assertSame(['aseo', '11:15', 'Ana'], [$row['state']['status'], $row['opened_at']->format('H:i'), $row['cleaned_by']]);

        // El 1/11 no tiene foto: se reconstruye (a esa hora el GO 102 todavía estaba lista).
        $day = $closing->day(Carbon::parse('2026-11-01'));
        $this->assertSame(['registro', 2, 0], [$day['source'], $day['summary']['ready'], $day['summary']['aseo']]);

        // El 3/11 cerró a las 03:00 del 4/11 y ya pasaron más de 30 minutos: no hay foto en vivo.
        $this->assertSame(0, $closing->takeDueSnapshot());
        $this->assertSame(['2026-11-03', '2026-11-02'], $page->viewData('history')->map(fn ($h) => $h['date']->toDateString())->all());
    }

    public function test_weekday_average_divides_by_every_such_day_of_the_month_and_compares_with_previous_months(): void
    {
        $sale = fn (string $date, int $amount) => HistoricalSale::create(['source' => 'planilla-test', 'source_row' => random_int(1, 999999), 'sold_on' => $date, 'sale_type' => 'ALOJAMIENTO', 'amount_main' => $amount, 'amount_extras' => 0]);
        $sale('2026-09-01', 10000);
        $sale('2026-09-07', 40000);
        $sale('2026-09-14', 20000);
        $sale('2026-10-26', 100000);
        $sale('2026-10-31', 10000);
        $this->travelTo(Carbon::parse('2026-11-03 12:00'));

        $result = app(WeekdayAverages::class)->forMonth(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $monday = $result['rows'][1];
        // Octubre: 4 lunes, solo uno vendió. Septiembre: 4 lunes, $60.000.
        $this->assertSame([4, 100000, 25000, 15000, 15000, 4], [$monday['days'], $monday['sales'], $monday['average'], $monday['last_month'], $monday['historical'], $monday['historical_days']]);
        $this->assertEqualsWithDelta(0.6667, $monday['vs_historical'], 0.001);
        $this->assertSame('planilla', $result['source']);
        $this->assertSame(['2026-09'], $result['months']->map(fn ($m) => $m['month']->format('Y-m'))->all());

        $this->get(route('sales.monthly', '2026-10'))->assertOk()->assertSee('Promedio por día de la semana vs. meses anteriores')->assertSee('+66,7%');
    }

    public function test_customer_lookup_by_rut_for_the_id_scanner(): void
    {
        $this->createSegmentRulesTable();

        $this->get(route('customers.lookup', ['rut' => '11.111.111-1']))
            ->assertOk()
            ->assertJson(['found' => true, 'phone' => '+56911111111', 'first_name' => 'Cliente']);

        $this->get(route('customers.lookup', ['rut' => '22222222-2']))->assertOk()->assertJson(['found' => false]);
        $this->get(route('customers.lookup', ['rut' => '11111111-2']))->assertOk()->assertJson(['found' => false]);
        $this->get(route('customers.lookup', ['phone' => '+56911111111']))->assertOk()->assertJson(['found' => true]);

        // Sin ficha: el nombre sale de las planillas (solo el nombre) y la lectura se cuenta sin guardar el RUT.
        $saleId = DB::table('historical_sales')->insertGetId(['source' => 'resumen-junio', 'source_row' => 1, 'sold_on' => '2026-06-12', 'sale_type' => 'ALOJAMIENTO', 'amount_main' => 30000]);
        DB::table('historical_guests')->insert(['historical_sale_id' => $saleId, 'name' => 'ANA MARÍA SOTO', 'document' => '12345678-5', 'document_kind' => 'rut']);

        $this->get(route('customers.lookup', ['rut' => '12.345.678-5', 'scan' => 1]))
            ->assertOk()
            ->assertExactJson(['found' => false, 'first_name' => 'Ana', 'last_name' => 'María Soto']);
        $this->get(route('customers.lookup', ['rut' => '22222222-2', 'scan' => 1]))->assertExactJson(['found' => false]);

        $this->assertSame(['planilla', 'nuevo'], AuditLog::where('action', 'carnet.escaneo')->orderBy('id')->get()->map(fn ($log) => $log->new_value['source'])->all());
        $this->assertStringNotContainsString('12345678', AuditLog::where('action', 'carnet.escaneo')->get()->toJson());
    }

    public function test_name_suggestions_are_loose_words_seen_more_than_once(): void
    {
        $lists = app(NameSuggestions::class)->build(collect([
            'MARÍA GONZALEZ', 'María Soto', 'Juan Carlos González Rojas', 'Juan Soto Rojas', 'Ana De La Fuente', 'X', 'Pedro 123',
        ]));

        $this->assertSame(['María', 'Juan'], $lists['first']);
        $this->assertSame(['González', 'Soto', 'Rojas'], $lists['last']);

        $this->get(route('customers.names'))->assertOk()->assertJsonStructure(['first', 'last']);

        // La lista base va detrás de lo propio, sin repetir y con su tilde.
        $merged = app(NameSuggestions::class)->withBase(['Gonzalez', 'Fuentealba'], 'apellidos.txt');
        $this->assertSame(['González', 'Fuentealba'], array_slice($merged, 0, 2));
        $this->assertSame(1, count(array_keys($merged, 'González', true)));
        $this->assertContains('Huenchumilla', $merged);
        $this->assertContains('Quispe', $merged);
        $this->assertNotContains('Gonzalez', $merged);
    }

    private function createSegmentRulesTable(): void
    {
        Schema::create('customer_segment_rules', function ($t) {
            $t->id();
            $t->string('segment');
            $t->integer('minimum_stays')->default(0);
            $t->integer('stars')->default(0);
        });
    }

    public function test_reception_only_sees_companions_during_the_stay(): void
    {
        $this->createSegmentRulesTable();
        $booking = $this->booking();
        BookingGuest::create(['booking_id' => $booking->id, 'name' => 'Persona Reservada', 'id_document' => '22222222-2']);
        $host = User::factory()->create(['role' => 'recepcion']);
        $admin = User::factory()->create(['role' => 'administrador']);

        $this->actingAs($host)->get(route('reservations.show', $booking->code))->assertOk()->assertSee('Persona Reservada');

        $booking->update(['booking_status' => 'FINALIZADA', 'checked_out_at' => now()]);

        $this->actingAs($host)->get(route('reservations.show', $booking->code))->assertOk()
            ->assertDontSee('Persona Reservada')->assertDontSee('22222222-2');
        $this->actingAs($admin)->get(route('reservations.show', $booking->code))->assertOk()->assertSee('Persona Reservada');
    }

    public function test_support_can_look_as_host_without_changing_anything(): void
    {
        $this->createSegmentRulesTable();
        Schema::create('staff_shift_logs', function ($t) {
            $t->id();
            $t->foreignId('user_id');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
        });
        $support = User::factory()->create(['role' => 'soporte']);
        $booking = $this->booking();
        $booking->update(['booking_status' => 'FINALIZADA', 'checked_out_at' => now()]);
        BookingGuest::create(['booking_id' => $booking->id, 'name' => 'Persona Reservada']);

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->post(route('host_view.start'))->assertForbidden();

        $this->actingAs($support)->get(route('reservations.show', $booking->code))->assertSee('Persona Reservada');
        $this->post(route('host_view.start'))->assertRedirect(route('rooms.board'));

        // Cada petición real carga la cuenta de nuevo; acá hay que hacerlo a mano.
        $asHost = fn () => $this->actingAs($support->fresh())->withSession([ApplyHostView::SESSION_KEY => true]);

        $asHost()->get(route('reservations.show', $booking->code))->assertOk()->assertDontSee('Persona Reservada')->assertSee('Vista de anfitrión');
        $asHost()->get(route('admin.mercadopago.index'))->assertForbidden();

        // Solo mirar: no guarda, no abre turno y la cuenta sigue siendo soporte.
        $asHost()->from(route('reservations.show', $booking->code))
            ->post(route('bookings.real_times', $booking->code), [])
            ->assertRedirect(route('reservations.show', $booking->code))->assertSessionHas('host_view_blocked');
        $this->assertSame('soporte', $support->fresh()->role);
        $this->assertSame(0, DB::table('staff_shift_logs')->where('user_id', $support->id)->count());

        $asHost()->post(route('host_view.stop'))->assertRedirect(route('rooms.board'));
        $this->assertFalse(session()->has(ApplyHostView::SESSION_KEY));
    }

    public function test_rooms_are_rented_first_floor_then_north_then_south(): void
    {
        $order = collect([
            ['name' => 'PLUS 311', 'wing' => 'sur'], ['name' => 'PLUS 207', 'wing' => 'sur'],
            ['name' => 'PLUS 101', 'wing' => null], ['name' => 'PLUS 302', 'wing' => 'norte'], ['name' => 'PLUS 202', 'wing' => 'norte'],
        ])->map(fn ($r) => new Room($r))->sortBy(fn (Room $r) => $r->rentOrder())->pluck('name')->all();

        $this->assertSame(['PLUS 101', 'PLUS 202', 'PLUS 302', 'PLUS 207', 'PLUS 311'], $order);
    }

    public function test_south_wing_only_enters_when_the_category_runs_out_of_first_floor_and_north(): void
    {
        $room = function (int $id, int $category, string $name, ?string $wing) {
            $model = new Room(['room_category_id' => $category, 'name' => $name, 'wing' => $wing]);
            $model->id = $id;

            return $model;
        };

        $offered = Room::offeredNow(collect([
            $room(1, 1, 'PLUS 101', null), $room(2, 1, 'PLUS 202', 'norte'), $room(3, 1, 'PLUS 207', 'sur'),
            $room(4, 2, 'MAX 310', 'sur'), $room(5, 2, 'MAX 210', 'sur'),
        ]));

        $this->assertEqualsCanonicalizing([1, 2, 5], $offered);
    }

    public function test_cash_payment_can_leave_the_receipt_for_later_but_transfer_cannot(): void
    {
        $booking = $this->booking();
        $cash = PaymentMethod::firstOrCreate(['code' => 'efectivo'], ['name' => 'Efectivo', 'is_active' => true]);
        $transfer = PaymentMethod::firstOrCreate(['code' => 'transferencia'], ['name' => 'Transferencia', 'is_active' => true]);

        $this->post(route('payments.store', $booking->code), ['payment_method_id' => $transfer->id, 'amount' => 1000, 'request_token' => (string) Str::uuid()])
            ->assertSessionHasErrors('receipt_number');

        $this->post(route('payments.store', $booking->code), ['payment_method_id' => $cash->id, 'amount' => 1000, 'request_token' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();

        $payment = $booking->payments()->first();
        $this->assertNull($payment->receipt_number);

        $this->put(route('payments.document', $payment), ['receipt_number' => '96302']);
        $this->assertSame('96302', $payment->fresh()->receipt_number);
    }

    public function test_shift_notes_alert_the_next_host_until_read_and_keep_who_read_them(): void
    {
        (require database_path('migrations/2026_11_04_120000_create_shift_notes_tables.php'))->up();
        $author = User::factory()->create(['role' => 'recepcion']);
        $next = User::factory()->create(['role' => 'recepcion']);

        $this->actingAs($author)->post(route('shift_notes.store'), ['category' => 'Caja', 'body' => 'Faltan monedas', 'urgent' => 1, 'pending' => 1])->assertSessionHasNoErrors();
        $note = ShiftNote::firstOrFail();

        $this->assertTrue(ShiftNote::alertFor($author)->isEmpty()); // no se avisa a quien la escribió
        $this->assertSame([$note->id], ShiftNote::alertFor($next)->pluck('id')->all());

        $this->actingAs($next)->post(route('shift_notes.read'))->assertRedirect();
        $this->assertTrue(ShiftNote::alertFor($next)->isEmpty());
        $this->assertTrue($note->readers()->whereKey($next->id)->exists());

        // El libro completo también es para las anfitrionas.
        $this->createSegmentRulesTable();
        $this->actingAs($next)->get(route('shift_notes.index'))->assertOk()->assertSee('Faltan monedas')->assertSee('Registrar novedad');
        $this->get(route('shift_notes.index', ['q' => 'monedas', 'desde' => now()->toDateString()]))->assertOk()->assertSee('Faltan monedas');
        $this->assertCount(0, $this->get(route('shift_notes.index', ['q' => 'ampolleta']))->assertOk()->viewData('notes'));

        // En vista de anfitrión soporte sí puede escribir una novedad.
        $support = User::factory()->create(['role' => 'soporte']);
        $this->actingAs($support)->withSession([ApplyHostView::SESSION_KEY => true])
            ->post(route('shift_notes.store'), ['category' => 'Otro', 'body' => 'Prueba desde soporte'])->assertSessionHasNoErrors();
        $this->assertSame($support->id, ShiftNote::where('body', 'Prueba desde soporte')->value('user_id'));
        ShiftNote::where('body', 'Prueba desde soporte')->delete();

        // Soporte en vista de anfitrión ve lo mismo que ella, aunque haya escrito la novedad.
        $this->assertSame([$note->id], ShiftNote::previewAlert()->pluck('id')->all());

        // Las del día siguen a la vista aunque ya se leyeron; salen al cambiar el día operativo.
        $this->assertSame([$note->id], ShiftNote::today()->pluck('id')->all());
        $plain = ShiftNote::create(['user_id' => $author->id, 'category' => 'Otro', 'body' => 'Dato del día']);
        $this->assertTrue(ShiftNote::today()->pluck('id')->contains($plain->id));
        $this->travel(2)->days();
        $this->assertFalse(ShiftNote::today()->pluck('id')->contains($plain->id));
        $this->assertTrue(ShiftNote::today()->pluck('id')->contains($note->id)); // pendiente: sigue
        $this->travelBack();
        $plain->delete();

        // Una pendiente vieja se sigue avisando; una normal vieja, no.
        $third = User::factory()->create(['role' => 'recepcion']);
        ShiftNote::whereKey($note->id)->update(['created_at' => now()->subDays(10)]);
        $this->assertSame([$note->id], ShiftNote::alertFor($third)->pluck('id')->all());

        $this->post(route('shift_notes.resolve', $note), ['resolution' => 'Se cambió sencillo'])->assertSessionHasNoErrors();
        $this->assertFalse($note->fresh()->isOpen());
        $this->assertTrue(ShiftNote::alertFor($third)->isEmpty());

        $this->assertTrue(ShiftNote::alertFor(User::factory()->create(['role' => 'administrador']))->isEmpty());

        // Al entrar se avisan las reservas de las próximas 24 horas, no las de después.
        $this->travelTo(Carbon::parse('2026-10-05 10:00'));
        $booking = $this->booking();
        $this->assertTrue(ShiftNote::upcomingFor($next)->pluck('code')->contains($booking->code));
        $booking->update(['starts_at' => Carbon::parse('2026-10-08 12:00'), 'ends_at' => Carbon::parse('2026-10-08 13:00')]);
        $this->assertFalse(ShiftNote::upcomingFor($next)->pluck('code')->contains($booking->code));
    }
}
