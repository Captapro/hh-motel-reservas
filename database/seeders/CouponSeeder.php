<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CouponSeeder extends Seeder
{
    /**
     * Promociones vigentes reales de HH Motel. Ninguna es acumulable con otra.
     */
    public function run(): void
    {
        $now = now();

        DB::table('coupons')->insert([
            [
                'code' => 'MORNING ESCAPE',
                'internal_name' => 'Morning Escape',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_amount' => null, 'max_discount_amount' => null,
                'starts_at' => null, 'ends_at' => null,
                'allowed_weekdays' => json_encode([1, 2, 3, 4]), // lun-jue
                'allowed_time_start' => '10:30:00', 'allowed_time_end' => '13:00:00',
                'allowed_durations' => null,
                'max_uses_total' => null, 'max_uses_per_customer' => null,
                'is_stackable' => false,
                'requires_verification' => false, 'verification_note' => null,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'EXPERTOS EN VIDA',
                'internal_name' => 'Expertos en Vida (65+)',
                'min_age' => 65,
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'min_amount' => null, 'max_discount_amount' => null,
                'starts_at' => null, 'ends_at' => null,
                'allowed_weekdays' => null, // todos los días
                'allowed_time_start' => null, 'allowed_time_end' => null,
                'allowed_durations' => null,
                'max_uses_total' => null, 'max_uses_per_customer' => null,
                'is_stackable' => false,
                'requires_verification' => true,
                'verification_note' => 'Cédula de identidad — 65 años o más',
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'ESTUDIANTES +18',
                'internal_name' => 'Estudiantes +18',
                'discount_type' => 'fixed',
                'discount_value' => 5000,
                'min_amount' => null, 'max_discount_amount' => null,
                'starts_at' => null, 'ends_at' => null,
                'allowed_weekdays' => json_encode([1, 2, 3, 4]), // lun-jue
                'allowed_time_start' => null, 'allowed_time_end' => null,
                'allowed_durations' => null,
                'max_uses_total' => null, 'max_uses_per_customer' => null,
                'is_stackable' => false,
                'requires_verification' => true,
                'verification_note' => 'Credencial de estudiante vigente',
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }
}
