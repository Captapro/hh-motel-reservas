<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const VIDEO = 'https://assets.cdn.filesafe.space/ksYYfSiY8nP4YFvkrJFJ/media/6aa80ad149f830e49b1cd8f4.mp4';

    /**
     * Video de la categoría NEW LITE en sus cuatro habitaciones. Solo
     * donde todavía no hay videos -- nunca pisa lo cargado desde el panel.
     */
    public function up(): void
    {
        $categoryId = DB::table('room_categories')->where('name', 'NEW LITE')->value('id');
        if (! $categoryId) {
            return;
        }

        foreach (DB::table('rooms')->where('room_category_id', $categoryId)->get(['id', 'videos']) as $room) {
            if (! empty(json_decode((string) $room->videos, true))) {
                continue;
            }
            DB::table('rooms')->where('id', $room->id)->update(['videos' => json_encode([self::VIDEO]), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
