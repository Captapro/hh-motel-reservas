<?php

namespace App\Console\Commands;

use App\Models\HistoricalSale;
use App\Services\Historical\HistoricalSalesImporter;
use Illuminate\Console\Command;

class ImportarHistorico extends Command
{
    protected $signature = 'app:importar-historico {archivo : CSV exportado de la planilla de ventas} {--fuente= : Nombre de la planilla, p. ej. planilla-2026-10} {--anio= : Año de las fechas que no lo traen (por defecto, el actual)} {--simular : Solo muestra el resumen, no guarda nada}';

    protected $description = 'Carga una planilla de ventas anterior al sistema en las ventas históricas (solo para el analista)';

    public function handle(HistoricalSalesImporter $importer): int
    {
        $file = $this->argument('archivo');
        if (! is_file($file)) {
            $this->error("No existe el archivo {$file}");

            return self::FAILURE;
        }

        $source = (string) ($this->option('fuente') ?: pathinfo($file, PATHINFO_FILENAME));
        $dry = (bool) $this->option('simular');
        $summary = $importer->import($file, $source, (int) ($this->option('anio') ?: now()->year), $dry);

        $this->info(($dry ? 'SIMULACIÓN — no se guardó nada. ' : 'Cargado. ')."Planilla: {$source}");
        $this->line("Líneas de venta: {$summary['lineas']}  ·  Huéspedes: {$summary['huespedes']}  ·  Filas ignoradas: {$summary['filas_ignoradas']}  ·  Sin tipo de venta, tomadas como alojamiento: {$summary['tipo_inferido']}");
        $this->line("Período: {$summary['desde']} a {$summary['hasta']}");
        $this->table(['Tipo', 'Líneas', 'Principal $', 'Extras $'], collect($summary['por_tipo'])->map(fn ($v, $k) => [$k, $v['lineas'], number_format($v['principal'], 0, ',', '.'), number_format($v['extras'], 0, ',', '.')])->values()->all());
        $this->line('Venta total: $'.number_format($summary['venta_principal'] + $summary['venta_extras'], 0, ',', '.').' (principal $'.number_format($summary['venta_principal'], 0, ',', '.').' + extras $'.number_format($summary['venta_extras'], 0, ',', '.').')');
        $this->line('Documentos: '.collect($summary['documentos'])->map(fn ($n, $k) => "{$k}={$n}")->implode('  '));

        if (! $dry) {
            $this->line('En la base ahora: '.HistoricalSale::count().' líneas históricas.');
        }

        return self::SUCCESS;
    }
}
