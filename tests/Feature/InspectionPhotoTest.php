<?php

namespace Tests\Feature;

use App\Models\InspectionPhoto;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomInspection;
use App\Models\User;
use App\Services\Booking\InspectionPhotoStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspectionPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_09_15_231000_create_room_inspections_table.php',
            '2026_09_15_234000_add_round_details_to_room_inspections.php',
            '2026_10_31_130000_create_inspection_photos_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_inspection_photos_are_served_only_to_logged_in_staff(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('foto.jpg')->store('inspections', 'public');
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['name' => '101', 'room_category_id' => $category->id]);
        $inspection = RoomInspection::create(['room_id' => $room->id, 'inspected_by' => 'Ana', 'shift' => 'Noche', 'checklist' => [], 'needs_maintenance' => true, 'photos' => [$path]]);

        $this->get(route('rooms.inspections.photo', [$inspection, 0]))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('rooms.inspections.photo', [$inspection, 0]))->assertOk();
        $this->get(route('rooms.inspections.photo', [$inspection, 1]))->assertNotFound();
    }

    public function test_photos_live_in_the_database_compressed_and_are_deleted_after_thirty_days(): void
    {
        Storage::fake('public');
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['name' => '101', 'room_category_id' => $category->id]);
        $old = RoomInspection::create(['room_id' => $room->id, 'inspected_by' => 'Ana', 'shift' => 'Noche', 'checklist' => [], 'needs_maintenance' => true, 'photos' => [UploadedFile::fake()->image('vieja.jpg')->store('inspections', 'public')]]);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();
        $recent = RoomInspection::create(['room_id' => $room->id, 'inspected_by' => 'Ana', 'shift' => 'Noche', 'checklist' => [], 'needs_maintenance' => true, 'photos' => [UploadedFile::fake()->image('grande.jpg', 3000, 2000)->store('inspections', 'public')]]);

        // Las del disco pasan a la base de datos; las de más de 30 días se descartan.
        $this->artisan('app:fotos-inspeccion-a-base')->assertSuccessful();
        $this->assertSame([], $old->fresh()->photos);
        $ref = $recent->fresh()->photos[0];
        $this->assertStringStartsWith(InspectionPhoto::PREFIX, $ref);
        [$width] = getimagesizefromstring(InspectionPhoto::firstOrFail()->contents());
        $this->assertSame(1600, $width);

        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $this->get(route('rooms.inspections.photo', [$recent, 0]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // A los 30 días se borra sola y desaparece de la inspección.
        $this->travel(31)->days();
        $this->assertSame(1, app(InspectionPhotoStore::class)->prune());
        $this->assertSame([], $recent->fresh()->photos);
        $this->assertSame(0, InspectionPhoto::count());
    }
}
