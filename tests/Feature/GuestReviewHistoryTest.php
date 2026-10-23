<?php

namespace Tests\Feature;

use App\Models\GuestReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuestReviewHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        DB::statement('create table guest_reviews (id integer primary key autoincrement, booking_id integer null, rating integer not null, comment text null, created_at datetime null, updated_at datetime null)');
    }

    public function test_internal_review_history_is_deleted_only_with_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        GuestReview::create(['rating' => 2, 'comment' => 'prueba']);
        GuestReview::create(['rating' => 5]);

        $this->delete(route('admin.reviews.destroy_all'), ['confirm_text' => 'no'])->assertSessionHasErrors('reviews');
        $this->assertSame(2, GuestReview::count());

        $this->delete(route('admin.reviews.destroy_all'), ['confirm_text' => 'ELIMINAR'])->assertSessionHas('status');
        $this->assertSame(0, GuestReview::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'opiniones.borrar_historial']);
    }

    public function test_non_admins_cannot_delete_the_history(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'recepcion']));
        GuestReview::create(['rating' => 1]);

        $this->delete(route('admin.reviews.destroy_all'), ['confirm_text' => 'ELIMINAR'])->assertForbidden();
        $this->assertSame(1, GuestReview::count());
    }
}
