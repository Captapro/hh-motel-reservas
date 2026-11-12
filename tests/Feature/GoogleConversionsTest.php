<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\TrackingData;
use App\Services\Marketing\GoogleConversions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Las ventas pagadas que llegaron por un anuncio de Google se avisan una sola vez a la hoja de
 * Apps Script, con su clic y su hora real, y solo se marcan si la hoja las recibió.
 */
class GoogleConversionsTest extends TestCase
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
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_08_20_110002_add_combo_id_to_booking_addons_table.php',
            '2026_10_27_130000_add_choice_items_to_combos.php',
            '2026_11_10_150000_add_stock_deducted_at_to_booking_addons.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120020_create_tracking_data_table.php',
            '2026_11_03_000000_add_marketing_tracking.php',
            '2026_11_10_120000_add_google_click_ids_to_tracking_data.php',
            '2026_11_11_190000_add_google_conversion_sent_to_tracking_data.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        config([
            'services.google.upload_url' => 'https://script.google.com/macros/s/xyz/exec',
            'services.google.upload_token' => 'clave-larga',
            'services.google.upload_action' => 'Reserva pagada (servidor)',
        ]);
        Cache::flush();
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
    }

    private function booking(string $code, array $tracking = ['gclid' => 'CLIC-ABC'], string $payment = 'PAGADA', string $origin = 'catalogo'): Booking
    {
        $customer = Customer::create(['name' => 'Cliente '.$code, 'phone_e164' => '+569'.random_int(10000000, 99999999)]);
        $booking = Booking::create([
            'code' => $code, 'customer_id' => $customer->id, 'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-11-12 18:00'), 'ends_at' => Carbon::parse('2026-11-12 20:00'),
            'duration_minutes' => 120, 'guests_count' => 2, 'booking_status' => 'CONFIRMADA', 'payment_status' => $payment,
            'price_original' => 36000, 'price_final' => 36000, 'origin' => $origin,
        ]);
        if ($tracking) {
            TrackingData::create(['booking_id' => $booking->id] + $tracking);
        }

        return $booking;
    }

    public function test_a_paid_google_ad_sale_is_sent_once_with_its_click_and_value(): void
    {
        $booking = $this->booking('HH-G1');
        Http::fake(['script.google.com/*' => Http::response(['ok' => true, 'recibidos' => ['HH-G1']])]);

        $this->assertSame(1, app(GoogleConversions::class)->sendPending());

        Http::assertSent(function ($request) {
            $row = $request['rows'][0];

            return $request['token'] === 'clave-larga'
                && $row['gclid'] === 'CLIC-ABC'
                && $row['order_id'] === 'HH-G1'
                && $row['value'] === 36000
                && $row['currency'] === 'CLP'
                && $row['conversion_name'] === 'Reserva pagada (servidor)'
                && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}-0[34]:00$/', $row['conversion_time']) === 1;
        });
        $this->assertNotNull($booking->trackingData->fresh()->google_conversion_sent_at);

        // Segunda pasada: ya no hay nada pendiente, no vuelve a llamar.
        Http::fake();
        $this->assertSame(0, app(GoogleConversions::class)->sendPending());
        Http::assertNothingSent();
    }

    public function test_only_paid_catalog_sales_with_a_google_click_are_sent(): void
    {
        $this->booking('HH-SIN-CLIC', ['utm_source' => 'google']);
        $this->booking('HH-SIN-PAGO', ['gclid' => 'X'], 'PAGO_PENDIENTE');
        $this->booking('HH-RECEPCION', ['gclid' => 'X'], 'PAGADA', 'recepcion');
        $this->booking('HH-IOS', ['gbraid' => 'IOS-1']);
        Http::fake(['script.google.com/*' => Http::response(['ok' => true, 'recibidos' => ['HH-IOS']])]);

        $this->assertSame(1, app(GoogleConversions::class)->sendPending());

        Http::assertSent(fn ($request) => count($request['rows']) === 1 && $request['rows'][0]['gbraid'] === 'IOS-1');
    }

    public function test_a_rejected_upload_leaves_the_sale_pending_and_reports_the_reason(): void
    {
        $booking = $this->booking('HH-G2');
        Http::fake(['script.google.com/*' => Http::response(['ok' => false, 'error' => 'no autorizado'])]);

        try {
            app(GoogleConversions::class)->sendPending();
            $this->fail('Debió lanzar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no autorizado', $e->getMessage());
        }

        $this->assertNull($booking->trackingData->fresh()->google_conversion_sent_at);
        $this->assertSame('no autorizado', Cache::get(GoogleConversions::LAST_RESULT_KEY)['error']);
    }

    public function test_a_sale_the_sheet_did_not_confirm_stays_pending(): void
    {
        $a = $this->booking('HH-OK');
        $b = $this->booking('HH-NO');
        Http::fake(['script.google.com/*' => Http::response(['ok' => true, 'recibidos' => ['HH-OK']])]);

        $this->assertSame(1, app(GoogleConversions::class)->sendPending());

        $this->assertNotNull($a->trackingData->fresh()->google_conversion_sent_at);
        $this->assertNull($b->trackingData->fresh()->google_conversion_sent_at);
    }

    public function test_it_does_nothing_without_the_url_and_key(): void
    {
        config(['services.google.upload_url' => null]);
        $this->booking('HH-G3');
        Http::fake();

        $this->assertFalse(GoogleConversions::enabled());
        $this->assertSame(0, Artisan::call('app:google-ventas'));
        Http::assertNothingSent();
    }
}
