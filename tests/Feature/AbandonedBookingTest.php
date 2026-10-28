<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\RoomCategory;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rescate de reservas web sin pagar (GhlAbandonedBookings): el aviso de
 * "pago por vencer" y el de "reserva liberada" llegan a GHL una sola vez,
 * solo para reservas del catálogo, y el de liberada no se manda si ya no
 * tiene sentido (horario pasado, pagó o volvió a reservar).
 */
class AbandonedBookingTest extends TestCase
{
    private Room $room;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->travelTo(Carbon::parse('2026-10-01 20:00'));
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_08_18_120005_create_rate_rule_windows_table.php',
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_09_28_120000_add_pass_token_to_bookings_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
            '2026_10_27_130000_add_choice_items_to_combos.php',
            '2026_10_27_140000_add_show_in_catalog_to_combos.php',
            '2026_10_28_120000_add_abandon_notices_to_bookings.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $category = RoomCategory::create(['name' => 'PLUS', 'is_active' => true]);
        $this->room = Room::create(['name' => 'PLUS 101', 'room_category_id' => $category->id, 'operational_status' => 'activa']);
        $this->customer = Customer::create(['name' => 'Ana Pérez', 'phone_e164' => '+56911111111', 'email' => 'ana@example.test']);
        config([
            'services.ghl.webhook_pago_pendiente' => 'https://ghl.test/pago-pendiente',
            'services.ghl.webhook_reserva_vencida' => 'https://ghl.test/reserva-vencida',
        ]);
        Http::fake(['ghl.test/*' => Http::response(['ok' => true])]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'code' => 'HH-'.Str::upper(Str::random(6)), 'pass_token' => Str::random(40),
            'customer_id' => $this->customer->id, 'room_id' => $this->room->id, 'origin' => 'catalogo',
            'starts_at' => Carbon::parse('2026-10-01 22:00'), 'ends_at' => Carbon::parse('2026-10-02 01:00'),
            'duration_minutes' => 180, 'guests_count' => 2,
            'booking_status' => 'PENDIENTE_PAGO', 'payment_status' => 'PENDIENTE',
            'price_original' => 30000, 'price_final' => 30000,
            'expires_at' => now()->addMinutes(Booking::HOLD_MINUTES),
        ], $overrides));
    }

    private function sentTo(string $hook): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), $hook))->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    public function test_reminder_goes_once_when_five_minutes_are_left(): void
    {
        $booking = $this->booking();

        $this->artisan('app:reservas-expirar');
        $this->assertCount(0, $this->sentTo('pago-pendiente'), 'Recién creada: todavía no se avisa.');

        $this->travel(5)->minutes();
        $this->artisan('app:reservas-expirar');
        $this->artisan('app:reservas-expirar');

        $sent = $this->sentTo('pago-pendiente');
        $this->assertCount(1, $sent);
        $this->assertSame($booking->code, $sent[0]['codigo']);
        $this->assertSame('20:10', $sent[0]['hora_limite']);
        $this->assertStringContainsString($booking->pass_token, $sent[0]['link_pago']);
        $this->assertSame('PLUS 101', $sent[0]['habitacion']);
    }

    public function test_expired_booking_is_offered_again_once_with_the_same_slot_prefilled(): void
    {
        $booking = $this->booking();
        $this->travel(11)->minutes();
        $this->artisan('app:reservas-expirar');
        $this->artisan('app:reservas-expirar');

        $this->assertSame('EXPIRADA', $booking->fresh()->booking_status);
        $sent = $this->sentTo('reserva-vencida');
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('fecha=2026-10-01', $sent[0]['link_reservar']);
        $this->assertStringContainsString('hora=22%3A00', $sent[0]['link_reservar']);
        $this->assertStringContainsString('duracion=180', $sent[0]['link_reservar']);
        $this->assertStringEndsWith('?'.$sent[0]['reservar_query'], $sent[0]['link_reservar']);

        // El enlace abre el formulario con todo puesto.
        $this->get($sent[0]['link_reservar'])->assertOk()
            ->assertSee('value="2026-10-01"', false)->assertSee('id="time_hour" value="22"', false);
    }

    public function test_no_recovery_message_when_it_no_longer_makes_sense(): void
    {
        // Volvió a reservar después.
        $this->booking();
        $this->travel(1)->minutes();
        $this->booking(['booking_status' => 'CONFIRMADA', 'expires_at' => null]);
        // Reserva de recepción (no es del catálogo).
        $this->booking(['origin' => 'recepcion', 'customer_id' => Customer::create(['name' => 'Otro', 'phone_e164' => '+56922222222'])->id]);
        // El horario ya pasa antes de que venza.
        $this->booking(['starts_at' => now()->addMinutes(5), 'customer_id' => Customer::create(['name' => 'Tarde', 'phone_e164' => '+56933333333'])->id]);
        // Pagó por fuera (transferencia) pero la retención venció igual.
        $paid = $this->booking(['customer_id' => Customer::create(['name' => 'Pagó', 'phone_e164' => '+56944444444'])->id]);
        Payment::create(['booking_id' => $paid->id, 'payment_method_id' => PaymentMethod::create(['code' => 'transferencia', 'name' => 'Transferencia'])->id, 'amount' => 10000, 'status' => 'aprobado']);

        $this->travel(15)->minutes();
        $this->artisan('app:reservas-expirar');

        $this->assertCount(0, $this->sentTo('reserva-vencida'));
    }

    public function test_nothing_is_sent_without_webhooks_configured(): void
    {
        config(['services.ghl.webhook_pago_pendiente' => null, 'services.ghl.webhook_reserva_vencida' => null]);
        $this->booking();
        $this->travel(11)->minutes();
        $this->artisan('app:reservas-expirar');

        Http::assertNothingSent();
    }
}
