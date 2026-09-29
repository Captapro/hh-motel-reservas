<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\RateRule;
use App\Models\RateRulePrice;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * findAvailableRoom() (compartida por checkAvailability() y store() del
 * catálogo público) no comprobaba dos cosas que store() sí exige después
 * de todos modos vía otras rutas:
 *  1. RoomCategory::is_active -- una pestaña abierta antes de apagar la
 *     categoría podía completar la reserva igual.
 *  2. El horario operativo de la tarifa -- el chequeo en vivo contestaba
 *     "disponible" para un horario que store() iba a rechazar por tarifa,
 *     mandando al cliente a llenar todo el formulario para nada.
 */
class PublicBookingValidationTest extends TestCase
{
    private RoomCategory $category;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120005_create_rate_rule_windows_table.php',
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120007_create_calendar_overrides_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120009_create_coupon_rooms_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_09_11_090000_add_identity_fields_to_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
            '2026_08_18_120013_create_booking_guests_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
            '2026_09_16_040100_create_operational_settings_table.php',
            '2026_09_17_002913_add_floor_toggles_to_operational_settings.php',
            '2026_09_21_135116_split_floor_toggles_by_wing_in_operational_settings.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->category = RoomCategory::create(['name' => 'GO', 'display_order' => 1, 'is_active' => true]);
        $this->room = Room::create(['room_category_id' => $this->category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
        Customer::create(['name' => 'Cliente Prueba', 'phone_e164' => '+56911111111']);

        $rateRule = RateRule::create(['name' => 'HH', 'is_active' => true]);
        // Lunes 10:00-23:00 -- deja un hueco real fuera de esa ventana para
        // probar el horario "que se anuncia disponible pero se rechaza".
        RateRuleWindow::create(['rate_rule_id' => $rateRule->id, 'weekday' => 1, 'start_time' => '10:00:00', 'end_time' => '23:00:00', 'wraps_midnight' => false]);
        RateRulePrice::create(['rate_rule_id' => $rateRule->id, 'room_category_id' => $this->category->id, 'duration_minutes' => 180, 'price' => 20000]);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'room_category_id' => $this->category->id,
            'date' => '2026-10-05', // lunes
            'time_hour' => 14,
            'time_minute' => 0,
            'duration_minutes' => 180,
            'guests_count' => 2,
            'first_name' => 'Cliente',
            'last_name' => 'Público',
            'phone' => '+56922222222',
        ], $overrides);
    }

    public function test_check_availability_rejects_a_disabled_category(): void
    {
        $this->category->update(['is_active' => false]);

        $response = $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query([
            'room_category_id' => $this->category->id,
            'date' => '2026-10-05',
            'time_hour' => 14,
            'time_minute' => 0,
            'duration_minutes' => 180,
        ]));

        $response->assertJson(['available' => false]);
    }

    public function test_store_rejects_a_disabled_category_even_with_a_stale_form(): void
    {
        // La categoría estaba activa cuando se cargó el formulario, pero se
        // apagó antes de que el cliente enviara -- store() la tiene que
        // rechazar igual, no solo el chequeo en vivo.
        $this->category->update(['is_active' => false]);

        $response = $this->post('/catalogo/reservar', $this->bookingPayload());

        $response->assertSessionHasErrors();
        $this->assertSame(0, Booking::count());
    }

    public function test_check_availability_agrees_with_store_outside_the_operating_window(): void
    {
        $payload = [
            'room_category_id' => $this->category->id,
            'date' => '2026-10-05',
            'time_hour' => 5, // fuera de la ventana 10:00-23:00
            'time_minute' => 0,
            'duration_minutes' => 180,
        ];

        $this->getJson('/catalogo/reservar/disponibilidad?'.http_build_query($payload))
            ->assertJson(['available' => false]);

        $this->post('/catalogo/reservar', $this->bookingPayload(['time_hour' => 5]))
            ->assertSessionHasErrors();
        $this->assertSame(0, Booking::count());
    }

    public function test_store_still_succeeds_within_the_operating_window_for_an_active_category(): void
    {
        $response = $this->post('/catalogo/reservar', $this->bookingPayload());

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Booking::count());
    }
}
