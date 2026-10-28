<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\RateRule;
use App\Models\RateRulePrice;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\ShortStaySetting;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * findAvailableRoom() (compartida por checkAvailability() y store() del
 * catálogo público) no comprobaba dos cosas que store() sí exige después
 * de todos modos vía otras rutas:
 *  1. RoomCategory::is_active -- una pestaña abierta antes de apagar la
 *     categoría podía completar la reserva igual.
 *  2. El horario operativo de la tarifa -- el chequeo en vivo contestaba
 *     "disponible" para un horario que store() iba a rechazar por tarifa,
 *     mandando al cliente a llenar todo el formulario para nada.
 */
class PublicBookingValidationTest extends TestCase
{
    private RoomCategory $category;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-01 12:00'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
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
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_10_26_220000_create_late_arrival_settings_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
            '2026_09_16_040000_add_wing_to_rooms.php',
            '2026_09_16_040100_create_operational_settings_table.php',
            '2026_09_17_002913_add_floor_toggles_to_operational_settings.php',
            '2026_09_21_135116_split_floor_toggles_by_wing_in_operational_settings.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
            '2026_10_28_130000_add_privacy_consent_to_customers.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->category = RoomCategory::create(['name' => 'GO', 'display_order' => 1, 'is_active' => true]);
        $this->room = Room::create(['room_category_id' => $this->category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        $rateRule = RateRule::create(['name' => 'HH', 'is_active' => true]);
        // Lunes 10:00-23:00 -- deja un hueco real fuera de esa ventana para
        // probar el horario "que se anuncia disponible pero se rechaza".
        RateRuleWindow::create(['rate_rule_id' => $rateRule->id, 'weekday' => 1, 'start_time' => '10:00:00', 'end_time' => '23:00:00', 'wraps_midnight' => false]);
        RateRulePrice::create(['rate_rule_id' => $rateRule->id, 'room_category_id' => $this->category->id, 'duration_minutes' => 180, 'price' => 20000]);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'room_category_id' => $this->category->id,
            'date' => '2026-10-05', // lunes
            'time_hour' => 14,
            'time_minute' => 0,
            'duration_minutes' => 180,
            'guests_count' => 2,
            'first_name' => 'Cliente',
            'last_name' => 'Público',
            'phone' => '+56922222222',
            'email' => 'cliente@ejemplo.cl',
            'accept_privacy' => 1,
        ], $overrides);
    }

    public function test_check_availability_rejects_a_disabled_category(): void
    {
        $this->category->update(['is_active' => false]);

        $response = $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query([
            'room_category_id' => $this->category->id,
            'date' => '2026-10-05',
            'time_hour' => 14,
            'time_minute' => 0,
            'duration_minutes' => 180,
        ]));

        $response->assertJson(['available' => false]);
    }

    public function test_store_rejects_a_disabled_category_even_with_a_stale_form(): void
    {
        // La categoría estaba activa cuando se cargó el formulario, pero se
        // apagó antes de que el cliente enviara -- store() la tiene que
        // rechazar igual, no solo el chequeo en vivo.
        $this->category->update(['is_active' => false]);

        $response = $this->post('/catalogo/reservar', $this->bookingPayload());

        $response->assertSessionHasErrors();
        $this->assertSame(0, Booking::count());
    }

    public function test_double_submit_from_the_catalog_creates_a_single_booking(): void
    {
        // Con una segunda habitación libre, el segundo envío antes tomaba esa y quedaban dos reservas.
        Room::create(['room_category_id' => $this->category->id, 'name' => 'GO 102', 'operational_status' => 'activa']);

        $first = $this->post('/catalogo/reservar', $this->bookingPayload());
        $second = $this->post('/catalogo/reservar', $this->bookingPayload());

        $this->assertSame(1, Booking::count());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
    }

    public function test_catalog_offers_only_arrival_times_inside_the_tariff_schedule(): void
    {
        // Lunes 10:00-23:00 con precio solo a 3 h: sin estadía corta, la última llegada posible es las 20:00.
        ShortStaySetting::current()->update(['enabled' => false]);
        $monday = $this->getJson('/catalogo/reservar/horarios?'.http_build_query(['date' => '2026-10-05', 'room_category_id' => $this->category->id]))->assertOk()->json('times');
        $this->assertSame('10:00', $monday[0]);
        $this->assertSame('20:00', end($monday));
        $this->assertNotContains('09:45', $monday);
        $this->assertNotContains('20:15', $monday);

        // Con la estadía corta (por defecto: de 1h30 a 30 min antes del cierre) se suman 21:30 a 22:30.
        ShortStaySetting::current()->update(['enabled' => true]);
        $withShort = $this->getJson('/catalogo/reservar/horarios?'.http_build_query(['date' => '2026-10-05', 'room_category_id' => $this->category->id]))->assertOk()->json('times');
        $this->assertContains('21:30', $withShort);
        $this->assertSame('22:30', end($withShort));
        $this->assertNotContains('21:15', $withShort);

        // Martes: no hay tarifa cargada, no se ofrece ninguna hora.
        $this->getJson('/catalogo/reservar/horarios?'.http_build_query(['date' => '2026-10-06', 'room_category_id' => $this->category->id]))->assertOk()->assertExactJson(['times' => []]);
    }

    public function test_catalog_short_stay_requires_accepting_the_closing_time(): void
    {
        // Lunes 22:00, cierre 23:00, mínimo 3 h: solo como estadía corta.
        $late = ['time_hour' => 22, 'time_minute' => 0];

        $check = $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($this->bookingPayload($late)))->assertOk();
        $this->assertTrue($check->json('available'));
        $this->assertSame('23:00', $check->json('short_stay.closes_at'));
        $this->assertSame(10000, $check->json('short_stay.price'));

        $this->post('/catalogo/reservar', $this->bookingPayload($late))->assertSessionHasErrors('duration_minutes');
        $this->assertSame(0, Booking::count());

        $this->post('/catalogo/reservar', $this->bookingPayload($late + ['short_stay_accept' => 1]))->assertSessionHasNoErrors();
        $booking = Booking::first();
        $this->assertTrue($booking->short_stay);
        $this->assertTrue(Carbon::parse('2026-10-05 23:00')->eq($booking->ends_at));
        $this->assertSame(10000, (int) $booking->price_final);
    }

    public function test_catalog_booking_requires_a_valid_email(): void
    {
        $this->post('/catalogo/reservar', $this->bookingPayload(['email' => '']))->assertSessionHasErrors('email');
        $this->post('/catalogo/reservar', $this->bookingPayload(['email' => 'no-es-un-correo']))->assertSessionHasErrors('email');
        $this->assertSame(0, Booking::count());

        $this->post('/catalogo/reservar', $this->bookingPayload())->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
    }

    public function test_catalog_fills_the_north_wing_before_the_south_wing(): void
    {
        // GO 102 (sur) va antes que GO 103 (norte) por nombre; igual se asigna el norte.
        $this->room->update(['name' => 'GO 101', 'wing' => 'sur']);
        $north = Room::create(['room_category_id' => $this->category->id, 'name' => 'GO 103', 'wing' => 'norte', 'operational_status' => 'activa']);

        $this->post('/catalogo/reservar', $this->bookingPayload())->assertSessionHasNoErrors();
        $this->assertSame($north->id, Booking::first()->room_id);

        // Con el norte ocupado en ese horario, recién ahí se usa el sur.
        $this->post('/catalogo/reservar', $this->bookingPayload(['phone' => '+56933333333', 'first_name' => 'Otra']))->assertSessionHasNoErrors();
        $this->assertSame($this->room->id, Booking::orderByDesc('id')->first()->room_id);
    }

    public function test_catalog_booking_is_held_for_ten_minutes_and_then_releases_the_room(): void
    {
        $this->post('/catalogo/reservar', $this->bookingPayload())->assertSessionHasNoErrors();
        $booking = Booking::first();
        $this->assertSame('PENDIENTE_PAGO', $booking->booking_status);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $booking->expires_at->timestamp, 5);

        // Dentro de los 10 minutos la habitación sigue tomada.
        $this->post('/catalogo/reservar', $this->bookingPayload(['phone' => '+56933333333', 'first_name' => 'Otra']))->assertSessionHasErrors('duration_minutes');
        $this->assertSame(1, Booking::count());

        // Vencida la retención sin pago, se libera y otra persona la puede reservar.
        $this->travel(11)->minutes();
        $this->post('/catalogo/reservar', $this->bookingPayload(['phone' => '+56933333333', 'first_name' => 'Otra']))->assertSessionHasNoErrors();
        $this->assertSame('EXPIRADA', $booking->fresh()->booking_status);
        $this->assertSame(2, Booking::count());
    }

    public function test_check_availability_agrees_with_store_outside_the_operating_window(): void
    {
        $payload = [
            'room_category_id' => $this->category->id,
            'date' => '2026-10-05',
            'time_hour' => 5, // fuera de la ventana 10:00-23:00
            'time_minute' => 0,
            'duration_minutes' => 180,
        ];

        $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($payload))
            ->assertJson(['available' => false]);

        $this->post('/catalogo/reservar', $this->bookingPayload(['time_hour' => 5]))
            ->assertSessionHasErrors();
        $this->assertSame(0, Booking::count());
    }

    public function test_store_still_succeeds_within_the_operating_window_for_an_active_category(): void
    {
        $response = $this->post('/catalogo/reservar', $this->bookingPayload());

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Booking::count());
    }

    public function test_with_online_payment_the_customer_goes_straight_to_mercado_pago(): void
    {
        // Un token pegado en Render con un salto de línea al final no debe romper el cobro.
        foreach (['2026_08_18_120014_create_booking_addons_table.php', '2026_08_18_120015_create_payment_methods_table.php', '2026_08_18_120016_create_payments_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        config(['services.mercadopago.access_token' => "APP_USR-token-de-prueba\n"]);
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['init_point' => 'https://www.mercadopago.cl/checkout/v1/redirect?pref_id=123'])]);

        $this->post('/catalogo/reservar', $this->bookingPayload())->assertSessionHasNoErrors();
        $booking = Booking::firstOrFail();

        $this->get(route('catalog.pay', $booking->pass_token))
            ->assertRedirect('https://www.mercadopago.cl/checkout/v1/redirect?pref_id=123');
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer APP_USR-token-de-prueba'));
    }

    public function test_store_sends_the_customer_to_the_payment_when_mercado_pago_is_on(): void
    {
        config(['services.mercadopago.access_token' => 'APP_USR-token-de-prueba']);

        $response = $this->post('/catalogo/reservar', $this->bookingPayload());

        $response->assertRedirect(route('catalog.pay', Booking::firstOrFail()->pass_token));
    }

    public function test_booking_requires_the_privacy_notice_and_records_consent_separately_from_promotions(): void
    {
        // Sin aceptar el aviso de privacidad no se reserva.
        $this->post('/catalogo/reservar', $this->bookingPayload(['accept_privacy' => null]))->assertSessionHasErrors('accept_privacy');
        $this->assertSame(0, Booking::count());

        // Acepta el aviso pero no las promociones: queda constancia solo de lo primero.
        $this->post('/catalogo/reservar', $this->bookingPayload())->assertSessionHasNoErrors();
        $customer = Customer::where('phone_e164', '+56922222222')->firstOrFail();
        $this->assertNotNull($customer->privacy_accepted_at);
        $this->assertFalse($customer->marketing_opt_in);
        $this->assertNull($customer->marketing_opt_in_at);

        // Después acepta las promociones: se enciende con fecha, y no se apaga por no marcarlo la próxima vez.
        $this->post('/catalogo/reservar', $this->bookingPayload(['marketing_opt_in' => 1, 'date' => '2026-10-12']))->assertSessionHasNoErrors();
        $this->assertTrue($customer->fresh()->marketing_opt_in);
        $this->assertNotNull($customer->fresh()->marketing_opt_in_at);
        $this->post('/catalogo/reservar', $this->bookingPayload(['date' => '2026-10-19']))->assertSessionHasNoErrors();
        $this->assertTrue($customer->fresh()->marketing_opt_in);
    }

    public function test_privacy_notice_page_and_form_checkboxes_are_available(): void
    {
        $this->get(route('catalog.privacy'))->assertOk()->assertSee('Aviso de privacidad')->assertSee('Tus derechos');
        $this->get(route('catalog.reserve', ['categoria' => $this->category->id]))->assertOk()
            ->assertSee('name="accept_privacy"', false)->assertSee('name="marketing_opt_in"', false)->assertSee(route('catalog.privacy'), false);
    }
}
