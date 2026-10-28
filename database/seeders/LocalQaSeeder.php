<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Solo para pruebas locales (APP_ENV=local): usuarios QA para recorrer el sistema en el navegador. */
class LocalQaSeeder extends Seeder
{
    public const PASSWORD = '1a42afbaf231212e';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'Solo local.');
        User::updateOrCreate(['email' => 'qa-admin@hh.test'], ['name' => 'QA Admin', 'role' => 'administrador', 'password' => self::PASSWORD, 'is_active' => true]);
        User::updateOrCreate(['email' => 'qa-anfitrion@hh.test'], ['name' => 'QA Anfitrión', 'role' => 'recepcion', 'password' => self::PASSWORD, 'is_active' => true]);
    }
}
