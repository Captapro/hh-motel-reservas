<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_a_host_changes_their_own_password_with_the_current_one(): void
    {
        $host = User::factory()->create(['role' => 'recepcion', 'password' => 'clave-vieja-1']);
        $this->actingAs($host);

        $this->get(route('account.password'))->assertOk()->assertSee('Cambiar mi contraseña');

        $this->from(route('account.password'))->put(route('account.password.update'), [
            'current_password' => 'equivocada', 'password' => 'clave-nueva-2', 'password_confirmation' => 'clave-nueva-2',
        ])->assertSessionHasErrors('current_password');
        $this->from(route('account.password'))->put(route('account.password.update'), [
            'current_password' => 'clave-vieja-1', 'password' => 'clave-nueva-2', 'password_confirmation' => 'otra-cosa-3',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('clave-vieja-1', $host->fresh()->password));

        $this->put(route('account.password.update'), [
            'current_password' => 'clave-vieja-1', 'password' => 'clave-nueva-2', 'password_confirmation' => 'clave-nueva-2',
        ])->assertRedirect(route('account.password'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('clave-nueva-2', $host->fresh()->password));
    }
}
