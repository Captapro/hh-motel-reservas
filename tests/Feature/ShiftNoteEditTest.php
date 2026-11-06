<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ShiftNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftNoteEditTest extends TestCase
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
            '2026_11_04_120000_create_shift_notes_tables.php',
            '2026_11_05_100000_add_pinned_to_shift_notes.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_only_administration_edits_a_note_and_the_old_text_stays_in_the_log(): void
    {
        $host = User::factory()->create(['role' => 'recepcion', 'name' => 'Anfitriona Prueba']);
        $admin = User::factory()->create(['role' => 'administrador']);
        $note = ShiftNote::create(['user_id' => $admin->id, 'category' => 'Otro', 'body' => 'Texto viejo', 'pinned' => true]);
        $host->readShiftNotes()->attach($note->id, ['read_at' => now()]);

        $this->actingAs($host)->patch(route('shift_notes.update', $note), ['body' => 'Cambio no permitido'])->assertForbidden();
        $this->assertSame('Texto viejo', $note->fresh()->body);

        $this->actingAs($admin)->patch(route('shift_notes.update', $note), ['body' => ''])->assertSessionHasErrors('body');
        $this->patch(route('shift_notes.update', $note), ['body' => 'Texto nuevo'])->assertRedirect();

        $this->assertSame('Texto nuevo', $note->fresh()->body);
        $this->assertTrue($note->fresh()->pinned);
        $this->assertSame([$note->id], ShiftNote::alertFor($host)->pluck('id')->all()); // el equipo confirma el texto nuevo

        $log = AuditLog::where('action', 'novedad.editada')->sole();
        $this->assertStringContainsString('Texto viejo', (string) $log->getRawOriginal('old_value'));
        $this->assertStringContainsString('Anfitriona Prueba', (string) $log->getRawOriginal('old_value'));
    }
}
