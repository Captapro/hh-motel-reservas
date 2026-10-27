<?php

namespace Tests\Feature;

use App\Models\AnalystRun;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Analyst\ClaudeTransport;
use App\Services\Analyst\SalesDataService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Analista de ventas (IA): solo administrador y soporte, los datos que ve son
 * cifras agregadas sin datos personales, y el ciclo de herramientas guarda
 * la respuesta. La API de Claude se reemplaza por una falsa.
 */
class SalesAnalystTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_120001_add_role_to_users_table.php',
            '2026_10_23_210000_allow_soporte_role_on_users.php',
            '2026_08_18_120002_create_room_categories_table.php',
            '2026_09_21_160029_add_sales_tip_to_room_categories_table.php',
            '2026_10_04_130000_add_max_capacity_to_room_categories.php',
            '2026_08_18_120003_create_rooms_table.php',
            '2026_09_16_040000_add_wing_to_rooms.php',
            '2026_08_18_120004_create_rate_rules_table.php',
            '2026_09_16_001000_add_extra_hour_price_to_rate_rules.php',
            '2026_08_18_120005_create_rate_rule_windows_table.php',
            '2026_08_18_120006_create_rate_rule_prices_table.php',
            '2026_08_18_120007_create_calendar_overrides_table.php',
            '2026_08_18_120008_create_coupons_table.php',
            '2026_09_10_130000_add_auto_apply_offers_to_coupons.php',
            '2026_09_11_090001_add_min_age_to_coupons_table.php',
            '2026_09_16_000000_add_included_extra_guests_to_coupons.php',
            '2026_09_21_232142_add_image_url_to_coupons_table.php',
            '2026_08_18_120010_create_customers_table.php',
            '2026_08_18_120011_create_bookings_table.php',
            '2026_08_18_120012_create_coupon_redemptions_table.php',
            '2026_08_19_180001_add_checkin_checkout_to_bookings_table.php',
            '2026_10_26_030000_add_origin_to_bookings_table.php',
            '2026_10_26_200000_add_short_stay_to_bookings_table.php',
            '2026_10_26_210000_create_short_stay_settings_table.php',
            '2026_08_18_120014_create_booking_addons_table.php',
            '2026_08_19_090001_create_products_table.php',
            '2026_08_19_090002_add_product_id_to_booking_addons_table.php',
            '2026_08_20_110001_create_combos_table.php',
            '2026_08_20_110002_add_combo_id_to_booking_addons_table.php',
            '2026_09_10_140000_create_upsell_offers_table.php',
            '2026_08_18_120015_create_payment_methods_table.php',
            '2026_08_18_120016_create_payments_table.php',
            '2026_08_18_120018_create_audit_logs_table.php',
            '2026_10_23_200000_create_access_permissions_table.php',
            '2026_10_27_090000_create_analyst_runs_table.php',
            '2026_10_27_100000_create_analyst_settings_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $this->admin = User::factory()->create(['role' => 'administrador']);

        $category = RoomCategory::create(['name' => 'PLUS', 'display_order' => 1]);
        $room = Room::create(['room_category_id' => $category->id, 'name' => 'PLUS 201', 'operational_status' => 'activa']);
        $customer = Customer::create(['name' => 'Cliente Secreto', 'phone_e164' => '+56987654321']);
        $method = PaymentMethod::create(['code' => 'efectivo', 'name' => 'Efectivo', 'is_active' => true]);
        $booking = Booking::create([
            'code' => 'HH-AN-1',
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'starts_at' => Carbon::parse('2026-10-20 22:00'),
            'ends_at' => Carbon::parse('2026-10-21 01:00'),
            'duration_minutes' => 180,
            'guests_count' => 2,
            'booking_status' => 'FINALIZADA',
            'payment_status' => 'PAGADA',
            'origin' => 'catalogo',
            'price_original' => 45000,
            'price_final' => 45000,
        ]);
        Payment::create(['booking_id' => $booking->id, 'payment_method_id' => $method->id, 'amount' => 45000, 'status' => 'aprobado']);
    }

    public function test_sales_data_is_aggregated_and_has_no_personal_data(): void
    {
        $data = app(SalesDataService::class);
        $from = Carbon::parse('2026-10-01', 'America/Santiago')->startOfDay();
        $to = Carbon::parse('2026-10-31', 'America/Santiago')->endOfDay();

        $overview = $data->overview($from, $to);
        $this->assertSame(1, $overview['totales']['reservas']);
        $this->assertSame(45000, $overview['totales']['venta_total']);
        $this->assertSame('PLUS', $overview['por_categoria'][0]['grupo']);
        $this->assertSame(1, $overview['reservas_por_dia_y_hora']['Martes']['22']);

        $occupancy = $data->occupancy($from, $to);
        $this->assertSame(3, $occupancy['por_categoria'][0]['horas_ocupadas']);

        $json = json_encode([$overview, $occupancy, $data->pricingConfig(), $data->monthlyTrend()], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Cliente Secreto', $json);
        $this->assertStringNotContainsString('987654321', $json);
        $this->assertStringNotContainsString('HH-AN-1', $json);
    }

    public function test_only_admin_and_support_can_open_it(): void
    {
        $host = User::factory()->create(['role' => 'recepcion']);
        $this->actingAs($host)->get(route('admin.analyst.index'))->assertForbidden();

        $support = User::factory()->create(['role' => 'soporte']);
        $this->actingAs($support)->get(route('admin.analyst.index'))->assertOk()->assertSee('Analista de ventas');
    }

    public function test_without_api_key_the_page_warns_and_questions_are_rejected(): void
    {
        config(['services.anthropic.key' => '']);

        $this->actingAs($this->admin)->get(route('admin.analyst.index'))->assertOk()->assertSee('ANTHROPIC_API_KEY');
        $this->actingAs($this->admin)->postJson(route('admin.analyst.ask'), ['question' => 'Hola', 'thread' => (string) Str::uuid()])
            ->assertStatus(422);
        $this->assertSame(0, AnalystRun::count());
    }

    public function test_question_runs_the_tools_and_saves_the_answer(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $fake = new class extends ClaudeTransport
        {
            public array $calls = [];

            public function send(string $system, array $messages, array $tools): object
            {
                $this->calls[] = $messages;
                $usage = (object) ['inputTokens' => 100, 'outputTokens' => 50];
                if (count($this->calls) === 1) {
                    return (object) ['stopReason' => 'tool_use', 'usage' => $usage, 'content' => [
                        (object) ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'resumen_ventas', 'input' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31']],
                        (object) ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'configuracion_precios', 'input' => []],
                    ]];
                }

                return (object) ['stopReason' => 'end_turn', 'usage' => $usage, 'content' => [
                    (object) ['type' => 'text', 'text' => "## Recomendación\n\n| Categoría | Venta |\n|---|---|\n| PLUS | \$45.000 |"],
                ]];
            }
        };
        $this->app->instance(ClaudeTransport::class, $fake);
        $thread = (string) Str::uuid();

        $response = $this->actingAs($this->admin)->postJson(route('admin.analyst.ask'), ['question' => '¿Cómo vamos?', 'thread' => $thread]);

        $response->assertOk()->assertJsonPath('status', 'listo');
        $this->assertStringContainsString('<table>', $response->json('answer_html'));
        $run = AnalystRun::first();
        $this->assertSame(['Revisando ventas (2026-10-01 a 2026-10-31)', 'Revisando tarifas, ofertas y upsells'], $run->steps);
        $this->assertSame([200, 100], [$run->input_tokens, $run->output_tokens]);

        // El resultado de la herramienta vuelve al modelo con la venta real.
        $toolResults = $fake->calls[1][2]['content'];
        $this->assertSame('tu_1', $toolResults[0]['toolUseID']);
        $this->assertStringContainsString('"venta_total":45000', $toolResults[0]['content']);

        // Una pregunta de seguimiento lleva la conversación anterior como contexto.
        $fake->calls = [];
        $this->actingAs($this->admin)->postJson(route('admin.analyst.ask'), ['question' => '¿Y el mes pasado?', 'thread' => $thread])->assertOk();
        $this->assertSame('¿Cómo vamos?', $fake->calls[0][0]['content']);
        $this->assertSame('¿Y el mes pasado?', $fake->calls[0][2]['content']);

        $this->actingAs($this->admin)->get(route('admin.analyst.index', ['conversacion' => $thread]))
            ->assertOk()->assertSee('¿Y el mes pasado?')->assertSee('Recomendación');
    }

    public function test_business_context_goes_into_the_prompt_and_price_changes_are_listed(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $fake = new class extends ClaudeTransport
        {
            public string $system = '';

            public function send(string $system, array $messages, array $tools): object
            {
                $this->system = $system;

                return (object) ['stopReason' => 'end_turn', 'usage' => (object) [], 'content' => [(object) ['type' => 'text', 'text' => 'Listo.']]];
            }
        };
        $this->app->instance(ClaudeTransport::class, $fake);

        $this->actingAs($this->admin)->put(route('admin.analyst.context'), ['business_context' => 'Meta: subir tardes de semana. Competencia: Motel X PLUS 3 h $42.000'])
            ->assertRedirect();
        $this->actingAs($this->admin)->postJson(route('admin.analyst.ask'), ['question' => 'Hola', 'thread' => (string) Str::uuid()])->assertOk();
        $this->assertStringContainsString('Competencia: Motel X PLUS 3 h $42.000', $fake->system);
        $this->assertStringContainsString('revenue management', $fake->system);

        $rule = RateRule::create(['name' => 'HOT', 'priority' => 2, 'is_active' => true]);
        AuditLog::record($this->admin->id, 'tarifa.precio_editar', 'RateRulePrice', 7,
            ['rate_rule_id' => $rule->id, 'room_category_id' => RoomCategory::first()->id, 'duration_minutes' => 180, 'price' => 45000],
            ['rate_rule_id' => $rule->id, 'room_category_id' => RoomCategory::first()->id, 'duration_minutes' => 180, 'price' => 48000]);

        $changes = app(SalesDataService::class)->priceChanges(now()->subDay(), now()->addDay());
        $this->assertCount(1, $changes);
        $this->assertSame('HOT', $changes[0]['tarifa']);
        $this->assertSame('PLUS', $changes[0]['categoria']);
        $this->assertSame(['antes' => 45000, 'despues' => 48000], $changes[0]['cambios']['price']);
    }
}
