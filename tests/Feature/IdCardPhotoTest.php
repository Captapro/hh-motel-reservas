<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analyst\ClaudeTransport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Carnet por foto (piloto): la foto se lee con IA y completa RUN y nombres; todo es una propuesta
 * que recepción revisa. Sin IA de verdad: se simula la respuesta del modelo.
 */
class IdCardPhotoTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

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
        $this->actingAs(User::factory()->create(['role' => 'recepcion']));
    }

    private function fakeModel(?string $answer, bool $configured = true): void
    {
        $test = $this;
        $this->app->instance(ClaudeTransport::class, new class($test, $answer, $configured) extends ClaudeTransport
        {
            public function __construct(private IdCardPhotoTest $test, private ?string $answer, private bool $isConfigured) {}

            public function configured(): bool
            {
                return $this->isConfigured;
            }

            public function send(string $system, array $messages, array $tools, array $options = []): object
            {
                $this->test->record($messages, $options);

                return (object) ['content' => [(object) ['type' => 'text', 'text' => (string) $this->answer]]];
            }
        });
    }

    /** @param  array<int, array<string, mixed>>  $messages */
    public function record(array $messages, array $options): void
    {
        $this->sent[] = ['messages' => $messages, 'options' => $options];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('carnet.jpg', 800, 500);
    }

    public function test_a_readable_card_fills_rut_and_names_for_review(): void
    {
        $this->fakeModel('```json'."\n".json_encode([
            'legible' => true, 'tipo' => 'cedula_chilena', 'rut' => '11.111.111-1', 'nombres' => 'MARÍA JOSÉ', 'apellidos' => 'GONZÁLEZ  pérez',
            'nacionalidad' => 'CHILENA', 'fecha_nacimiento' => '1990-05-17', 'fecha_vencimiento' => '2030-01-31',
        ])."\n".'```');

        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])
            ->assertOk()
            ->assertJson([
                'ok' => true, 'rut' => '11111111-1', 'first_name' => 'María José', 'last_name' => 'González Pérez',
                'nationality' => 'Chilena', 'birth_date' => '1990-05-17', 'expires_on' => '2030-01-31', 'foreigner' => false,
            ]);

        // La foto viaja como imagen al modelo y en la bitácora queda solo que se usó, sin datos de la persona.
        $block = $this->sent[0]['messages'][0]['content'][0];
        $this->assertSame('image', $block['type']);
        $this->assertSame('base64', $block['source']['type']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'carnet.foto']);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => '%11111111%']);
    }

    public function test_a_foreign_card_is_marked_as_foreigner(): void
    {
        $this->fakeModel(json_encode(['legible' => true, 'tipo' => 'carnet_extranjero', 'rut' => '25320187-8', 'nombres' => 'Gerald', 'apellidos' => 'Chevry', 'nacionalidad' => 'Haitiana']));

        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])
            ->assertOk()->assertJson(['ok' => true, 'foreigner' => true, 'nationality' => 'Haitiana']);
    }

    public function test_an_invalid_rut_or_an_unreadable_photo_is_rejected_without_inventing_data(): void
    {
        // Dígito verificador malo: no se completa nada confiable.
        $this->fakeModel(json_encode(['legible' => true, 'rut' => '11.111.111-9', 'nombres' => 'Ana', 'apellidos' => 'Soto']));
        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])
            ->assertStatus(422)->assertJson(['ok' => false, 'rut' => null, 'first_name' => null]);

        // Foto borrosa o que no es un carnet.
        $this->fakeModel(json_encode(['legible' => false]));
        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])->assertStatus(422)->assertJson(['ok' => false]);

        // Respuesta que no es JSON.
        $this->fakeModel('No puedo leer esto.');
        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_without_configuration_or_a_valid_file_it_fails_cleanly(): void
    {
        $this->fakeModel(null, configured: false);
        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])
            ->assertStatus(422)->assertJson(['ok' => false, 'error' => 'La lectura por foto no está configurada.']);
        $this->assertSame([], $this->sent);

        $this->fakeModel('{}');
        $this->postJson(route('customers.id_photo'), [])->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->postJson(route('customers.id_photo'), ['photo' => UploadedFile::fake()->create('carnet.pdf', 100, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_only_signed_in_staff_can_use_it(): void
    {
        $this->fakeModel('{}');
        auth()->logout();
        $this->postJson(route('customers.id_photo'), ['photo' => $this->photo()])->assertUnauthorized();
    }
}
