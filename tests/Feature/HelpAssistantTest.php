<?php

namespace Tests\Feature;

use App\Models\HelpQuestion;
use App\Models\User;
use App\Services\Analyst\ClaudeTransport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asistente de ayuda: cualquier usuario del equipo pregunta, responde con el
 * manual y las reglas de la casa, y el administrador ve y borra el historial.
 */
class HelpAssistantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_10_27_120000_add_ai_access_to_users_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_10_23_200000_create_access_permissions_table.php',
            '2026_10_27_110000_create_help_assistant_tables.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    private function fakeClaude(): object
    {
        $fake = new class extends ClaudeTransport
        {
            public string $system = '';

            public array $messages = [];

            public array $options = [];

            public function send(string $system, array $messages, array $tools, array $options = []): object
            {
                [$this->system, $this->messages, $this->options] = [$system, $messages, $options];

                return (object) ['stopReason' => 'end_turn', 'usage' => (object) ['inputTokens' => 10, 'outputTokens' => 5], 'content' => [
                    (object) ['type' => 'text', 'text' => "1. Abre la reserva.\n2. Aprieta **Vender 1 hora adicional**."],
                ]];
            }
        };
        $this->app->instance(ClaudeTransport::class, $fake);

        return $fake;
    }

    public function test_host_asks_and_gets_steps_built_from_manual_and_house_rules(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $fake = $this->fakeClaude();
        $admin = User::factory()->create(['role' => 'administrador']);
        $host = User::factory()->create(['role' => 'recepcion', 'can_use_help' => true]);

        $this->actingAs($admin)->put(route('admin.help.rules'), ['house_rules' => 'Las devoluciones las autoriza solo el administrador.'])->assertRedirect();

        $this->actingAs($host)->postJson(route('help.ask'), [
            'question' => '¿Cómo cobro una hora extra?',
            'page' => 'reservations.show — Reserva',
            'history' => [['q' => '¿Cómo hago check-in?', 'a' => 'Escanea el QR.']],
        ])->assertOk()->assertJsonPath('status', 'listo')
            ->assertJsonPath('answer_html', fn ($html) => str_contains($html, '<strong>Vender 1 hora adicional</strong>'));

        $this->assertStringContainsString('Las devoluciones las autoriza solo el administrador.', $fake->system);
        $this->assertStringContainsString('<manual>', $fake->system);
        $this->assertSame('medium', $fake->options['effort']);
        $this->assertSame('¿Cómo hago check-in?', $fake->messages[0]['content']);
        $this->assertStringContainsString('reservations.show', $fake->messages[2]['content']);

        $record = HelpQuestion::first();
        $this->assertSame('recepcion', $record->role);
        $this->assertSame($host->id, $record->user_id);
    }

    public function test_only_admin_sees_the_history_and_can_delete_it(): void
    {
        $host = User::factory()->create(['role' => 'recepcion']);
        $admin = User::factory()->create(['role' => 'administrador']);
        HelpQuestion::create(['user_id' => $host->id, 'role' => 'recepcion', 'question' => '¿Cómo paso a aseo?', 'answer' => 'Así.']);

        $this->actingAs($host)->get(route('admin.help.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.help.index'))->assertOk()->assertSee('¿Cómo paso a aseo?');

        $this->actingAs($admin)->delete(route('admin.help.destroy_all'))->assertRedirect();
        $this->assertSame(0, HelpQuestion::count());
    }

    public function test_only_authorized_people_see_the_help_button_and_can_ask(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->fakeClaude();
        $host = User::factory()->create(['role' => 'recepcion']);

        $this->actingAs($host)->postJson(route('help.ask'), ['question' => 'Hola'])->assertForbidden();
        $this->assertFalse($host->canUseAi('ayuda'));
        $host->update(['can_use_help' => true]);
        $this->actingAs($host->fresh())->postJson(route('help.ask'), ['question' => 'Hola'])->assertOk();
    }

    public function test_without_api_key_the_question_is_rejected(): void
    {
        config(['services.anthropic.key' => '']);
        $host = User::factory()->create(['role' => 'recepcion', 'can_use_help' => true]);

        $this->actingAs($host)->postJson(route('help.ask'), ['question' => 'Hola'])->assertStatus(422);
        $this->assertSame(0, HelpQuestion::count());
    }
}
