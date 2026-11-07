<?php

namespace Tests\Feature;

use App\Models\AttentionRequest;
use App\Models\AuditLog;
use App\Models\ClosedConversation;
use App\Models\OperationalSetting;
use App\Models\TeamContact;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationInboxTest extends TestCase
{
    private const GHL = 'https://services.leadconnectorhq.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_11_05_130000_create_attention_requests_table.php',
            '2026_11_06_180000_create_team_contacts_table.php',
            '2026_09_16_040100_create_operational_settings_table.php',
            '2026_09_17_002913_add_floor_toggles_to_operational_settings.php',
            '2026_09_21_135116_split_floor_toggles_by_wing_in_operational_settings.php',
            '2026_11_06_190000_add_conversations_since_to_operational_settings.php',
            '2026_11_06_200000_create_closed_conversations_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        config(['services.ghl.private_token' => 'token-prueba', 'services.ghl.location_id' => 'loc1']);
    }

    private function thread(string $type = 'TYPE_WHATSAPP', string $lastInbound = '-2 hours'): void
    {
        Http::fake([
            self::GHL.'/conversations/abc/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'm2', 'direction' => 'outbound', 'messageType' => $type, 'body' => 'Respuesta', 'dateAdded' => now()->modify($lastInbound)->addMinute()->toIso8601String()],
                ['id' => 'm1', 'direction' => 'inbound', 'messageType' => $type, 'body' => 'Hola', 'dateAdded' => now()->modify($lastInbound)->toIso8601String()],
                ['id' => 'm0', 'direction' => 'outbound', 'messageType' => 'TYPE_ACTIVITY_CONTACT', 'body' => 'interno'],
            ]]]),
            self::GHL.'/conversations/messages' => Http::response(['messageId' => 'x']),
            self::GHL.'/conversations/abc' => Http::response(['id' => 'abc', 'contactId' => 'c1', 'type' => 'TYPE_PHONE']),
            self::GHL.'/contacts/c1/tags' => Http::response(['tags' => []]),
            self::GHL.'/contacts/c1' => Http::response(['contact' => ['firstName' => 'Ana', 'phone' => '', 'tags' => ['hh_no_bot']]]),
        ]);
    }

    public function test_hosts_and_administration_enter_but_marketing_does_not(): void
    {
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => []])]);

        foreach (['recepcion', 'administrador', 'soporte'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson(route('admin.conversations.list'))->assertOk();
        }

        $marketing = User::factory()->create(['role' => 'marketing']);
        $this->actingAs($marketing)->getJson(route('admin.conversations.list'))->assertForbidden();
        $this->actingAs($marketing)->postJson(route('admin.conversations.reply', 'abc'), ['message' => 'Hola'])->assertForbidden();
    }

    public function test_hosts_never_see_or_mark_team_conversations(): void
    {
        $this->thread();
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'abc', 'contactId' => 'c1', 'fullName' => 'Equipo', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
        ]])]);
        TeamContact::create(['ghl_contact_id' => 'c1']);
        $host = User::factory()->create(['role' => 'recepcion']);

        $this->actingAs($host)->getJson(route('admin.conversations.list', ['vista' => 'equipo']))->assertForbidden();
        $this->getJson(route('admin.conversations.list', ['q' => 'Equipo']))->assertJsonCount(0, 'items');
        $this->getJson(route('admin.conversations.show', 'abc'))->assertStatus(422);
        $this->postJson(route('admin.conversations.reply', 'abc'), ['message' => 'Hola'])->assertStatus(422);
        $this->postJson(route('admin.conversations.team', 'abc'), ['on' => false])->assertForbidden();
        $this->assertSame(1, TeamContact::count());
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_list_keeps_only_the_requested_view_and_channel_even_if_ghl_ignores_the_filter(): void
    {
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'a', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
            ['id' => 'b', 'fullName' => 'Beto', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'outbound', 'lastMessageDate' => now()->getTimestampMs()],
            ['id' => 'c', 'fullName' => 'Caro', 'lastMessageType' => 'TYPE_INSTAGRAM', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
        ]])]);

        $response = $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.list', ['vista' => 'por_atender', 'canal' => 'whatsapp']));

        $response->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'a');
    }

    public function test_thread_hides_internal_activity_orders_messages_and_shows_the_window(): void
    {
        $this->thread();

        $response = $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.show', 'abc'));

        $response->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.body', 'Hola')
            ->assertJsonPath('channel', 'whatsapp')
            ->assertJsonPath('can_reply', true)
            ->assertJsonPath('cata.active', false);
        $this->assertGreaterThan(1300, $response->json('window_left_minutes'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/c1') && $r->header('Version') === ['2021-07-28']);
    }

    public function test_opening_from_the_inbox_asks_messages_and_contact_without_the_extra_query(): void
    {
        Http::fake([
            self::GHL.'/conversations/abc/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'm1', 'contactId' => 'c1', 'direction' => 'inbound', 'messageType' => 'TYPE_WHATSAPP', 'body' => 'Hola', 'dateAdded' => now()->subHour()->toIso8601String()],
            ]]]),
            self::GHL.'/contacts/c1' => Http::response(['contact' => ['firstName' => 'Ana', 'phone' => '', 'tags' => []]]),
            self::GHL.'/contacts/otro' => Http::response(['contact' => ['firstName' => 'Otra persona', 'phone' => '', 'tags' => []]]),
        ]);
        $user = User::factory()->create(['role' => 'soporte']);

        $this->actingAs($user)->getJson(route('admin.conversations.show', ['conversation' => 'abc', 'contacto' => 'c1']))
            ->assertOk()->assertJsonPath('contact.name', 'Ana')->assertJsonPath('cata.active', true);
        Http::assertSentCount(2);

        // Si la pista no calza, manda el contacto real de la conversación.
        $this->getJson(route('admin.conversations.show', ['conversation' => 'abc', 'contacto' => 'otro']))
            ->assertOk()->assertJsonPath('contact.name', 'Ana');
    }

    public function test_attention_alert_leads_to_the_conversation_and_without_access_to_whatsapp(): void
    {
        $alert = AttentionRequest::create(['phone' => '+56911112222', 'name' => 'Ana']);
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'abc', 'contactId' => 'c1', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
        ]])]);
        $this->actingAs(User::factory()->create(['role' => 'marketing']))->getJson(route('attention.pending'))
            ->assertJsonPath('0.conversation', null);

        $link = $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->json('0.conversation');
        $this->assertStringEndsWith('/admin/conversaciones?aviso='.$alert->id, $link);
        $this->assertStringNotContainsString('56911112222', $link);

        $this->getJson(route('admin.conversations.list', ['aviso' => $alert->id]))->assertOk()->assertJsonPath('items.0.id', 'abc');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/conversations/search') && ($r['query'] ?? null) === '+56911112222');
    }

    private function waiting(array $conversations): void
    {
        Cache::flush();
        OperationalSetting::current()->update(['conversations_since' => now()->subDay()]);
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => array_map(fn (array $c) => $c + [
            'contactId' => 'c-'.$c['id'], 'fullName' => 'Cliente '.$c['id'], 'lastMessageType' => 'TYPE_WHATSAPP',
            'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->subMinutes(10)->getTimestampMs(),
        ], $conversations)])]);
    }

    public function test_client_waiting_for_an_answer_shows_on_every_screen_after_a_few_minutes(): void
    {
        $this->waiting([
            ['id' => 'espera'],
            ['id' => 'reciente', 'lastMessageDate' => now()->subMinute()->getTimestampMs()],
            ['id' => 'correo', 'lastMessageType' => 'TYPE_EMAIL'],
            ['id' => 'equipo', 'contactId' => 'c-equipo'],
            ['id' => 'alta'],
        ]);
        TeamContact::create(['ghl_contact_id' => 'c-equipo']);
        ClosedConversation::create(['ghl_conversation_id' => 'alta', 'closed_at' => now()]);

        $rows = $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->assertOk()->json();

        $this->assertCount(1, $rows, 'Recepción no ve al equipo.');
        $this->assertSame('Cliente espera espera respuesta', $rows[0]['title']);
        $this->assertStringEndsWith('/admin/conversaciones?abrir=espera', $rows[0]['conversation']);

        $rows = $this->actingAs(User::factory()->create(['role' => 'soporte']))->getJson(route('attention.pending'))->json();

        $this->assertCount(2, $rows, 'Administración también ve cuando escribe el equipo.');
        $this->assertStringStartsWith('Equipo · WhatsApp', $rows[1]['detail']);
        $this->assertStringEndsWith('/admin/conversaciones?abrir=equipo&equipo=1', $rows[1]['conversation']);
    }

    public function test_marketing_gets_no_waiting_alert_and_handoffs_are_not_repeated(): void
    {
        $this->waiting([['id' => 'traspaso', 'phone' => '+56911112222'], ['id' => 'otra', 'phone' => '+56933334444']]);
        AttentionRequest::create(['phone' => '+56911112222', 'name' => 'Ana']);

        $this->actingAs(User::factory()->create(['role' => 'marketing']))->getJson(route('attention.pending'))->assertJsonCount(1);

        $rows = $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->json();
        $this->assertCount(2, $rows);
        $this->assertSame('Cliente otra espera respuesta', $rows[1]['title']);
    }

    public function test_many_waiting_clients_fit_in_a_summary_row(): void
    {
        $this->waiting(array_map(fn (int $n) => ['id' => 'c'.$n], range(1, 5)));

        $rows = $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->json();

        $this->assertCount(4, $rows);
        $this->assertSame('2 conversaciones más por atender', $rows[3]['title']);
    }

    public function test_ghl_failure_never_breaks_the_screens(): void
    {
        Cache::flush();
        Http::fake([self::GHL.'/conversations/search*' => Http::response([], 500)]);

        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->assertOk()->assertJsonCount(0);
    }

    public function test_replying_clears_the_alert_right_away(): void
    {
        Cache::flush();
        OperationalSetting::current()->update(['conversations_since' => now()->subDay()]);
        $waiting = [['id' => 'abc', 'contactId' => 'c1', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->subMinutes(10)->getTimestampMs()]];
        Http::fake([self::GHL.'/conversations/search*' => function () use (&$waiting) {
            return Http::response(['conversations' => $waiting]);
        }]);
        $this->thread();
        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->getJson(route('attention.pending'))->assertJsonCount(1);

        $waiting = [];
        $this->postJson(route('admin.conversations.reply', 'abc'), ['message' => 'Hola'])->assertOk();

        $this->getJson(route('attention.pending'))->assertJsonCount(0);
    }

    public function test_only_what_the_client_wrote_after_the_starting_point_is_pending(): void
    {
        OperationalSetting::current()->update(['conversations_since' => now()->subHour()]);
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'nuevo', 'fullName' => 'Nuevo', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs(), 'unreadCount' => 9],
            ['id' => 'viejo', 'fullName' => 'Viejo', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->subDay()->getTimestampMs(), 'unreadCount' => 9],
        ]])]);

        $user = User::factory()->create(['role' => 'soporte']);

        $this->actingAs($user)->getJson(route('admin.conversations.list', ['vista' => 'por_atender']))
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'nuevo');
        $this->getJson(route('admin.conversations.list'))
            ->assertJsonCount(2, 'items')->assertJsonPath('items.1.pending', false);
    }

    public function test_waiting_for_client_also_starts_from_the_starting_point(): void
    {
        OperationalSetting::current()->update(['conversations_since' => now()->subHour()]);
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'nuevo', 'fullName' => 'Nuevo', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'outbound', 'lastMessageDate' => now()->getTimestampMs()],
            ['id' => 'viejo', 'fullName' => 'Viejo', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'outbound', 'lastMessageDate' => now()->subDay()->getTimestampMs()],
        ]])]);

        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.list', ['vista' => 'esperando']))
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'nuevo');
        $this->getJson(route('admin.conversations.list'))->assertJsonCount(2, 'items');
    }

    public function test_waiting_for_client_lasts_twelve_hours_and_a_new_message_brings_it_back(): void
    {
        OperationalSetting::current()->update(['conversations_since' => now()->subDays(3)]);
        $ghl = fn (string $direction, string $ago) => ['contactId' => 'c1', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => $direction, 'lastMessageDate' => now()->modify($ago)->getTimestampMs()];
        Http::fake([self::GHL.'/conversations/search*' => function (Request $r) use ($ghl) {
            return Http::response(['conversations' => str_contains($r->url(), 'inbound')
                ? [['id' => 'volvio'] + $ghl('inbound', '-1 hour')]
                : [['id' => 'reciente'] + $ghl('outbound', '-11 hours'), ['id' => 'vencida'] + $ghl('outbound', '-13 hours')]]);
        }]);
        $this->actingAs(User::factory()->create(['role' => 'soporte']));

        $this->getJson(route('admin.conversations.list', ['vista' => 'esperando']))
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'reciente');
        $this->getJson(route('admin.conversations.list', ['vista' => 'por_atender']))
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'volvio');
    }

    public function test_closed_conversation_leaves_follow_up_until_a_new_message_arrives(): void
    {
        OperationalSetting::current()->update(['conversations_since' => now()->subDay()]);
        $this->thread();
        $lastMessageAt = now()->subHour()->getTimestampMs();
        $reply = fn (int $at) => Http::response(['conversations' => [
            ['id' => 'abc', 'contactId' => 'c1', 'fullName' => 'Ana', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'outbound', 'lastMessageDate' => $at],
        ]]);
        $answers = [$reply($lastMessageAt), $reply($lastMessageAt), $reply(now()->addHour()->getTimestampMs())];
        Http::fake([self::GHL.'/conversations/search*' => function () use (&$answers) { return array_shift($answers); }]);
        $user = User::factory()->create(['role' => 'soporte']);

        $this->actingAs($user)->postJson(route('admin.conversations.close', 'abc'), ['on' => true])->assertOk();
        $this->assertSame($user->id, ClosedConversation::sole()->closed_by);

        $this->getJson(route('admin.conversations.list', ['vista' => 'esperando']))->assertJsonCount(0, 'items');
        $this->getJson(route('admin.conversations.list'))->assertJsonPath('items.0.closed', true);
        $this->getJson(route('admin.conversations.list', ['vista' => 'esperando']))->assertJsonCount(1, 'items')->assertJsonPath('items.0.closed', false);
        $this->getJson(route('admin.conversations.show', 'abc'))->assertJsonPath('closed', true);

        $this->postJson(route('admin.conversations.close', 'abc'), ['on' => false])->assertOk();
        $this->assertSame(0, ClosedConversation::count());
    }

    public function test_team_contacts_leave_the_inbox_and_go_to_their_own_filter(): void
    {
        $this->thread();
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['conversations' => [
            ['id' => 'abc', 'contactId' => 'c1', 'fullName' => 'Equipo Uno', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
            ['id' => 'def', 'contactId' => 'c2', 'fullName' => 'Cliente', 'lastMessageType' => 'TYPE_WHATSAPP', 'lastMessageDirection' => 'inbound', 'lastMessageDate' => now()->getTimestampMs()],
        ]])]);
        $user = User::factory()->create(['role' => 'soporte']);

        $this->actingAs($user)->postJson(route('admin.conversations.team', 'abc'), ['on' => true])->assertOk();
        $this->assertSame('c1', TeamContact::sole()->ghl_contact_id);

        $this->getJson(route('admin.conversations.list'))->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'def');
        $this->getJson(route('admin.conversations.list', ['q' => 'Equipo']))->assertJsonCount(2, 'items')->assertJsonPath('items.0.team', true);
        $this->getJson(route('admin.conversations.list', ['vista' => 'equipo']))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'abc');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'contactId=c1'));

        $this->postJson(route('admin.conversations.team', 'abc'), ['on' => false])->assertOk();
        $this->assertSame(0, TeamContact::count());
    }

    public function test_contact_card_saves_name_and_email_but_never_the_phone(): void
    {
        $this->thread();
        Http::fake([self::GHL.'/contacts/c1' => function (Request $r) {
            return Http::response(['contact' => ['id' => 'c1', 'firstName' => 'Ana', 'phone' => '', 'tags' => []]]);
        }]);
        $user = User::factory()->create(['role' => 'soporte']);

        $this->actingAs($user)->putJson(route('admin.conversations.contact', 'abc'), ['first_name' => ''])->assertUnprocessable();

        $this->putJson(route('admin.conversations.contact', 'abc'), ['first_name' => 'Ana', 'last_name' => 'Soto', 'email' => 'ana@example.com', 'phone' => '+56900000000'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['lastName'] === 'Soto' && $r['email'] === 'ana@example.com' && ! isset($r['phone']));
        $this->assertSame($user->id, AuditLog::where('action', 'conversacion.ficha_editada')->sole()->user_id);
    }

    public function test_reply_goes_by_the_conversation_channel_and_records_who_answered(): void
    {
        $this->thread('TYPE_INSTAGRAM');
        $user = User::factory()->create(['role' => 'soporte']);

        $this->actingAs($user)->postJson(route('admin.conversations.reply', 'abc'), ['message' => 'Te ayudo', 'contactId' => 'otro', 'type' => 'SMS'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === self::GHL.'/conversations/messages'
            && $r['type'] === 'IG' && $r['contactId'] === 'c1' && $r['message'] === 'Te ayudo');
        $this->assertSame($user->id, AuditLog::where('action', 'conversacion.respondida')->sole()->user_id);
    }

    public function test_whatsapp_reply_is_blocked_after_24_hours(): void
    {
        $this->thread('TYPE_WHATSAPP', '-25 hours');

        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->postJson(route('admin.conversations.reply', 'abc'), ['message' => 'Hola'])
            ->assertStatus(422);

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
        $this->assertSame(0, AuditLog::count());
    }

    public function test_turning_cata_off_adds_the_no_bot_tag(): void
    {
        $this->thread();

        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->postJson(route('admin.conversations.cata', 'abc'), ['on' => false])->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts/c1/tags') && $r['tags'] === ['hh_no_bot']);
    }

    public function test_missing_ghl_permission_gives_a_clear_message(): void
    {
        Http::fake([self::GHL.'/conversations/search*' => Http::response(['message' => 'no autorizado'], 401)]);

        $this->actingAs(User::factory()->create(['role' => 'soporte']))
            ->getJson(route('admin.conversations.list'))
            ->assertStatus(502)
            ->assertJsonFragment(['error' => 'GHL rechazó el permiso. Revisa que la integración tenga acceso a conversaciones y mensajes.']);
    }
}
