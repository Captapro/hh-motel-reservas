<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Número de habitación => cantidad de fotos en public/images/rooms/{numero}/.
    private const PHOTOS = [
        101 => 4, 102 => 4, 103 => 4, 203 => 4, 204 => 4, 206 => 4, 302 => 4,
        305 => 4, 306 => 4, 307 => 4, 310 => 4, 311 => 4, 312 => 4,
    ];

    /**
     * Asigna las fotos reales a cada habitación, solo si todavía no tiene
     * ninguna -- nunca pisa lo que se haya cargado a mano desde el panel.
     */
    public function up(): void
    {
        foreach (self::PHOTOS as $number => $count) {
            $room = DB::table('rooms')->where('name', 'like', '% '.$number)->first(['id', 'photos']);
            if (! $room || ! empty(json_decode((string) $room->photos, true))) {
                continue;
            }
            $paths = array_map(fn ($i) => "/images/rooms/{$number}/{$i}.jpg", range(1, $count));
            DB::table('rooms')->where('id', $room->id)->update(['photos' => json_encode($paths), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
