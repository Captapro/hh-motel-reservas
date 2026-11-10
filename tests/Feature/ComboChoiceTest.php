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
 * Combos a elección ("2 bebidas a elegir"): cada sabor se descuenta de su
 * propio stock, no se puede vender sin elegir, y al devolver el consumo
 * vuelve al inventario el sabor que realmente salió.
 */
class ComboChoiceTest extends TestCase
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
            'duration_minutes' => 180, 'guests_count' => 2, 'booking_status' => 'CHECK_IN', 'payment_status' => 'PAGADA',
            'price_original' => 20000, 'price_final' => 20000, 'checked_in_at' => now(),
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

    public function test_each_flavor_comes_out_of_its_own_stock_and_goes_back_when_restored(): void
    {
        $this->assertTrue($this->choice->isChoice());
        $this->assertSame(8, $this->combo->fresh('items')->availableCount()); // (10 + 1 + 6) / 2

        $addon = app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 2, null, [
            $this->choice->id => [$this->drinks['Coca-Cola']->id => 3, $this->drinks['Fanta']->id => 1],
        ]);

        $this->assertSame(7, $this->drinks['Coca-Cola']->fresh()->stock);
        $this->assertSame(0, $this->drinks['Fanta']->fresh()->stock);
        $this->assertSame(6, $this->drinks['Sprite']->fresh()->stock);
        $this->assertSame(7000, (int) $addon->amount);
        $this->assertStringContainsString('3 Coca-Cola, 1 Fanta', $addon->description);

        $this->booking->load('addons')->restoreConsumedStock();
        $this->assertSame(10, $this->drinks['Coca-Cola']->fresh()->stock);
        $this->assertSame(1, $this->drinks['Fanta']->fresh()->stock);
    }

    public function test_it_cannot_be_sold_without_choosing_or_beyond_a_flavor_stock(): void
    {
        $sell = fn (array $choices) => app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 1, null, $choices);

        foreach ([
            [],                                                               // sin elegir
            [$this->choice->id => [$this->drinks['Sprite']->id => 1]],         // falta uno
            [$this->choice->id => [$this->drinks['Fanta']->id => 2]],          // Fanta tiene 1
        ] as $choices) {
            try {
                $sell($choices);
                $this->fail('Debió rechazarse');
            } catch (InsufficientStockException) {
            }
        }
        $this->assertSame(17, Product::sum('stock'));
        $this->assertSame(0, $this->booking->addons()->count());
    }

    public function test_reception_form_sends_the_chosen_flavors(): void
    {
        $this->post("/reservas/{$this->booking->code}/consumo", [
            'combo_quantities' => [$this->combo->id => 1],
            'combo_choices' => [$this->combo->id => [$this->choice->id => [$this->drinks['Sprite']->id => 2]]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $this->drinks['Sprite']->fresh()->stock);
    }

    public function test_a_choice_by_category_includes_every_active_product_of_that_category_even_new_ones(): void
    {
        foreach ($this->drinks as $drink) {
            $drink->update(['category' => 'Bebidas']);
        }
        Product::create(['name' => 'Agua', 'category' => 'Agua y jugos', 'price' => 1500, 'is_active' => true]);
        $pack = Combo::create(['name' => 'Pack bebidas', 'price' => 3500, 'is_active' => true]);
        $this->post(route('admin.combos.items.store', $pack), ['kind' => 'choice_category', 'choice_category' => 'Bebidas', 'quantity' => 2])->assertSessionHasNoErrors();
        $item = $pack->items()->firstOrFail();
        $this->assertSame('2 a elegir de Bebidas', $item->label());
        $this->assertCount(3, $item->options());

        // Una bebida nueva entra sola al pack.
        $pap = Product::create(['name' => 'Pap', 'category' => 'Bebidas', 'price' => 2000, 'track_inventory' => true, 'stock' => 4, 'is_active' => true]);
        app(ConsumptionService::class)->addCombo($this->booking, $pack->fresh(), 1, null, [$item->id => [$pap->id => 2]]);
        $this->assertSame(2, $pap->fresh()->stock);
    }

    public function test_finalize_screen_offers_the_flavor_picker_and_saves_the_choice(): void
    {
        $this->payRoom($this->booking);
        $this->get(route('bookings.finalize.show', $this->booking->code))
            ->assertOk()->assertSee('data-choices', false)->assertSee('Sprite');

        $this->post(route('bookings.finalize.store', $this->booking->code), [
            'confirm_room_checked' => '1',
            'combo_quantities' => [$this->combo->id => 1],
            'combo_choices' => [$this->combo->id => [$this->choice->id => [$this->drinks['Coca-Cola']->id => 2]]],
        ]);

        $this->assertSame(8, $this->drinks['Coca-Cola']->fresh()->stock);
    }

    public function test_admin_pauses_and_reactivates_a_combo_from_the_list(): void
    {
        $this->post(route('admin.combos.toggle', $this->combo))->assertRedirect(route('admin.combos.index'));
        $this->assertFalse($this->combo->fresh()->is_active);
        $this->post(route('admin.combos.toggle', $this->combo));
        $this->assertTrue($this->combo->fresh()->is_active);

        $host = User::factory()->create(['role' => 'recepcion']);
        $this->actingAs($host)->post(route('admin.combos.toggle', $this->combo))->assertForbidden();
    }

    public function test_admin_shows_and_hides_a_combo_in_the_customer_catalog(): void
    {
        $this->assertFalse((bool) $this->combo->fresh()->show_in_catalog);
        $this->post(route('admin.combos.catalog', $this->combo))->assertRedirect(route('admin.combos.index'));
        $this->assertTrue($this->combo->fresh()->show_in_catalog);
        $this->get(route('admin.combos.index'))->assertOk()->assertSee('En catálogo');

        $this->post(route('admin.combos.catalog', $this->combo));
        $this->assertFalse($this->combo->fresh()->show_in_catalog);
    }

    public function test_combo_list_counts_packs_sold_leaving_out_cancelled_bookings(): void
    {
        $picks = [$this->choice->id => [$this->drinks['Coca-Cola']->id => 4]];
        app(ConsumptionService::class)->addCombo($this->booking, $this->combo, 2, null, $picks);

        $cancelled = $this->booking->replicate(['code'])->fill(['code' => 'HH-TEST-CANCEL', 'booking_status' => 'CANCELADA']);
        $cancelled->save();
        app(ConsumptionService::class)->addCombo($cancelled, $this->combo, 1, null, [$this->choice->id => [$this->drinks['Sprite']->id => 2]]);

        $combo = $this->get(route('admin.combos.index'))->assertOk()->assertSee('Packs vendidos')->viewData('combos')->first();
        $this->assertSame(2, (int) $combo->sold_today);
        $this->assertSame(2, (int) $combo->sold_month);
        $this->assertSame(2, (int) $combo->sold_total);
        $this->assertSame(7000, (int) $combo->amount_month);
    }

    public function test_the_tv_screen_shows_active_combos_and_qr_codes_without_login(): void
    {
        foreach (['2026_11_02_200000_create_tv_screen_settings_table.php', '2026_11_02_220000_add_playlist_to_tv_screen_settings.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        auth()->logout();
        $this->combo->update(['show_in_catalog' => true]);
        Combo::create(['name' => 'Combo apagado', 'price' => 9000, 'is_active' => false, 'show_in_catalog' => true]);
        Combo::create(['name' => 'Combo solo recepción', 'price' => 9000, 'is_active' => true, 'show_in_catalog' => false]);

        $this->get('/pantalla')->assertOk()
            ->assertSee('2 Bebidas')
            ->assertSee('$3.500')
            ->assertSee('2 a elegir: Coca-Cola / Fanta / Sprite')
            ->assertSee('Reserva desde tu celular')
            ->assertSee('Danos tu opinión')
            ->assertSee('data:image/png;base64', false)
            ->assertDontSee('Combo apagado')
            ->assertDontSee('Combo solo recepción');
    }

    public function test_the_admin_chooses_each_slide_and_its_order_on_the_tv(): void
    {
        foreach (['2026_11_02_200000_create_tv_screen_settings_table.php', '2026_11_02_220000_add_playlist_to_tv_screen_settings.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->combo->update(['show_in_catalog' => true]);
        $other = Combo::create(['name' => 'Pack Oculto', 'price' => 5000, 'is_active' => true, 'show_in_catalog' => true]);

        // La lista trae cada combo, los dos QR y los mensajes con título.
        $this->put(route('admin.tv_screen.update'), [
            'combo_seconds' => 8, 'qr_seconds' => 20, 'combo_cta' => 'Pídelo en recepción',
            'messages' => [['title' => 'Lunes a jueves 10% off', 'text' => 'En todas las categorías']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('admin.tv_screen.edit'))->assertOk()
            ->assertSee('Combo · 2 Bebidas')->assertSee('Combo · Pack Oculto')
            ->assertSee('QR · Reserva desde tu celular')->assertSee('QR · Danos tu opinión')
            ->assertSee('Mensaje · Lunes a jueves 10% off');

        // Opinión primero, luego el mensaje y el combo; Pack Oculto y el QR de reservar apagados.
        $this->put(route('admin.tv_screen.update'), [
            'combo_seconds' => 8, 'qr_seconds' => 20, 'combo_cta' => 'Pídelo en recepción',
            'messages' => [['title' => 'Lunes a jueves 10% off', 'text' => 'En todas las categorías']],
            'playlist' => [
                ['key' => 'review', 'on' => '1'],
                ['key' => 'message:0', 'on' => '1'],
                ['key' => 'combo:'.$this->combo->id, 'on' => '1'],
                ['key' => 'combo:'.$other->id],
                ['key' => 'reserve'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        auth()->logout();
        $html = $this->get('/pantalla')->assertOk()
            ->assertSeeInOrder(['Danos tu opinión', 'Lunes a jueves 10% off', '2 Bebidas'])
            ->assertSee('Pídelo en recepción')
            ->assertDontSee('Pack Oculto')
            ->assertDontSee('Reserva desde tu celular')
            ->getContent();
        $this->assertStringContainsString('data-seconds="8"', $html);
        $this->assertStringContainsString('data-seconds="20"', $html);

        // Un combo nuevo en el catálogo entra encendido al final.
        Combo::create(['name' => 'Pack Nuevo', 'price' => 4000, 'is_active' => true, 'show_in_catalog' => true]);
        $this->get('/pantalla')->assertSeeInOrder(['2 Bebidas', 'Pack Nuevo']);

        // Recepción no puede programarla.
        $this->actingAs(User::factory()->create(['role' => 'recepcion']))->get(route('admin.tv_screen.edit'))->assertForbidden();
    }
}
