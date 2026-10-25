<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RateRuleSeeder extends Seeder
{
    /**
     * Tarifas oficiales reales de HH Motel (2 personas), tomadas de la
     * imagen de tarifas compartida. weekday: 0=domingo ... 6=sábado.
     */
    public function run(): void
    {
        $now = now();

        $hhId = DB::table('rate_rules')->insertGetId([
            'name' => 'HH',
            'description' => 'Lunes a jueves, 10:30 a 03:00 (se reinicia cada noche).',
            'extra_person_price' => 17500,
            'extra_hour_price' => 10000,
            'priority' => 1,
            'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $hotId = DB::table('rate_rules')->insertGetId([
            'name' => 'HOT',
            'description' => 'Viernes 10:30 a domingo 22:30, horario continuo.',
            'extra_person_price' => 20000,
            'extra_hour_price' => 15000,
            'priority' => 2,
            'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Ventanas semanales ---
        // HH: lunes(1) a jueves(4), cada día 10:30 -> 03:00 del día siguiente.
        foreach ([1, 2, 3, 4] as $weekday) {
            DB::table('rate_rule_windows')->insert([
                'rate_rule_id' => $hhId, 'weekday' => $weekday,
                'start_time' => '10:30:00', 'end_time' => '03:00:00',
                'wraps_midnight' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // HOT: bloque continuo viernes(5) 10:30 -> sábado(6) completo -> domingo(0) 22:30.
        DB::table('rate_rule_windows')->insert([
            [
                'rate_rule_id' => $hotId, 'weekday' => 5, // viernes
                'start_time' => '10:30:00', 'end_time' => '00:00:00',
                'wraps_midnight' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rate_rule_id' => $hotId, 'weekday' => 6, // sábado, día completo
                'start_time' => '00:00:00', 'end_time' => '00:00:00',
                'wraps_midnight' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rate_rule_id' => $hotId, 'weekday' => 0, // domingo
                'start_time' => '00:00:00', 'end_time' => '22:30:00',
                'wraps_midnight' => false,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // --- Precios por categoría y duración ---
        $catIds = DB::table('room_categories')->pluck('id', 'name');

        $prices = [
            // categoria, duracion(min), HH, HOT
            ['GO', 60, 20000, 25000],
            ['GO', 120, 26000, 32500],
            ['LITE', 180, 30000, 37500],
            ['PLUS', 180, 36000, 45000],
            ['PLUS', 360, 43920, 54900],
            ['PLUS', 720, 50400, 63000],
            ['MAX', 180, 43200, 54000],
            ['MAX', 360, 52800, 66000],
            ['MAX', 720, 61440, 76800],
        ];

        foreach ($prices as [$category, $minutes, $hhPrice, $hotPrice]) {
            DB::table('rate_rule_prices')->insert([
                [
                    'rate_rule_id' => $hhId, 'room_category_id' => $catIds[$category],
                    'duration_minutes' => $minutes, 'price' => $hhPrice,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'rate_rule_id' => $hotId, 'room_category_id' => $catIds[$category],
                    'duration_minutes' => $minutes, 'price' => $hotPrice,
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
        }
    }
}
