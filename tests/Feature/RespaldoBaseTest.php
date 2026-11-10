<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Respaldo diario a Google Drive: solo actúa si está configurado, y un fallo se nota
 * (código de salida distinto de cero) en vez de pasar en silencio.
 */
class RespaldoBaseTest extends TestCase
{
    public function test_scheduled_run_does_nothing_when_drive_is_not_configured(): void
    {
        config(['services.backup.drive_url' => null]);
        Process::fake();
        Http::fake();

        $this->artisan('app:respaldo-base', ['--programado' => true])->assertSuccessful();

        Process::assertNothingRan();
        Http::assertNothingSent();
    }

    public function test_it_refuses_to_run_on_a_database_that_is_not_postgres(): void
    {
        config(['services.backup.drive_url' => 'https://script.google.com/macros/s/x/exec', 'services.backup.drive_token' => 'clave']);
        Process::fake();
        Http::fake();

        // La base de pruebas es SQLite: no hay pg_dump que valga, y debe fallar a la vista.
        $this->artisan('app:respaldo-base')
            ->expectsOutputToContain('solo funciona con PostgreSQL')
            ->assertFailed();

        Http::assertNothingSent();
    }
}
