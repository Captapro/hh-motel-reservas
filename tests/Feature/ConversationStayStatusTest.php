<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\OperationalSetting;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationStayStatusTest extends TestCase
{
    private const GHL = 'https://services.leadconnectorhq.com';

    private Room $room;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_09_22_012032_add_room_check_to_bookings_table.php',
            '2026_11_03_100000_add_room_closing_log_and_booked_ends.php',
            '2026_11_05_130000_create_attention_requests_table.php',
            '2026_11_06_180000_create_team_contacts_table.php',
            '2026_09_16_040100_create_operational_settings_table.php',
            '2026_09_17_002913_add_floor_toggles_to_operational_settings.php',
            '2026_09_21_135116_split_floor_toggles_by_wing_in_operational_settings.php',
            '2026_11_06_190000_add_conversations_since_to_operational_settings.php',
            '2026_11_06_200000_create_closed_conversations_table.php',
            '2026_09_22_001051_add_payment_instructions_sent_at_to_bookings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        config(['services.ghl.private_token' => 'token-prueba', 'services.ghl.location_id' => 'loc1']);
        $category = RoomCategory::create(['name' => 'PLUS', 'is_active' => true]);
        $this->room = Room::create(['name' => 'PLUS 101', 'room_category_id' => $category->id, 'operational_status' => 'activa']);
        $this->customer = Customer::create(['name' => 'Ana Pérez', 'phone_e164' => '+56911111111']);
        Http::fake([
            self::GHL.'/conversations/abc/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'm1', 'direction' => 'inbound', 'messageType' => 'TYPE_WHATSAPP', 'body' => 'Hola', 'contactId' => 'c1', 'dateAdded' => now()->toIso8601String()],
            ]]]),
            self::GHL.'/conversations/abc' => Http::response(['id' => 'abc', 'contactId' => 'c1', 'type' => 'TYPE_PHONE']),
            self::GHL.'/contacts/c1' => Http::response(['contact' => ['firstName' => 'Ana', 'phone' => '+56911111111', 'tags' => []]]),
        ]);
    }

    private function booking(string $code, string $status, string $start, array $overrides = []): Booking
    {
        $startsAt = Carbon::parse($start);

        return Booking::create(array_merge([
            'code' => $code, 'pass_token' => Str::random(40),
            'customer_id' => $this->customer->id, 'room_id' => $this->room->id, 'origin' => 'catalogo',
            'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHours(3),
            'duration_minutes' => 180, 'guests_count' => 2,
            'booking_status' => $status, 'payment_status' => 'PAGADA',
            'price_original' => 30000, 'price_final' => 30000,
        ], $overrides));
    }

    private function card(): array
    {
        return $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.show', 'abc'))
            ->assertOk()
            ->json('customer');
    }

    public function test_confirmed_booking_wins_over_an_expired_twin_at_the_same_time(): void
    {
        $this->booking('HH-CONF', 'CONFIRMADA', '+1 day');
        $this->booking('HH-EXP', 'EXPIRADA', '+1 day');

        $card = $this->card();

        $this->assertSame('HH-CONF', $card['current']['code']);
        $this->assertSame('Próximo a llegar', $card['current']['state']);
        $this->assertSame(1, $card['bookings']);
        $this->assertEqualsCanonicalizing(['HH-CONF', 'HH-EXP'], array_column($card['recent'], 'code'));
    }

    public function test_person_in_the_room_shows_first_even_with_a_later_booking(): void
    {
        $this->booking('HH-DENTRO', 'CHECK_IN', '-1 hour', ['checked_in_at' => now()->subHour()]);
        $this->booking('HH-LUEGO', 'CONFIRMADA', '+3 days');

        $card = $this->card();

        $this->assertSame('HH-DENTRO', $card['current']['code']);
        $this->assertSame('En la habitación', $card['current']['state']);
    }

    public function test_nearest_upcoming_booking_is_the_current_one(): void
    {
        $this->booking('HH-LEJOS', 'CONFIRMADA', '+9 days');
        $this->booking('HH-CERCA', 'CONFIRMADA', '+2 days');
        $this->booking('HH-ANTES', 'FINALIZADA', '-9 days', ['checked_out_at' => now()->subDays(9)]);

        $this->assertSame('HH-CERCA', $this->card()['current']['code']);
    }

    public function test_finished_stay_says_the_person_already_left(): void
    {
        $this->booking('HH-FIN', 'FINALIZADA', '-2 days', ['checked_out_at' => now()->subDays(2)]);

        $this->assertSame('Ya salió', $this->card()['current']['state']);
    }

    private function pendingWith(array $conversations): array
    {
        Cache::flush();
        OperationalSetting::current()->update(['conversations_since' => now()->subDay()]);
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => array_map(fn (array $c) => $c + [
            'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound',
        ], $conversations)])]);

        return $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->assertOk()->json();
    }

    public function test_guest_in_the_room_alerts_first_and_without_waiting(): void
    {
        $this->booking('HH-DENTRO', 'CHECK_IN', '-1 hour', ['checked_in_at' => now()->subHour()]);

        $rows = $this->pendingWith([
            ['id' => 'otro', 'contactId' => 'c2', 'phone' => '+56922222222', 'tags' => ['hh_no_bot'], 'lastMessageDate' => now()->subMinutes(10)->getTimestampMs()],
            ['id' => 'dentro', 'contactId' => 'c1', 'phone' => '+56911111111', 'lastMessageDate' => now()->getTimestampMs()],
        ]);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['urgent']);
        $this->assertSame('Ana escribe desde la habitación PLUS 101', $rows[0]['title']);
        $this->assertFalse($rows[1]['urgent']);
    }

    public function test_instagram_guest_is_found_by_ghl_contact_when_there_is_no_phone(): void
    {
        $this->customer->update(['ghl_contact_id' => 'c1']);
        $this->booking('HH-DENTRO', 'CHECK_IN', '-1 hour', ['checked_in_at' => now()->subHour()]);

        $rows = $this->pendingWith([['id' => 'dentro', 'contactId' => 'c1', 'lastMessageType' => 'TYPE_INSTAGRAM', 'lastMessageDate' => now()->getTimestampMs()]]);

        $this->assertTrue($rows[0]['urgent']);
    }

    public function test_after_leaving_the_room_there_is_no_priority(): void
    {
        $this->booking('HH-FIN', 'FINALIZADA', '-5 hours', ['checked_out_at' => now()->subHour()]);

        $this->assertSame([], $this->pendingWith([['id' => 'fuera', 'contactId' => 'c1', 'phone' => '+56911111111', 'lastMessageDate' => now()->getTimestampMs()]]));
    }

    public function test_booking_opens_the_conversation_of_its_customer(): void
    {
        $this->customer->update(['ghl_contact_id' => 'c1']);
        $this->booking('HH-CHAT', 'CONFIRMADA', '+1 day');
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'abc', 'contactId' => 'c1', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDate' => now()->getTimestampMs()],
        ]])]);

        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.list', ['reserva' => 'HH-CHAT']))
            ->assertOk()
            ->assertJsonPath('items.0.id', 'abc');
    }

    public function test_booking_without_ghl_contact_finds_the_conversation_by_phone(): void
    {
        $this->booking('HH-FONO', 'CONFIRMADA', '+1 day');
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'abc', 'contactId' => 'c1', 'phone' => '+56911111111', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDate' => now()->getTimestampMs()],
        ]])]);

        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.list', ['reserva' => 'HH-FONO']))
            ->assertOk()
            ->assertJsonPath('items.0.id', 'abc');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/conversations/search') && ($request['query'] ?? null) === '+56911111111');
    }

    public function test_unknown_booking_opens_nothing(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.list', ['reserva' => 'HH-NOEXISTE']))
            ->assertOk()
            ->assertJsonPath('items', []);
    }

    public function test_a_booking_message_goes_straight_to_the_clients_whatsapp_conversation(): void
    {
        $booking = $this->booking('HH-PAGO', 'PENDIENTE_PAGO', '+1 day', ['payment_status' => 'NO_PAGADA']);
        Http::fake([
            self::GHL.'/conversations/search*' => Http::response(['conversations' => [
                ['id' => 'ig1', 'contactId' => 'c1', 'phone' => '+56911111111', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_INSTAGRAM', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
                ['id' => 'abc', 'contactId' => 'c1', 'phone' => '+56911111111', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->subMinute()->getTimestampMs()],
            ]]),
            self::GHL.'/conversations/messages' => Http::response(['messageId' => 'x']),
        ]);
        $user = User::factory()->create(['role' => 'recepcion']);

        $this->actingAs($user)->postJson(route('admin.conversations.booking_send', 'HH-PAGO'), ['message' => 'Tu reserva HH-PAGO', 'kind' => 'pago'])
            ->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/conversations/messages') && $r['type'] === 'WhatsApp' && $r['message'] === 'Tu reserva HH-PAGO' && $r['contactId'] === 'c1');
        $this->assertNotNull($booking->fresh()->payment_instructions_sent_at);

        $this->actingAs(User::factory()->create(['role' => 'marketing']))
            ->postJson(route('admin.conversations.booking_send', 'HH-PAGO'), ['message' => 'x', 'kind' => 'pago'])->assertForbidden();
    }

    public function test_without_a_whatsapp_conversation_it_says_to_use_the_whatsapp_button(): void
    {
        $this->booking('HH-SIN', 'FINALIZADA', '-1 day');
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => []])]);

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))
            ->postJson(route('admin.conversations.booking_send', 'HH-SIN'), ['message' => 'Reseña', 'kind' => 'resena'])
            ->assertStatus(422)->assertJsonPath('error', 'Este cliente no tiene conversación de WhatsApp en el sistema. Usa "Contactar por WhatsApp".');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/conversations/messages'));
    }
}
