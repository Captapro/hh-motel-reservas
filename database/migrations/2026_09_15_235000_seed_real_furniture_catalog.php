<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('furniture_categories')->where('name', 'Mobiliario de habitaciones')->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('furniture_categories')->insertGetId([
                'name' => 'Mobiliario de habitaciones',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $items = [
            ['Cama', '🛏️'], ['Circular', '⭕'], ['Rectangular', '▭'],
            ['Tántrico', '💠'], ['Sling tela', '🪢'], ['X', '✕'],
            ['Guillotina', '⚙️'], ['Caballete matroneitor', '🪑'],
            ['Caballete madera', '🪵'], ['Tijeras', '✂️'],
        ];

        foreach ($items as [$name, $icon]) {
            $id = DB::table('furniture_items')->where('furniture_category_id', $categoryId)->where('name', $name)->value('id');
            if (! $id) {
                $data = ['furniture_category_id' => $categoryId, 'name' => $name, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('furniture_items', 'icon')) {
                    $data['icon'] = $icon;
                }
                DB::table('furniture_items')->insert($data);
            } elseif (Schema::hasColumn('furniture_items', 'icon')) {
                DB::table('furniture_items')->where('id', $id)->update(['icon' => $icon, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $categoryId = DB::table('furniture_categories')->where('name', 'Mobiliario de habitaciones')->value('id');
        if ($categoryId) {
            DB::table('furniture_items')->where('furniture_category_id', $categoryId)->delete();
            DB::table('furniture_categories')->where('id', $categoryId)->delete();
        }
    }
};
