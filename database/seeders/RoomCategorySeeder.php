<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('room_categories')->insert([
            [
                'max_capacity' => 6, 'name' => 'GO', 'former_name' => 'ex-S',
                'description' => null, 'features' => json_encode([]),
                'base_capacity' => 2, 'extra_guest_from' => 3, 'display_order' => 1,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'max_capacity' => 4, 'name' => 'LITE', 'former_name' => 'ex-S',
                'description' => null,
                'features' => json_encode(['Baño exterior privado', 'Uso exclusivo de la habitación']),
                'base_capacity' => 2, 'extra_guest_from' => 3, 'display_order' => 2,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'max_capacity' => 8, 'name' => 'PLUS', 'former_name' => 'ex Estándar',
                'description' => null, 'features' => json_encode(['LED']),
                'base_capacity' => 2, 'extra_guest_from' => 3, 'display_order' => 3,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'max_capacity' => 12, 'name' => 'MAX', 'former_name' => 'ex XL',
                'description' => null, 'features' => json_encode([]),
                'base_capacity' => 2, 'extra_guest_from' => 3, 'display_order' => 4,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }
}
