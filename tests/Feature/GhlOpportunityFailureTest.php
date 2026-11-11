<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Integrations\GhlOpportunitySync;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Una reserva que GHL rechaza (teléfono con código de país inválido, por ejemplo) no puede
 * frenar el embudo: se anota con su motivo, se salta y las demás siguen subiendo.
 */
class GhlOpportunityFailureTest extends TestCase
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
            '2026_08_18_120020_create_tracking_data_table.php',
            '2026_11_03_000000_add_marketing_tracking.php',
            '2026_11_11_140000_add_ghl_sync_failure_to_bookings.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        config([
            'services.ghl.private_token' => 'pit', 'services.ghl.location_id' => 'loc',
            'services.ghl.opportunities_since' => '2026-11-01', 'services.ghl.pipeline_name' => 'Reservas HH',
        ]);
        Cache::flush();

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
    }

    /** Respuestas simuladas de GHL: el embudo, la creación de la oportunidad y, si se pide, el contacto. */
    private function fakeGhl(?array $contacts = null, string $opportunityId = 'opp-ok', int $opportunityStatus = 200): void
    {
        $stages = collect(GhlOpportunitySync::STAGES)->map(fn ($s, $k) => ['id' => 'st-'.$k, 'name' => $s['name']])->values()->all();
        $fake = [
            'services.leadconnectorhq.com/opportunities/pipelines*' => Http::response(['pipelines' => [['id' => 'p-hh', 'name' => 'Reservas HH', 'stages' => $stages]]]),
            'services.leadconnectorhq.com/opportunities/' => $opportunityStatus === 200
                ? Http::response(['opportunity' => ['id' => $opportunityId]])
                : Http::response('caído', $opportunityStatus),
        ];
        if ($contacts !== null) {
            $fake = ['services.leadconnectorhq.com/contacts/*' => Http::response($contacts, 400)] + $fake;
        }
        Http::fake($fake);
    }

    private function booking(string $phone, ?string $contactId): Booking
    {
        $customer = Customer::create(['name' => 'Cliente '.$phone, 'phone_e164' => $phone, 'ghl_contact_id' => $contactId]);

        return Booking::create([
            'code' => 'HH-'.substr(md5($phone), 0, 8), 'customer_id' => $customer->id, 'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-11-12 18:00'), 'ends_at' => Carbon::parse('2026-11-12 20:00'),
            'duration_minutes' => 120, 'guests_count' => 2, 'booking_status' => 'CONFIRMADA', 'payment_status' => 'PAGADA',
            'price_original' => 26000, 'price_final' => 26000, 'origin' => 'catalogo',
        ]);
    }

    public function test_a_booking_ghl_rejects_does_not_block_the_ones_behind_it(): void
    {
        // La primera (id más bajo) no tiene contacto y su teléfono es inválido: GHL responde 400.
        $this->fakeGhl(['statusCode' => 400, 'message' => 'Invalid country calling code']);
        $bad = $this->booking('+5691917003', null);
        $good = $this->booking('+56912345678', 'c-good');

        $this->assertSame(1, app(GhlOpportunitySync::class)->syncPending());

        $this->assertSame('opp-ok', $good->fresh()->ghl_opportunity_id);
        $bad = $bad->fresh();
        $this->assertNull($bad->ghl_opportunity_id);
        $this->assertSame(1, $bad->ghl_sync_attempts);
        $this->assertStringContainsString('Invalid country calling code', $bad->ghl_sync_error);
        $this->assertNotNull($bad->ghl_sync_failed_at);
        // No quedó pausado: el rechazo era de esa reserva, no de GHL.
        $this->assertNull(Cache::get(GhlOpportunitySync::ERROR_KEY));
    }

    public function test_the_rejected_booking_waits_an_hour_then_is_retried_and_clears_when_fixed(): void
    {
        $this->fakeGhl(['statusCode' => 400, 'message' => 'Invalid country calling code'], 'opp-fixed');
        $bad = $this->booking('+5691917003', null);

        app(GhlOpportunitySync::class)->syncPending();
        $this->assertSame(1, $bad->fresh()->ghl_sync_attempts);

        // Sin pasar la hora, no vuelve a molestar a GHL.
        $this->assertSame(0, app(GhlOpportunitySync::class)->syncPending());
        $this->assertSame(1, $bad->fresh()->ghl_sync_attempts);

        // Recepción corrige el teléfono; pasada la hora entra sola.
        $this->travel(GhlOpportunitySync::RETRY_FAILED_AFTER_MINUTES + 1)->minutes();
        $bad->customer->update(['phone_e164' => '+56912345678', 'ghl_contact_id' => 'c-fixed']);
        Cache::flush();

        $this->assertSame(1, app(GhlOpportunitySync::class)->syncPending());
        $fresh = $bad->fresh();
        $this->assertSame('opp-fixed', $fresh->ghl_opportunity_id);
        $this->assertNull($fresh->ghl_sync_failed_at);
        $this->assertSame(0, $fresh->ghl_sync_attempts);
        $this->assertNull($fresh->ghl_sync_error);
    }

    public function test_a_ghl_outage_still_pauses_everything_instead_of_marking_bookings(): void
    {
        $this->fakeGhl(null, 'x', 503);
        $booking = $this->booking('+56912345678', 'c-1');

        $this->assertSame(0, app(GhlOpportunitySync::class)->syncPending());

        $this->assertNotNull(Cache::get(GhlOpportunitySync::ERROR_KEY));
        $this->assertSame(0, $booking->fresh()->ghl_sync_attempts);
        $this->assertNull($booking->fresh()->ghl_sync_failed_at);
    }
}
