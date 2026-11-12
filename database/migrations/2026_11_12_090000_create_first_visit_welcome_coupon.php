<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HH Secret Pass: 15% de bienvenida que Cata ofrece a quien todavía no nos
 * conoce. El sistema lo acepta solo en la primera visita (origin
 * primera_visita, ver CouponValidator) y una vez por cliente. No aparece en el
 * catálogo; se edita o apaga desde Admin → Cupones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('coupons')->where('code', 'SECRETPASS')->exists()) {
            return;
        }

        DB::table('coupons')->insert([
            'code' => 'SECRETPASS',
            'origin' => 'primera_visita',
            'auto_apply' => false,
            'internal_name' => 'HH Secret Pass · 15% primera visita',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'max_uses_per_customer' => 1,
            'is_stackable' => false,
            'requires_verification' => false,
            'is_active' => true,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('coupons')->where('code', 'SECRETPASS')->where('origin', 'primera_visita')->delete();
    }
};
