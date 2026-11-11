<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El teléfono es la identidad del cliente: solo se corrige desde su ficha cuando el guardado
 * está mal escrito, y entonces sus reservas rechazadas por GHL se reintentan.
 */
class CustomerPhoneFixTest extends TestCase
{
    private Room $room;

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
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_08_18_120020_create_tracking_data_table.php',
            '2026_11_03_000000_add_marketing_tracking.php',
            '2026_11_11_140000_add_ghl_sync_failure_to_bookings.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->actingAs(User::factory()->create(['role' => 'administrador']));
        $category = RoomCategory::create(['name' => 'GO', 'display_order' => 1]);
        $this->room = Room::create(['room_category_id' => $category->id, 'name' => 'GO 101', 'operational_status' => 'activa']);
    }

    private function customerWithRejectedBooking(string $phone): Customer
    {
        $customer = Customer::create(['name' => 'Bastián Fuentes', 'phone_e164' => $phone, 'ghl_contact_id' => 'c-viejo']);
        Booking::create([
            'code' => 'HH-X-1', 'customer_id' => $customer->id, 'room_id' => $this->room->id,
            'starts_at' => Carbon::parse('2026-11-09 21:55'), 'ends_at' => Carbon::parse('2026-11-10 00:55'),
            'duration_minutes' => 180, 'guests_count' => 2, 'booking_status' => 'FINALIZADA', 'payment_status' => 'PAGADA',
            'price_original' => 36000, 'price_final' => 31000, 'origin' => 'presencial',
            'ghl_sync_failed_at' => now(), 'ghl_sync_attempts' => 3, 'ghl_sync_error' => 'GHL 400: Invalid country calling code',
        ]);

        return $customer;
    }

    private function save(Customer $customer, array $extra = [])
    {
        return $this->put(route('admin.customers.update', $customer), ['name' => $customer->name] + $extra);
    }

    public function test_a_badly_written_phone_can_be_fixed_from_the_ficha_and_its_bookings_are_retried(): void
    {
        $customer = $this->customerWithRejectedBooking('+87121124');

        $this->save($customer, ['phone' => '+56 9 1234 5678'])->assertSessionHasNoErrors();

        $fresh = $customer->fresh();
        $this->assertSame('+56912345678', $fresh->phone_e164);
        $this->assertNull($fresh->ghl_contact_id); // el contacto de GHL era del número viejo
        $booking = Booking::firstOrFail();
        $this->assertNull($booking->ghl_sync_failed_at);
        $this->assertSame(0, (int) $booking->ghl_sync_attempts);
        $this->assertNull($booking->ghl_sync_error);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cliente.telefono_corregir', 'entity_id' => (string) $customer->id]);
        $this->assertSame('+87121124', AuditLog::where('action', 'cliente.telefono_corregir')->firstOrFail()->old_value['phone_e164']);
    }

    public function test_the_fix_must_itself_be_a_valid_number(): void
    {
        $customer = $this->customerWithRejectedBooking('+87121124');

        $this->save($customer, ['phone' => '+5691917003'])->assertSessionHasErrors('phone');

        $this->assertSame('+87121124', $customer->fresh()->phone_e164);
    }

    public function test_an_unknown_phone_can_be_cleared(): void
    {
        $customer = $this->customerWithRejectedBooking('+87121124');

        $this->save($customer, ['phone' => ''])->assertSessionHasNoErrors();

        $this->assertNull($customer->fresh()->phone_e164);
    }

    public function test_a_phone_that_belongs_to_another_customer_is_refused(): void
    {
        Customer::create(['name' => 'Otro', 'phone_e164' => '+56912345678']);
        $customer = $this->customerWithRejectedBooking('+87121124');

        $this->save($customer, ['phone' => '9 1234 5678'])->assertSessionHasErrors('phone');

        $this->assertSame('+87121124', $customer->fresh()->phone_e164);
    }

    public function test_a_valid_phone_is_still_the_identity_and_is_not_edited_here(): void
    {
        $customer = $this->customerWithRejectedBooking('+56987654321');

        $this->save($customer, ['phone' => '+56 9 1111 2222', 'email' => 'nuevo@correo.cl'])->assertSessionHasNoErrors();

        $fresh = $customer->fresh();
        $this->assertSame('+56987654321', $fresh->phone_e164);
        $this->assertSame('nuevo@correo.cl', $fresh->email);
        $this->assertSame('c-viejo', $fresh->ghl_contact_id);
    }

    public function test_the_reception_form_rejects_a_badly_written_phone_and_accepts_a_good_one(): void
    {
        $this->post('/reservar', ['customer_phone' => '+5691917003'])->assertSessionHasErrors('customer_phone');
        $this->assertStringContainsString('Teléfono mal escrito', session('errors')->first('customer_phone'));

        // Con un número bueno, el teléfono ya no es el problema (faltan los demás datos del formulario).
        $this->post('/reservar', ['customer_phone' => '+56 9 1234 5678'])->assertSessionDoesntHaveErrors('customer_phone');
        // Sin teléfono sigue siendo opcional.
        $this->post('/reservar', [])->assertSessionDoesntHaveErrors('customer_phone');
    }

    public function test_the_public_catalog_asks_the_customer_to_recheck_their_whatsapp_number(): void
    {
        $this->post('/catalogo/reservar', ['phone' => '+5691917003'])->assertSessionHasErrors('phone');
        $this->assertStringStartsWith('Revisa tu número de WhatsApp: ', session('errors')->first('phone'));

        $this->post('/catalogo/reservar', ['phone' => '9 1234 5678'])->assertSessionDoesntHaveErrors('phone');
    }
}
