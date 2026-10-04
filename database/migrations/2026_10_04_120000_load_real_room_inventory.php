<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROOMS = [
        101 => 'PLUS', 102 => 'PLUS', 103 => 'MAX',
        201 => 'GO', 202 => 'PLUS', 203 => 'MAX', 204 => 'NEW LITE', 205 => 'NEW LITE', 206 => 'PLUS', 207 => 'PLUS',
        208 => 'LITE', 209 => 'LITE', 210 => 'MAX', 212 => 'PLUS',
        301 => 'GO', 302 => 'PLUS', 303 => 'MAX', 304 => 'NEW LITE', 305 => 'NEW LITE', 306 => 'PLUS', 307 => 'PLUS',
        308 => 'LITE', 309 => 'LITE', 310 => 'MAX', 311 => 'PLUS', 312 => 'PLUS',
    ];

    private const PLACEHOLDERS = ['GO 101', 'GO 102', 'LITE 108', 'PLUS 205', 'PLUS 206', 'MAX 304'];

    // [duración en minutos => precio HH, precio HOT] -- tarifas de NEW LITE.
    private const NEW_LITE_PRICES = [
        180 => ['HH' => 32000, 'HOT' => 40000],
        360 => ['HH' => 39000, 'HOT' => 48800],
        720 => ['HH' => 44800, 'HOT' => 56000],
    ];

    /**
     * Reemplaza las habitaciones de ejemplo del seeder por el inventario real
     * de HH Motel. Empareja por número de habitación y actualiza en el lugar
     * (así ninguna reserva queda huérfana); nunca borra una pieza con
     * reservas. Sin las 4 categorías base (base recién creada, todavía sin
     * seeders) no hace nada.
     */
    public function up(): void
    {
        $now = now();
        $base = DB::table('room_categories')->whereIn('name', ['GO', 'LITE', 'PLUS', 'MAX'])->count();
        if ($base < 4) {
            return;
        }

        if (! DB::table('room_categories')->where('name', 'NEW LITE')->exists()) {
            DB::table('room_categories')->where('name', 'PLUS')->update(['display_order' => 4]);
            DB::table('room_categories')->where('name', 'MAX')->update(['display_order' => 5]);
            DB::table('room_categories')->insert([
                'name' => 'NEW LITE',
                'features' => json_encode(['Baño interior privado', 'Uso exclusivo de la habitación']),
                'sales_tip' => 'Baño interior con ducha integrada al ambiente, visible desde la cama.',
                'base_capacity' => 2, 'extra_guest_from' => 3, 'display_order' => 3,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $categoryIds = DB::table('room_categories')->pluck('id', 'name');

        foreach (['HH', 'HOT'] as $ruleName) {
            $ruleId = DB::table('rate_rules')->where('name', $ruleName)->value('id');
            if (! $ruleId) {
                continue;
            }
            foreach (self::NEW_LITE_PRICES as $minutes => $prices) {
                $exists = DB::table('rate_rule_prices')->where([
                    'rate_rule_id' => $ruleId, 'room_category_id' => $categoryIds['NEW LITE'], 'duration_minutes' => $minutes,
                ])->exists();
                if (! $exists) {
                    DB::table('rate_rule_prices')->insert([
                        'rate_rule_id' => $ruleId, 'room_category_id' => $categoryIds['NEW LITE'],
                        'duration_minutes' => $minutes, 'price' => $prices[$ruleName],
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        $existingByNumber = [];
        foreach (DB::table('rooms')->orderBy('id')->get(['id', 'name']) as $room) {
            if (preg_match('/(\d{3})$/', $room->name, $m)) {
                $existingByNumber[(int) $m[1]] ??= $room;
            }
        }

        foreach (self::ROOMS as $number => $category) {
            $name = $category.' '.$number;
            $wing = match (true) {
                $number <= 103 => null,
                ($number >= 201 && $number <= 206) || ($number >= 301 && $number <= 306) => 'norte',
                default => 'sur',
            };

            if (isset($existingByNumber[$number])) {
                DB::table('rooms')->where('id', $existingByNumber[$number]->id)->update([
                    'name' => $name, 'room_category_id' => $categoryIds[$category], 'wing' => $wing, 'updated_at' => $now,
                ]);
            } else {
                DB::table('rooms')->insert([
                    'room_category_id' => $categoryIds[$category], 'name' => $name, 'wing' => $wing,
                    'photos' => json_encode([]), 'buffer_minutes' => 15, 'operational_status' => 'activa',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $kept = array_map(fn ($c, $n) => $c.' '.$n, self::ROOMS, array_keys(self::ROOMS));
        $leftovers = DB::table('rooms')->whereIn('name', self::PLACEHOLDERS)->whereNotIn('name', $kept)->pluck('id');
        foreach ($leftovers as $id) {
            if (DB::table('bookings')->where('room_id', $id)->exists()) {
                DB::table('rooms')->where('id', $id)->update(['operational_status' => 'inactiva', 'updated_at' => $now]);
            } else {
                DB::table('rooms')->where('id', $id)->delete();
            }
        }
    }

    public function down(): void {}
};
