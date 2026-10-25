<?php

namespace Tests\Feature;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Pricing\CouponValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CouponValidator es la única puerta de entrada que decide si un cupón u
 * oferta puede aplicarse a una reserva puntual -- estas pruebas cubren dos
 * reglas que ya estaban implementadas pero sin ningún test que las dejara
 * fijas: la vigencia temporal (starts_at/ends_at) y que una oferta dirigida
 * a UNA habitación puntual no se filtre a otra habitación de la misma
 * categoría.
 */
class CouponScopeAndWindowTest extends TestCase
{
    private Room $roomA;

    private Room $roomB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120009_create_coupon_rooms_table.php',
            '2026_09_10_130000_add_auto_apply_offers_to_coupons.php',
            '2026_09_11_090001_add_min_age_to_coupons_table.php',
            '2026_09_16_000000_add_included_extra_guests_to_coupons.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->roomA = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $this->roomB = Room::create(['room_category_id' => $category->id, 'name' => 'GO 102', 'operational_status' => 'activa']);
    }

    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => null,
            'auto_apply' => true,
            'internal_name' => 'Oferta de prueba',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
        ], $overrides));
    }

    public function test_offer_outside_its_validity_window_is_rejected(): void
    {
        $validator = app(CouponValidator::class);
        $now = Carbon::parse('2026-09-21 15:00');

        $notStartedYet = $this->makeCoupon(['starts_at' => '2026-10-01']);
        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('todavía no comienza');
        $validator->validate($notStartedYet, $this->roomA, $now, 60, null, 20000);
    }

    public function test_offer_already_expired_is_rejected(): void
    {
        $validator = app(CouponValidator::class);
        $now = Carbon::parse('2026-09-21 15:00');

        $expired = $this->makeCoupon(['ends_at' => '2026-09-01']);
        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('ya venció');
        $validator->validate($expired, $this->roomA, $now, 60, null, 20000);
    }

    public function test_offer_within_its_validity_window_is_accepted(): void
    {
        $validator = app(CouponValidator::class);
        $now = Carbon::parse('2026-09-21 15:00');

        $live = $this->makeCoupon(['starts_at' => '2026-09-01', 'ends_at' => '2026-09-30']);
        $discount = $validator->validate($live, $this->roomA, $now, 60, null, 20000);

        $this->assertSame(4000, $discount); // 20% de 20.000
    }

    public function test_offer_targeted_at_one_room_does_not_apply_to_a_different_room(): void
    {
        $validator = app(CouponValidator::class);
        $now = Carbon::parse('2026-09-21 15:00');

        $offer = $this->makeCoupon();
        $offer->rooms()->attach($this->roomA->id);

        // La habitación correcta sí recibe el descuento.
        $discount = $validator->validate($offer, $this->roomA, $now, 60, null, 20000);
        $this->assertSame(4000, $discount);

        // La otra habitación de la MISMA categoría no -- el alcance es por
        // habitación puntual, no por categoría.
        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('no aplica a esta habitación');
        $validator->validate($offer, $this->roomB, $now, 60, null, 20000);
    }
}
