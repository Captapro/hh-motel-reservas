<?php

namespace Tests\Feature;

use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
use App\Services\Analyst\HistoricalDataService;
use App\Services\Historical\HistoricalSalesImporter;
use Carbon\Carbon;
use Tests\TestCase;

class HistoricalSalesTest extends TestCase
{
    private string $file = __DIR__.'/../fixtures/planilla_ventas_ejemplo.csv';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        (require database_path('migrations/2026_10_28_140000_create_historical_sales_tables.php'))->up();
    }

    public function test_dry_run_reads_the_sheet_without_saving_and_totals_match(): void
    {
        $summary = app(HistoricalSalesImporter::class)->import($this->file, 'planilla-2026-10', 2026, true);

        $this->assertSame(0, HistoricalSale::count());
        $this->assertSame(5, $summary['lineas']);
        $this->assertSame(['ALOJAMIENTO' => 3, 'EXTRAS' => 1, 'EXTRA CORE' => 1], collect($summary['por_tipo'])->map->lineas->all());
        // Principal: alojamientos 36.000 + 31.000 + 50.000 y la hora extra 20.000.
        $this->assertSame(36000 + 31000 + 20000 + 50000, $summary['venta_principal']);
        $this->assertSame(2000, $summary['venta_extras']);
        $this->assertSame(1, $summary['tipo_inferido']);
        $this->assertSame('2026-10-01', $summary['desde']);
        $this->assertSame('2026-10-03', $summary['hasta']);
        // La fila de resumen sin fecha se ignora.
        $this->assertSame(1, $summary['filas_ignoradas']);
    }

    public function test_import_saves_sales_and_guests_and_is_repeatable(): void
    {
        $importer = app(HistoricalSalesImporter::class);
        $importer->import($this->file, 'planilla-2026-10', 2026);
        $importer->import($this->file, 'planilla-2026-10', 2026); // otra vez: no duplica

        $this->assertSame(5, HistoricalSale::count());
        $first = HistoricalSale::where('source_row', 1)->firstOrFail();
        $this->assertSame('2026-10-01', $first->sold_on->toDateString());
        $this->assertSame('PLUS', $first->category);
        $this->assertSame(185, $first->duration_minutes);
        $this->assertSame('tarjeta', $first->payment_method);
        $this->assertSame('MAÑANA', $first->shift);
        $this->assertSame(36000, $first->total());
        $this->assertCount(2, $first->guests); // titular + acompañante
        $this->assertTrue($first->guests->firstWhere('is_holder', true)->is_holder);

        $discounted = HistoricalSale::where('source_row', 3)->firstOrFail();
        $this->assertSame('Estudiantes $5000 OFF', $discounted->discount_note);
        $this->assertSame('R0001', $discounted->reservation_ref);

        // RUT con puntos se normaliza; uno con dígito mal queda marcado; un pasaporte es "otro".
        $this->assertSame('11111111-1', HistoricalGuest::where('name', 'Ana Prueba Uno')->first()->document);
        $this->assertSame('rut_invalido', HistoricalGuest::where('name', 'Carla Prueba Tres')->first()->document_kind);
        $this->assertSame('otro', HistoricalGuest::where('name', 'Diego Prueba Cuatro')->first()->document_kind);
        $this->assertSame(5, HistoricalGuest::count());
    }

    public function test_analyst_summary_is_aggregate_only_and_counts_people_who_came_back(): void
    {
        app(HistoricalSalesImporter::class)->import($this->file, 'planilla-2026-10', 2026);
        $summary = app(HistoricalDataService::class)->summary(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame(3, $summary['totales']['estadias']);
        $this->assertSame(36000 + 31000 + 50000, $summary['totales']['venta_alojamiento']);
        // La hora extra va aparte de la persona adicional, otros servicios y los extras (productos).
        $this->assertSame(20000, $summary['totales']['venta_hora_extra']);
        $this->assertSame(0, $summary['totales']['venta_persona_adicional']);
        $this->assertSame(0, $summary['totales']['venta_otros_extra_core']);
        $this->assertSame(2000, $summary['totales']['venta_extras_consumos']);
        $this->assertSame(['desde' => '2026-10-01', 'hasta' => '2026-10-03'], $summary['cobertura_cargada']);
        $this->assertSame(1, $summary['descuentos']['estadias_con_descuento']);
        // Ana (RUT válido) estuvo 2 veces; Beto (RUT válido) 1 vez; Carla tiene RUT inválido y Diego pasaporte: no se cuentan.
        $this->assertSame(2, $summary['personas']['personas_distintas_en_el_periodo']);
        $this->assertSame(1, $summary['personas']['que_volvieron']);
        $this->assertSame(50.0, $summary['personas']['porcentaje_que_volvio']);

        // Nunca sale una persona: ni nombres ni RUT en lo que se le da al modelo.
        $json = json_encode($summary, JSON_UNESCAPED_UNICODE);
        foreach (['Ana', 'Beto', 'Carla', 'Diego', '11111111', '22222222', 'P1234567'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_customer_behavior_uses_all_loaded_sheets_without_exposing_people(): void
    {
        $service = app(HistoricalDataService::class);
        $this->assertNull($service->behavior());

        app(HistoricalSalesImporter::class)->import($this->file, 'planilla-2026-10', 2026);
        $b = $service->behavior();

        // Ana (2 estadías) y Beto (1) tienen RUT válido; Carla e invitados con documento raro no cuentan.
        $this->assertSame(2, $b['people']);
        $this->assertSame(1, $b['returned']);
        $this->assertSame(50, $b['returned_pct']);
        $this->assertSame(['desde' => '2026-10-01', 'hasta' => '2026-10-03'], $b['coverage']);
        $this->assertSame(2, $b['months'][0]['people']);
        $this->assertSame(2, $b['months'][0]['new']); // con un solo mes cargado todas son «nuevas»
        $this->assertCount(7, $b['weekdays']);

        $json = json_encode($b, JSON_UNESCAPED_UNICODE);
        foreach (['Ana', 'Beto', 'Carla', 'Diego', '11111111', '22222222', 'P1234567'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_each_line_counts_in_its_own_concept(): void
    {
        $concept = fn (string $type, ?string $detail = null) => (new HistoricalSale(['sale_type' => $type, 'detail' => $detail]))->concept();

        $this->assertSame('alojamiento', $concept('ALOJAMIENTO'));
        $this->assertSame('hora_extra', $concept('EXTRA CORE', 'HORA EXTRA'));
        $this->assertSame('persona_adicional', $concept('EXTRA CORE', 'Goloso'));
        $this->assertSame('servicios', $concept('EXTRA CORE', 'LAVANDERIA'));
        // Planillas antiguas: un extra core sin detalle es un Goloso (persona adicional).
        $this->assertSame('persona_adicional', $concept('EXTRA CORE'));
        $this->assertSame('persona_adicional', $concept('EXTRA CORE', 'Persona adicional'));
        $this->assertSame('extras', $concept('EXTRAS', 'PRESERVATIVO'));
        $this->assertSame('fraccionado', $concept('PAGO FRACCIONADO'));
    }
}
