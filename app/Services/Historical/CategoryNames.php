<?php

namespace App\Services\Historical;

use App\Models\Room;
use Illuminate\Support\Facades\Schema;

/**
 * Las planillas antiguas llaman a los playrooms con otros nombres: STANDARD es PLUS,
 * XL es MAX y S era lo que hoy son LITE y GO. Aquí se traducen al nombre de hoy sin
 * tocar lo guardado (la planilla queda tal cual). «S» se reparte por el número de
 * habitación según la categoría actual de esa habitación; si no se sabe, «LITE / GO».
 */
class CategoryNames
{
    private const ALIASES = ['STANDARD' => 'PLUS', 'XL' => 'MAX'];

    private const SMALL = ['S'];

    /** @var array<string, string>|null número de habitación => categoría actual */
    private static ?array $rooms = null;

    public static function canonical(?string $category, ?string $roomLabel = null): ?string
    {
        $category = $category === null ? null : mb_strtoupper(trim($category));
        if ($category === null || $category === '') {
            return null;
        }
        if (isset(self::ALIASES[$category])) {
            return self::ALIASES[$category];
        }
        if (! in_array($category, self::SMALL, true)) {
            return $category;
        }

        $current = self::rooms()[self::roomNumber($roomLabel)] ?? null;

        return in_array($current, ['LITE', 'GO'], true) ? $current : 'LITE / GO';
    }

    public static function forget(): void
    {
        self::$rooms = null;
    }

    private static function roomNumber(?string $label): string
    {
        return preg_match('/(\d+)\s*$/', (string) $label, $m) ? $m[1] : '';
    }

    /** @return array<string, string> */
    private static function rooms(): array
    {
        if (self::$rooms === null) {
            self::$rooms = [];
            if (Schema::hasTable('rooms') && Schema::hasTable('room_categories')) {
                foreach (Room::with('category')->get() as $room) {
                    $number = self::roomNumber($room->name);
                    if ($number !== '' && $room->category) {
                        self::$rooms[$number] = mb_strtoupper($room->category->name);
                    }
                }
            }
        }

        return self::$rooms;
    }
}
