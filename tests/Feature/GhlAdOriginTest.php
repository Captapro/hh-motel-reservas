<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AnalyticsController;
use App\Models\Booking;
use App\Models\BookingAdOrigin;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Integrations\GhlAdOrigin;
use App\Services\Marketing\Attribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * De qué anuncio llegó el cliente de cada reserva: se lee del contacto de GHL por teléfono,
 * se guarda una vez y Analytics lo usa para mostrar las ventas por anuncio.
 */
class GhlAdOriginTest extends TestCase
{
    private Room $room;

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
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_18_120020_create_tracking_data_table.php',
            '2026_11_03_000000_add_marketing_tracking.php',
            '2026_11_11_130000_create_booking_ad_origins_table.php',
            '2026_11_11_160000_add_ctwa_to_booking_ad_origins.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        config([
            'services.ghl.private_token' => 'token-de-prueba',
            'services.ghl.location_id' => 'loc',
            'services.ghl.opportunities_since' => '2026-11-01',
        ]);

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
    }

    private function booking(string $phone, string $origin = 'catalogo', int $minutesOld = 30): Booking
    {
        $customer = Customer::create(['name' => 'Cliente '.$phone, 'phone_e164' => $phone]);
        $booking = Booking::create([
            'code' => 'HH-'.substr(md5($phone.microtime()), 0, 8), 'customer_id' => $customer->id, 'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-11-12 18:00'), 'ends_at' => Carbon::parse('2026-11-12 20:00'),
            'duration_minutes' => 120, 'guests_count' => 2, 'booking_status' => 'CONFIRMADA', 'payment_status' => 'PAGADA',
            'price_original' => 26000, 'price_final' => 26000, 'origin' => $origin,
        ]);
        $booking->forceFill(['created_at' => now()->subMinutes($minutesOld)])->save();

        return $booking;
    }

    private function fakeContact(string $phone, array $attributions): void
    {
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['contacts' => [['id' => 'c1', 'phone' => $phone, 'attributions' => $attributions]]])]);
    }

    public function test_it_picks_the_ad_from_the_contact_attributions(): void
    {
        $pick = GhlAdOrigin::pickAttribution([
            ['utmSessionSource' => 'Paid Social', 'isFirst' => true, 'medium' => 'whatsapp', 'utmAdId' => '120253089036650584', 'adName' => 'HH Playrooms · Santiago Centro'],
            ['utmSessionSource' => 'Paid Social', 'isLast' => true, 'medium' => 'whatsapp'],
        ]);
        $this->assertSame('120253089036650584', $pick['ad_id']);
        $this->assertSame('Paid Social', $pick['source']);

        $organic = GhlAdOrigin::pickAttribution([['utmSessionSource' => 'Social media', 'isFirst' => true, 'medium' => 'whatsapp']]);
        $this->assertNull($organic['ad_id']);
        $this->assertSame('Social media', $organic['source']);

        $this->assertNull(GhlAdOrigin::pickAttribution([])['source']);
    }

    public function test_a_booking_gets_its_ad_once_and_analytics_credits_meta(): void
    {
        $booking = $this->booking('+56911112222', 'recepcion');
        $this->fakeContact('+56911112222', [['utmSessionSource' => 'Paid Social', 'medium' => 'whatsapp', 'utmAdId' => '120253089036650584', 'adName' => 'HH Playrooms']]);

        $this->assertSame(1, app(GhlAdOrigin::class)->resolvePending());

        $origin = $booking->fresh()->adOrigin;
        $this->assertSame('120253089036650584', $origin->meta_ad_id);
        $this->assertTrue($origin->contact_found);
        [$channel, $detail] = Attribution::channelOf($booking->fresh(['adOrigin']));
        $this->assertSame('Meta · WhatsApp', $channel);
        $this->assertStringContainsString('120253089036650584', $detail);

        // Ya resuelta: no se vuelve a consultar a GHL.
        Http::fake();
        $this->assertSame(0, app(GhlAdOrigin::class)->resolvePending());
        Http::assertNothingSent();
    }

    public function test_a_client_who_wrote_on_their_own_is_not_credited_to_an_ad(): void
    {
        $booking = $this->booking('+56933334444', 'recepcion');
        $this->fakeContact('+56933334444', [['utmSessionSource' => 'Social media', 'medium' => 'whatsapp']]);

        app(GhlAdOrigin::class)->resolvePending();

        [$channel] = Attribution::channelOf($booking->fresh(['adOrigin']));
        $this->assertNotSame('Meta · WhatsApp', $channel);
        $this->assertSame('Reserva asistida', $channel);
    }

    public function test_own_google_label_is_not_overridden(): void
    {
        $booking = $this->booking('+56955556666', 'catalogo');
        $booking->trackingData()->create(['session_id' => 's', 'source_label' => 'Google · gads_busqueda_motel']);
        $this->fakeContact('+56955556666', [['utmSessionSource' => 'Paid Social', 'utmAdId' => '1', 'medium' => 'whatsapp']]);

        app(GhlAdOrigin::class)->resolvePending();

        [$channel] = Attribution::channelOf($booking->fresh(['adOrigin', 'trackingData']));
        $this->assertSame('Google · gads_busqueda_motel', $channel);
    }

    public function test_missing_contact_is_retried_a_few_times_then_dropped(): void
    {
        $booking = $this->booking('+56977778888');
        Http::fake(['services.leadconnectorhq.com/*' => Http::response(['contacts' => []])]);

        app(GhlAdOrigin::class)->resolvePending();
        $this->assertSame(1, $booking->fresh()->adOrigin->attempts);
        $this->assertFalse($booking->fresh()->adOrigin->contact_found);

        // Antes de que pasen los minutos de espera no reintenta.
        app(GhlAdOrigin::class)->resolvePending();
        $this->assertSame(1, $booking->fresh()->adOrigin->attempts);

        foreach ([2, 3] as $n) {
            BookingAdOrigin::where('booking_id', $booking->id)->update(['checked_at' => now()->subMinutes(30)]);
            app(GhlAdOrigin::class)->resolvePending();
            $this->assertSame($n, $booking->fresh()->adOrigin->attempts);
        }

        BookingAdOrigin::where('booking_id', $booking->id)->update(['checked_at' => now()->subMinutes(30)]);
        app(GhlAdOrigin::class)->resolvePending();
        $this->assertSame(GhlAdOrigin::MAX_ATTEMPTS, $booking->fresh()->adOrigin->attempts);
    }

    public function test_a_ghl_failure_does_not_burn_attempts_and_nothing_runs_without_config(): void
    {
        $booking = $this->booking('+56999990000');
        Http::fake(['services.leadconnectorhq.com/*' => Http::response('boom', 500)]);

        $this->assertSame(0, app(GhlAdOrigin::class)->resolvePending());
        $this->assertNull($booking->fresh()->adOrigin);

        config(['services.ghl.private_token' => '']);
        Http::fake();
        $this->assertSame(0, (new GhlAdOrigin(token: '', locationId: ''))->resolvePending());
        Http::assertNothingSent();
    }

    public function test_analytics_groups_sales_by_channel_and_ad(): void
    {
        $a = $this->booking('+56911110001', 'recepcion');
        $b = $this->booking('+56911110002', 'recepcion');
        $c = $this->booking('+56911110003', 'presencial');
        foreach ([$a, $b] as $i => $bk) {
            BookingAdOrigin::create(['booking_id' => $bk->id, 'meta_ad_id' => '120253089036650584', 'wa_source' => 'Paid Social', 'contact_found' => true, 'attempts' => 1, 'checked_at' => now()]);
        }

        $method = new \ReflectionMethod(AnalyticsController::class, 'byChannel');
        $result = $method->invoke(new AnalyticsController, Booking::with(['adOrigin', 'trackingData'])->get(), fn (Booking $bk) => (int) $bk->price_final);

        $rows = $result['rows']->keyBy('label');
        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $rows['Meta · WhatsApp']['count']);
        $this->assertSame(52000, $rows['Meta · WhatsApp']['value']);
        $this->assertSame(2, $rows['Meta · WhatsApp']['ads'][0]['count']);
        $this->assertStringContainsString('120253089036650584', $rows['Meta · WhatsApp']['ads'][0]['label']);
        $this->assertSame(1, $rows['Presencial']['count']);
    }

    public function test_the_whatsapp_ad_click_id_is_read_from_the_contact_detail(): void
    {
        $booking = $this->booking('+56955224224', 'recepcion');
        Http::fake([
            'services.leadconnectorhq.com/contacts/c1' => Http::response(['contact' => [
                'attributionSource' => ['sessionSource' => 'Paid Social', 'medium' => 'whatsapp', 'adId' => '120253089036650584', 'adName' => 'HH Playrooms · Santiago Centro', 'ctwaClid' => 'PRIMER-CLIC'],
                'lastAttributionSource' => ['sessionSource' => 'Paid Social', 'medium' => 'whatsapp', 'adId' => '120253089036650584', 'ctwaClid' => 'ULTIMO-CLIC'],
            ]]),
            'services.leadconnectorhq.com/contacts/*' => Http::response(['contacts' => [[
                'id' => 'c1', 'phone' => '+56955224224',
                'attributions' => [['utmSessionSource' => 'Paid Social', 'medium' => 'whatsapp', 'utmAdId' => '120253089036650584', 'adName' => 'HH Playrooms']],
            ]]]),
        ]);

        app(GhlAdOrigin::class)->resolvePending();

        $origin = $booking->fresh()->adOrigin;
        $this->assertSame('ULTIMO-CLIC', $origin->ctwa_clid); // el clic más reciente es el que cuenta para Meta
        $this->assertSame('120253089036650584', $origin->meta_ad_id);
        $this->assertSame('HH Playrooms · Santiago Centro', $origin->meta_ad_name);
    }

    public function test_a_client_who_did_not_come_from_an_ad_costs_no_detail_call(): void
    {
        $this->booking('+56966667777', 'recepcion');
        $this->fakeContact('+56966667777', [['utmSessionSource' => 'Social media', 'medium' => 'whatsapp']]);

        app(GhlAdOrigin::class)->resolvePending();

        Http::assertSentCount(1); // solo la búsqueda por teléfono
    }
}
