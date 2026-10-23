<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountStaffDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_09_16_010000_create_staff_and_shifts_tables.php',
            '2026_09_16_020000_add_legal_hours_to_staff.php',
            '2026_09_22_010022_add_pin_to_staff_table.php',
            '2026_09_22_010843_add_last_qr_scan_to_staff_table.php',
            '2026_09_22_012410_create_staff_shift_logs_table.php',
            '2026_09_25_231158_add_user_id_to_staff_shift_logs_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_accounts_can_be_deleted_but_never_your_own(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'is_active' => true]);
        $reception = User::factory()->create(['role' => 'recepcion']);
        $this->actingAs($admin);

        $this->delete(route('admin.accounts.destroy', $admin))->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $this->delete(route('admin.accounts.destroy', $reception))->assertRedirect(route('admin.staff.index'));
        $this->assertDatabaseMissing('users', ['id' => $reception->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cuenta.eliminar', 'entity_id' => $reception->id]);

        // Un segundo administrador sí se puede borrar mientras quede otro activo.
        $other = User::factory()->create(['role' => 'administrador', 'is_active' => true]);
        $this->delete(route('admin.accounts.destroy', $other))->assertRedirect(route('admin.staff.index'));
    }

    public function test_staff_member_can_be_deleted_with_their_shifts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $person = Staff::create(['name' => 'Prueba', 'role' => 'Mucama', 'is_active' => true]);

        $this->delete(route('admin.staff.destroy', $person))->assertRedirect(route('admin.staff.index'));
        $this->assertDatabaseMissing('staff', ['id' => $person->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'personal.eliminar', 'entity_id' => $person->id]);
    }

    public function test_only_administrators_can_delete(): void
    {
        $reception = User::factory()->create(['role' => 'recepcion']);
        $person = Staff::create(['name' => 'Protegida', 'role' => 'Mucama', 'is_active' => true]);
        $this->actingAs($reception);

        $this->delete(route('admin.staff.destroy', $person))->assertForbidden();
        $this->assertDatabaseHas('staff', ['id' => $person->id]);
    }
}
