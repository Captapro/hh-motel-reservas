<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analyst\ClaudeTransport;
use App\Services\Customers\IdScanSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El celular como cámara del computador: QR → sesión de un solo uso → carnets que el computador recoge.
 */
class IdScanSessionTest extends TestCase
{
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
        Cache::flush();
    }

    private function fakeModel(array $answer): void
    {
        $this->app->instance(ClaudeTransport::class, new class($answer) extends ClaudeTransport
        {
            public function __construct(private array $answer) {}

            public function configured(): bool
            {
                return true;
            }

            public function send(string $system, array $messages, array $tools, array $options = []): object
            {
                return (object) ['content' => [(object) ['type' => 'text', 'text' => json_encode($this->answer)]]];
            }
        });
    }

    private function card(): array
    {
        return ['legible' => true, 'tipo' => 'cedula_chilena', 'rut' => '11.111.111-1', 'nombres' => 'MARÍA JOSÉ', 'apellidos' => 'PÉREZ GONZÁLEZ', 'nacionalidad' => 'CHILENA'];
    }

    public function test_the_computer_gets_a_qr_session_and_the_phone_page_opens_without_login(): void
    {
        $user = User::factory()->create(['role' => 'recepcion']);
        $session = $this->actingAs($user)->postJson(route('id_scan.create'))->assertOk()->json();
        $this->assertSame(40, strlen($session['token']));

        $this->get($session['qr'])->assertOk()->assertHeader('Content-Type', 'image/png');
        auth()->logout();

        // El celular entra solo con el código, sin iniciar sesión.
        $this->get(route('id_scan.phone', $session['token']))->assertOk()->assertSee('Escanear carnet')->assertDontSee('ya venció');
        // Un código cualquiera no sirve.
        $this->get(route('id_scan.phone', str_repeat('a', 40)))->assertOk()->assertSee('ya venció');
        $this->get('/escaner/corto')->assertNotFound();
    }

    public function test_cards_read_on_the_phone_reach_the_computer_once_and_are_then_deleted(): void
    {
        $user = User::factory()->create(['role' => 'recepcion']);
        $token = $this->actingAs($user)->postJson(route('id_scan.create'))->json('token');
        $this->fakeModel($this->card());
        auth()->logout();

        $this->postJson(route('id_scan.upload', $token), ['photo' => UploadedFile::fake()->image('c.jpg', 800, 500)])
            ->assertOk()->assertJson(['ok' => true, 'n' => 1, 'name' => 'María José Pérez González']);

        $this->actingAs($user);
        $first = $this->getJson(route('id_scan.poll', $token).'?after=0')->assertOk()->json();
        $this->assertCount(1, $first['reads']);
        $this->assertSame('11111111-1', $first['reads'][0]['data']['rut']);
        $this->assertSame(1, $first['last']);

        // Ya recogida (after=1): no vuelve a llegar y se borra del servidor.
        $second = $this->getJson(route('id_scan.poll', $token).'?after=1')->assertOk()->json();
        $this->assertSame([], $second['reads']);
        $this->assertSame([], Cache::get('idscan:'.$token)['reads']);
    }

    public function test_only_the_owner_can_read_and_the_session_closes_and_expires(): void
    {
        $owner = User::factory()->create(['role' => 'recepcion']);
        $other = User::factory()->create(['role' => 'recepcion']);
        $token = $this->actingAs($owner)->postJson(route('id_scan.create'))->json('token');

        // Otra persona con sesión no puede ver las lecturas ni el QR.
        $this->actingAs($other)->getJson(route('id_scan.poll', $token))->assertStatus(410);
        $this->get(route('id_scan.qr', $token))->assertNotFound();

        // Al cerrar (o vencer), el celular recibe un aviso y no se guarda nada.
        $this->actingAs($owner)->deleteJson(route('id_scan.close', $token))->assertOk();
        $this->fakeModel($this->card());
        auth()->logout();
        $this->postJson(route('id_scan.upload', $token), ['photo' => UploadedFile::fake()->image('c.jpg')])->assertStatus(410);
        $this->assertFalse(app(IdScanSession::class)->exists($token));
    }

    public function test_unreadable_photos_are_not_stored_and_there_is_a_limit_per_session(): void
    {
        $user = User::factory()->create(['role' => 'recepcion']);
        $token = $this->actingAs($user)->postJson(route('id_scan.create'))->json('token');
        auth()->logout();

        $this->fakeModel(['legible' => false]);
        $this->postJson(route('id_scan.upload', $token), ['photo' => UploadedFile::fake()->image('c.jpg')])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertSame([], Cache::get('idscan:'.$token)['reads']);

        // Tope de lecturas por sesión: corta abusos y gasto de IA.
        $this->fakeModel($this->card());
        $sessions = app(IdScanSession::class);
        for ($i = 0; $i < IdScanSession::MAX_READS; $i++) {
            $this->assertNotNull($sessions->addRead($token, ['rut' => '11111111-1']));
        }
        $this->postJson(route('id_scan.upload', $token), ['photo' => UploadedFile::fake()->image('c.jpg')])->assertStatus(410);
    }

    public function test_creating_a_session_requires_login(): void
    {
        $this->postJson(route('id_scan.create'))->assertUnauthorized();
        $this->getJson(route('id_scan.poll', str_repeat('a', 40)))->assertUnauthorized();
    }
}
