<?php

namespace App\Services\Analyst;

use App\Models\AnalystRun;

/**
 * Lanza el análisis en un proceso aparte: una respuesta puede tardar uno o
 * dos minutos y Apache corta las solicitudes a los 60 s. La página consulta
 * el estado cada pocos segundos. En los tests corre en el mismo proceso.
 */
class AnalystLauncher
{
    public function __construct(private SalesAnalyst $analyst) {}

    public function launch(AnalystRun $run): void
    {
        if (app()->runningUnitTests()) {
            $this->analyst->run($run);

            return;
        }

        $cmd = sprintf(
            '%s %s app:analista-correr %d > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINDIR.'/php'),
            escapeshellarg(base_path('artisan')),
            $run->id,
        );
        exec($cmd);
    }
}
