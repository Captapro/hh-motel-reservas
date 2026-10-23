<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffShiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MucamaShiftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-23 15:00', 'America/Santiago'));
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

    public function test_forgotten_shifts_close_themselves_after_the_cap(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $old = StaffShiftLog::create(['user_id' => $admin->id, 'started_at' => now()->subDays(24)]);
        $recent = StaffShiftLog::create(['user_id' => $admin->id, 'started_at' => now()->subHours(3)]);

        $open = $admin->openShiftLog();

        $this->assertSame($recent->id, $open->id);
        $this->assertNotNull($old->fresh()->ended_at);
        $this->assertSame(StaffShiftLog::MAX_SHIFT_HOURS, (int) $old->fresh()->started_at->diffInHours($old->fresh()->ended_at));
        $this->assertNull($recent->fresh()->ended_at);
    }

    public function test_only_mucamas_in_a_real_shift_are_listed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $working = Staff::create(['name' => 'Trabajando', 'role' => 'Mucama', 'is_active' => true]);
        $off = Staff::create(['name' => 'Fuera de turno', 'role' => 'Mucama', 'is_active' => true]);
        $forgot = Staff::create(['name' => 'Olvidó salir', 'role' => 'Mucama', 'is_active' => true]);
        StaffShiftLog::create(['staff_id' => $working->id, 'started_at' => now()->subHour()]);
        StaffShiftLog::create(['staff_id' => $forgot->id, 'started_at' => now()->subDays(3)]);

        $this->getJson(route('mucamas.activity.status'))->assertOk()
            ->assertJsonCount(1)->assertJsonFragment(['name' => 'Trabajando'])
            ->assertJsonMissing(['name' => 'Fuera de turno'])->assertJsonMissing(['name' => 'Olvidó salir']);
    }
}
