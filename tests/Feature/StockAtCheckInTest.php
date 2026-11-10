<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Booking;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Booking\ConsumptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PaysBookings;
use Tests\TestCase;

/**
 * Lo vendido antes del check-in (packs, consumos online) no baja el inventario
 * hasta que se entrega; cancelar antes de eso no devuelve nada que no salió.
 */
class StockAtCheckInTest extends TestCase
{
    use PaysBookings;

    private Booking $booking;

    private Combo $combo;

    private ComboItem $choice;

    /** @var array<string, Product> */
    private array $drinks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_11_10_150000_add_stock_deducted_at_to_booking_addons.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_19_150001_add_inventory_to_products_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_08_20_110002_add_combo_id_to_booking_addons_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_10_27_130000_add_choice_items_to_combos.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
            '2026_10_27_140000_add_show_in_catalog_to_combos.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);
        $this->booking = Booking::create([
            'code' => 'HH-TEST-COMBO', 'customer_id' => $customer->id, 'room_id' => $room->id,
            'starts_at' => Carbon::parse('2026-10-05 12:00'), 'ends_at' => Carbon::parse('2026-10-05 15:00'),
            'duration_minutes' => 180, 'guests_count' => 2, 'booking_status' => 'CONFIRMADA', 'payment_status' => 'PAGADA',
            'price_original' => 20000, 'price_final' => 20000,
        ]);

        foreach (['Coca-Cola' => 10, 'Fanta' => 1, 'Sprite' => 6] as $name => $stock) {
            $this->drinks[$name] = Product::create(['name' => $name, 'price' => 2000, 'track_inventory' => true, 'stock' => $stock, 'is_active' => true]);
        }
        $this->combo = Combo::create(['name' => '2 Bebidas', 'price' => 3500, 'is_active' => true]);
        $this->actingAs(User::first())->post(route('admin.combos.items.store', $this->combo), [
            'kind' => 'choice', 'quantity' => 2, 'choice_product_ids' => array_map(fn ($p) => $p->id, array_values($this->drinks)),
        ])->assertSessionHasNoErrors();
        $this->choice = ComboItem::firstOrFail();
    }

    public function test_pack_sold_before_check_in_does_not_touch_stock_until_delivered(): void
    {
        $addon = app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 1, null, [
            $this->choice->id => [$this->drinks['Coca-Cola']->id => 2],
        ]);

        $this->assertNull($addon->stock_deducted_at);
        $this->assertSame(10, $this->drinks['Coca-Cola']->fresh()->stock);
        $this->assertSame(2, app(ConsumptionService::class)->reservedUnits($this->drinks['Coca-Cola']->id));
        $this->assertSame(['2 × Coca-Cola'], $addon->contentLines());

        $this->booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        app(ConsumptionService::class)->deductPending($this->booking->fresh(), null);

        $this->assertSame(8, $this->drinks['Coca-Cola']->fresh()->stock);
        $this->assertNotNull($addon->fresh()->stock_deducted_at);
        $this->assertSame(0, app(ConsumptionService::class)->reservedUnits($this->drinks['Coca-Cola']->id));

        // Un segundo check-in no descuenta de nuevo.
        app(ConsumptionService::class)->deductPending($this->booking->fresh(), null);
        $this->assertSame(8, $this->drinks['Coca-Cola']->fresh()->stock);
    }

    public function test_cancelling_before_delivery_does_not_give_back_stock_that_never_left(): void
    {
        app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 1, null, [
            $this->choice->id => [$this->drinks['Sprite']->id => 2],
        ]);

        $this->booking->load('addons')->restoreConsumedStock();

        $this->assertSame(6, $this->drinks['Sprite']->fresh()->stock);
    }

    public function test_reserved_units_cannot_be_sold_twice(): void
    {
        // Fanta: 1 en bodega. Se reserva en una reserva sin check-in…
        app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 1, null, [
            $this->choice->id => [$this->drinks['Fanta']->id => 1, $this->drinks['Sprite']->id => 1],
        ]);

        // …y otra reserva ya no puede venderla aunque la bodega aún la tenga.
        $this->expectException(InsufficientStockException::class);
        app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 1, null, [
            $this->choice->id => [$this->drinks['Fanta']->id => 1, $this->drinks['Sprite']->id => 1],
        ]);
    }

    public function test_booking_page_shows_what_the_pack_includes_and_that_it_is_pending_delivery(): void
    {
        app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 1, null, [
            $this->choice->id => [$this->drinks['Coca-Cola']->id => 1, $this->drinks['Sprite']->id => 1],
        ]);

        $addon = $this->booking->addons()->with('combo.items')->firstOrFail();
        $html = view('reservations._addon-detail', ['addon' => $addon, 'booking' => $this->booking])->render();

        $this->assertStringContainsString('Incluye:', $html);
        $this->assertStringContainsString('1 × Coca-Cola', $html);
        $this->assertStringContainsString('1 × Sprite', $html);
        $this->assertStringContainsString('Por entregar en el check-in', $html);

        // Ya entregado: sigue mostrando qué incluía, sin el aviso de pendiente.
        $this->booking->update(['checked_in_at' => now(), 'booking_status' => 'CHECK_IN']);
        app(ConsumptionService::class)->deductPending($this->booking->fresh(), null);
        $html = view('reservations._addon-detail', ['addon' => $addon->fresh('combo.items'), 'booking' => $this->booking->fresh()])->render();
        $this->assertStringContainsString('Incluye:', $html);
        $this->assertStringNotContainsString('Por entregar', $html);
    }

    public function test_packs_page_lists_packs_with_their_contents(): void
    {
        $this->get(route('rooms.packs'))
            ->assertOk()
            ->assertSee('2 Bebidas', false);
    }
}
