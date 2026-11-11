<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAdOrigin;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Marketing\MetaConversions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La venta de un cliente que llegó por un anuncio de WhatsApp se le avisa a Meta con el id de su
 * clic (ctwa_clid) y la cuenta de WhatsApp Business, una sola vez y sin datos personales.
 */
class MetaMessagingPurchaseTest extends TestCase
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
            '2026_11_11_130000_create_booking_ad_origins_table.php',
            '2026_11_11_160000_add_ctwa_to_booking_ad_origins.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        config([
            'services.meta.pixel_id' => '1712431846730178', 'services.meta.capi_token' => 'token-capi',
            'services.meta.waba_id' => '987654321', 'services.meta.test_event_code' => null,
        ]);
        Cache::flush();
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
    }

    private function origin(string $code, ?string $ctwa = 'CLIC-123', string $payment = 'PAGADA'): BookingAdOrigin
    {
        $customer = Customer::create(['name' => 'Cliente '.$code, 'phone_e164' => '+569'.random_int(10000000, 99999999)]);
        $booking = Booking::create([
            'code' => $code, 'customer_id' => $customer->id, 'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-11-12 18:00'), 'ends_at' => Carbon::parse('2026-11-12 20:00'),
            'duration_minutes' => 120, 'guests_count' => 2, 'booking_status' => 'CONFIRMADA', 'payment_status' => $payment,
            'price_original' => 26000, 'price_final' => 26000, 'origin' => 'recepcion',
        ]);

        return BookingAdOrigin::create(['booking_id' => $booking->id, 'meta_ad_id' => '120253089036650584', 'wa_source' => 'Paid Social', 'contact_found' => true, 'attempts' => 1, 'checked_at' => now(), 'ctwa_clid' => $ctwa]);
    }

    public function test_a_paid_whatsapp_ad_sale_goes_to_meta_with_its_click_id_and_no_personal_data(): void
    {
        $origin = $this->origin('HH-WA-1');
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        $this->assertSame(1, app(MetaConversions::class)->sendPendingMessagingPurchases());

        Http::assertSent(function ($r) {
            $e = $r['data'][0];

            return str_ends_with($r->url(), '/1712431846730178/events')
                && $e['event_name'] === 'Purchase' && $e['action_source'] === 'business_messaging' && $e['messaging_channel'] === 'whatsapp'
                && $e['user_data'] === ['whatsapp_business_account_id' => '987654321', 'ctwa_clid' => 'CLIC-123']
                && $e['custom_data']['value'] === 26000 && $e['custom_data']['currency'] === 'CLP'
                && $e['event_id'] === 'purchase-wa-HH-WA-1' && ! isset($e['user_data']['ph']) && ! isset($e['user_data']['em']);
        });
        $this->assertNotNull($origin->fresh()->wa_purchase_sent_at);

        // Una sola vez por reserva.
        Http::fake();
        $this->assertSame(0, app(MetaConversions::class)->sendPendingMessagingPurchases());
        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_without_the_whatsapp_account_or_without_a_paid_sale_or_a_click_id(): void
    {
        $this->origin('HH-WA-2', null);              // sin id de clic
        $this->origin('HH-WA-3', 'CLIC-9', 'NO_PAGADA'); // sin pagar
        Http::fake();

        $this->assertSame(0, app(MetaConversions::class)->sendPendingMessagingPurchases());

        config(['services.meta.waba_id' => null]);   // sin cuenta de WhatsApp Business
        $this->origin('HH-WA-4');
        $this->assertFalse(MetaConversions::messagingEnabled());
        $this->assertSame(0, app(MetaConversions::class)->sendPendingMessagingPurchases());
        Http::assertNothingSent();
    }

    public function test_a_meta_rejection_is_remembered_and_retried_later_not_hammered(): void
    {
        $origin = $this->origin('HH-WA-5');
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Permission denied']], 403)]);

        $this->assertSame(0, app(MetaConversions::class)->sendPendingMessagingPurchases());
        $this->assertNull($origin->fresh()->wa_purchase_sent_at);
        $this->assertSame('Permission denied', Cache::get(MetaConversions::LAST_WA_RESULT_KEY)['error']);

        // Dentro de la espera no vuelve a molestar a Meta.
        Http::fake();
        $this->assertSame(0, app(MetaConversions::class)->sendPendingMessagingPurchases());
        Http::assertNothingSent();
    }
}
