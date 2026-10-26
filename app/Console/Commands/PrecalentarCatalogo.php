<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Recorre el catálogo público una vez después de cada arranque para que las fotos externas ya
 * estén reducidas y guardadas cuando llegue el primer cliente (si no, ese cliente las espera).
 */
class PrecalentarCatalogo extends Command
{
    protected $signature = 'app:catalogo-precalentar {--base= : URL base del servidor (por defecto, este mismo contenedor)}';

    protected $description = 'Genera de antemano las fotos reducidas del catálogo público';

    public function handle(): int
    {
        $base = rtrim($this->option('base') ?: 'http://127.0.0.1:'.(getenv('PORT') ?: 10000), '/');

        try {
            $index = Http::timeout(60)->get($base.'/catalogo')->body();
        } catch (\Throwable $e) {
            $this->warn('No se pudo abrir el catálogo: '.$e->getMessage());

            return self::SUCCESS;
        }

        $pages = [$index];
        preg_match_all('#href="[^"]*?(/catalogo/habitacion/\d+)"#', $index, $rooms);
        foreach (array_unique($rooms[1]) as $path) {
            try {
                $pages[] = Http::timeout(60)->get($base.$path)->body();
            } catch (\Throwable $e) {
                // una ficha que falla no frena al resto
            }
        }

        $urls = [];
        foreach ($pages as $html) {
            preg_match_all('#(?:src|href|data-photo)="[^"]*?(/img\?[^"]+)"#', $html, $found);
            foreach ($found[1] as $u) {
                $urls[html_entity_decode($u)] = true;
            }
        }

        $done = 0;
        foreach (array_keys($urls) as $u) {
            try {
                Http::timeout(60)->get($base.$u);
                $done++;
            } catch (\Throwable $e) {
                // se reintenta cuando alguien la pida
            }
        }
        $this->info("{$done} imagen(es) preparada(s).");

        return self::SUCCESS;
    }
}
