<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@hhmotel.cl',
            'role' => 'administrador',
            // La fábrica usa la clave "password": en una base real sería una
            // puerta abierta. Va la de ADMIN_PASSWORD o una al azar (se entra
            // con la cuenta de soporte y se le pone una clave desde Personal).
            'password' => env('ADMIN_PASSWORD') ?: Str::password(24),
        ]);

        $this->call([
            RoomCategorySeeder::class,
            RoomSeeder::class,
            RateRuleSeeder::class,
            CouponSeeder::class,
            PaymentMethodSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
