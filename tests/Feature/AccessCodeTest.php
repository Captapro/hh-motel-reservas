<?php

namespace Tests\Feature;

use App\Models\AccessCode;
use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Support\AccessSections;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Claves de chapas digitales: cifradas en la base, ocultas en la página, cada
 * vista queda en el AuditLog y solo el administrador las agrega o cambia.
 */
class AccessCodeTest extends TestCase
{
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        AccessSections::forget();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_26_120001_add_aseo_override_to_rooms_table.php',
            '2026_08_26_140001_add_aseo_started_to_rooms_table.php',
            '2026_09_16_040000_add_wing_to_rooms.php',
            '2026_09_16_050000_add_videos_to_rooms.php',
            '2026_09_22_004138_add_aseo_report_to_rooms_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_10_23_200000_create_access_permissions_table.php',
            '2026_10_26_230000_create_access_codes_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $category = RoomCategory::create(['name' => 'Suite', 'duration_minutes' => 180]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'Habitación 101']);
    }

    protected function tearDown(): void
    {
        AccessSections::forget();
        parent::tearDown();
    }

    public function test_admin_saves_codes_encrypted_and_the_page_never_prints_them(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);

        $this->actingAs($admin)->put(route('access_codes.room', $this->room), ['code' => '4471#'])->assertRedirect();
        $this->actingAs($admin)->post(route('access_codes.store'), ['name' => 'Bodega de blancos', 'kind' => 'bodega', 'code' => '9090'])->assertRedirect();

        $this->assertSame(2, AccessCode::count());
        $this->assertStringNotContainsString('4471', (string) DB::table('access_codes')->where('room_id', $this->room->id)->value('code'));
        $this->assertSame('4471#', AccessCode::where('room_id', $this->room->id)->first()->code);

        $this->actingAs($admin)->get(route('access_codes.index'))->assertOk()
            ->assertSee('Habitación 101')->assertSee('Bodega de blancos')
            ->assertDontSee('4471#')->assertDontSee('9090');
    }

    public function test_revealing_returns_the_codes_and_leaves_a_record(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $code = AccessCode::create(['room_id' => $this->room->id, 'kind' => 'habitacion', 'code' => '1234']);

        $this->actingAs($admin)->postJson(route('access_codes.reveal'), ['ids' => []])
            ->assertOk()->assertJsonPath($code->id.'.code', '1234');

        $log = AuditLog::where('action', 'claves.ver')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['Habitación 101'], $log->new_value['recintos']);
    }

    public function test_host_needs_the_section_and_can_never_change_codes(): void
    {
        $host = User::factory()->create(['role' => 'recepcion']);
        AccessCode::create(['room_id' => $this->room->id, 'kind' => 'habitacion', 'code' => '1234']);

        $this->actingAs($host)->get(route('access_codes.index'))->assertForbidden();
        $this->actingAs($host)->postJson(route('access_codes.reveal'))->assertForbidden();

        DB::table('access_permissions')->updateOrInsert(['role' => 'recepcion', 'section' => 'claves'], ['allowed' => true]);
        AccessSections::forget();

        $this->actingAs($host)->get(route('access_codes.index'))->assertOk()->assertDontSee('Agregar recinto');
        $this->actingAs($host)->postJson(route('access_codes.reveal'))->assertOk();
        $this->actingAs($host)->put(route('access_codes.room', $this->room), ['code' => '0000'])->assertForbidden();
        $this->assertSame('1234', AccessCode::first()->code);
    }
}
