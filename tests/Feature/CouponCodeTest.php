<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Support\CouponCode;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El cliente escribe el cupón como lo vio afuera ("cupón AM" en hhmotel.cl, "Morning escape"):
 * el catálogo lo tiene que encontrar igual, sin exigir el código exacto con su espacio.
 */
class CouponCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '2026_08_18_120008_create_coupons_table.php',
            '2026_09_10_130000_add_auto_apply_offers_to_coupons.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_codes_match_ignoring_case_spaces_signs_and_known_aliases(): void
    {
        $morning = Coupon::create(['code' => 'MORNING ESCAPE', 'internal_name' => 'Morning Escape', 'discount_type' => 'percentage', 'discount_value' => 20, 'is_active' => true]);
        $students = Coupon::create(['code' => 'ESTUDIANTES +18', 'internal_name' => 'Estudiantes', 'discount_type' => 'fixed', 'discount_value' => 5000, 'is_active' => true]);
        $query = fn () => Coupon::where('auto_apply', false)->where('is_active', true);

        foreach (['MORNING ESCAPE', 'morning escape', 'MorningEscape', ' morning-escape ', 'AM', 'am'] as $typed) {
            $this->assertTrue($morning->is(CouponCode::find($query(), $typed)), "no encontró «{$typed}»");
        }
        $this->assertTrue($students->is(CouponCode::find($query(), 'estudiantes 18')));

        $this->assertNull(CouponCode::find($query(), 'MORNING'));
        $this->assertNull(CouponCode::find($query(), ''));
        $morning->update(['is_active' => false]);
        $this->assertNull(CouponCode::find($query(), 'AM'));
    }
}
