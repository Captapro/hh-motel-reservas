<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccessSections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessProfilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        AccessSections::forget();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_10_23_200000_create_access_permissions_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Route::middleware(['web', 'auth', 'section'])->get('/_prueba/ventas', fn () => 'ok')->name('sales.prueba');
        Route::middleware(['web', 'auth', 'section'])->get('/_prueba/libre', fn () => 'ok')->name('reservations.prueba');
    }

    protected function tearDown(): void
    {
        AccessSections::forget();
        parent::tearDown();
    }

    public function test_default_profiles_match_the_agreed_starting_point(): void
    {
        $host = User::factory()->create(['role' => 'recepcion']);
        $marketing = User::factory()->create(['role' => 'marketing']);
        $admin = User::factory()->create(['role' => 'administrador']);

        foreach (['tablero', 'calendario', 'clientes', 'caja', 'operaciones'] as $ok) {
            $this->assertTrue($host->canAccess($ok), "anfitrión debería ver $ok");
        }
        foreach (['ventas', 'analytics', 'opiniones', 'productos'] as $no) {
            $this->assertFalse($host->canAccess($no), "anfitrión no debería ver $no");
        }
        $this->assertTrue($marketing->canAccess('analytics'));
        $this->assertTrue($marketing->canAccess('opiniones'));
        $this->assertFalse($marketing->canAccess('caja'));
        $this->assertSame('admin.customers.index', $marketing->homeRoute());
        $this->assertSame('rooms.board', $host->homeRoute());
        foreach (array_keys(AccessSections::SECTIONS) as $section) {
            $this->assertTrue($admin->canAccess($section));
        }
    }

    public function test_a_section_the_profile_cannot_see_is_blocked_and_admin_can_change_it(): void
    {
        $host = User::factory()->create(['role' => 'recepcion']);
        $admin = User::factory()->create(['role' => 'administrador']);

        $this->actingAs($host)->get('/_prueba/ventas')->assertForbidden();
        $this->actingAs($host)->get('/_prueba/libre')->assertOk();

        $payload = ['access' => ['recepcion' => ['ventas' => 1, 'tablero' => 1], 'marketing' => []]];
        $this->actingAs($admin)->put(route('admin.access.update'), $payload)->assertRedirect(route('admin.access.index'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'accesos.editar']);

        $this->actingAs($host)->get('/_prueba/ventas')->assertOk();
        $this->assertFalse($host->fresh()->canAccess('caja'));
    }

    public function test_only_administrators_can_edit_access(): void
    {
        $host = User::factory()->create(['role' => 'recepcion']);

        $this->actingAs($host)->put(route('admin.access.update'), ['access' => ['recepcion' => ['ventas' => 1]]])->assertForbidden();
        $this->assertFalse(AccessSections::allows('recepcion', 'ventas'));
    }
}
