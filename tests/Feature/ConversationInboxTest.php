<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Client\Request;
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

    public function test_only_soporte_enters(): void
    {
        Http::fake();

        foreach (['recepcion', 'administrador'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.conversations.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.conversations.reply', 'abc'), ['message' => 'Hola'])->assertForbidden();
        }

        Http::assertNothingSent();
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
