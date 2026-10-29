<?php

namespace Tests\Feature;

use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BookingCancelController;
use App\Http\Controllers\RoomBoardController;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CoreExtra;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
use App\Models\LateArrivalSetting;
use App\Models\OperationalSetting;
use App\Models\Payment;
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
use App\Services\Booking\RoomBoardService;
use App\Services\Historical\HistoricalSalesImporter;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Payments\MercadoPagoPayments;
use App\Support\GuestLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_pass_qr_verifies_on_the_phone_and_alerts_the_desktop_which_then_goes_to_what_is_pending(): void
    {
        Cache::flush();
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo']);
        $booking = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00')]);

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

    public function test_customer_behavior_page_counts_new_returning_and_at_risk_clients(): void
    {
        foreach (['2026_09_11_090000_add_identity_fields_to_customers_table.php', '2026_09_22_020000_add_blacklist_to_customers_and_segment_rules.php', '2026_09_22_030000_add_window_and_stars_to_customer_segment_rules.php'] as $file) {
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
        $this->assertSame(2, $data['active']);      // newbie + returning vinieron en agosto
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
        $this->assertSame(3000, (int) $totals['extras']);
        $this->assertSame((int) $booking->price_final + 3000, (int) $totals['total']);
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
        $this->assertSame([25000, 5000, 25000], [(int) $totals['total'], (int) $totals['extras'], (int) $totals['cash_total']]);
        $board = $this->boardDailySummary();
        $this->assertSame(5000, $board['extras_revenue']);
    }

    public function test_late_online_payment_after_hold_expired_is_cash_but_not_a_sale_and_frees_the_slot(): void
    {
        Cache::flush();
        config(['services.mercadopago.access_token' => 'TEST-token', 'services.mercadopago.webhook_secret' => 'sekret']);
        $this->travelTo(Carbon::parse('2026-11-02 11:00'));
        $held = $this->booking(['starts_at' => Carbon::parse('2026-11-02 12:00'), 'origin' => 'catalogo']);
        $held->update(['expires_at' => now()->addMinutes(10)]);

        // Pasados los 10 minutos, otro cliente puede tomar el mismo horario.
        $this->travelTo(now()->addMinutes(11));
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
            ->assertSee('Planilla de recepción')->assertSee('Hora extra')->assertSee('Persona adicional')->assertSee('Otros extra core')->assertSee('Extras (consumos)')->assertSee('$139.000')->assertSee('Total planilla')->assertSee('planilla</span>', false);

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
        foreach (['2026_09_11_090000_add_identity_fields_to_customers_table.php', '2026_09_22_020000_add_blacklist_to_customers_and_segment_rules.php', '2026_09_22_030000_add_window_and_stars_to_customer_segment_rules.php'] as $file) {
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

    public function test_walk_in_booking_goes_to_payment_then_check_in(): void
    {
        (require database_path('migrations/2026_09_11_090000_add_identity_fields_to_customers_table.php'))->up();
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
        foreach (['2026_08_19_090003_add_consumption_offered_to_bookings_table.php', '2026_08_26_120001_add_aseo_override_to_rooms_table.php', '2026_08_26_140001_add_aseo_started_to_rooms_table.php'] as $f) {
            (require database_path('migrations/'.$f))->up();
        }
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
        $this->post('/catalogo/reservar', $data + ['guests_count' => 1, 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'phone' => Customer::first()->phone_e164, 'email' => 'cliente@ejemplo.cl', 'accept_privacy' => 1])
            ->assertRedirect()->assertSessionHasErrors('duration_minutes');
        $this->assertSame(0, Booking::count());
        RateRule::where('is_active', true)->first()->prices()->create(['room_category_id' => Room::first()->room_category_id, 'duration_minutes' => 180, 'price' => 40000]);
        $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($data))->assertOk()->assertJsonPath('available', true);
        $this->post('/catalogo/reservar', $data + ['guests_count' => 1, 'first_name' => 'Cliente', 'last_name' => 'Prueba', 'phone' => Customer::first()->phone_e164, 'email' => 'cliente@ejemplo.cl', 'accept_privacy' => 1])
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
}
