<?php

namespace App\Console\Commands;

use App\Models\AnalystRun;
use App\Services\Analyst\SalesAnalyst;
use Illuminate\Console\Command;

class CorrerAnalista extends Command
{
    protected $signature = 'app:analista-correr {run}';

    protected $description = 'Responde una pregunta del analista de ventas (IA) en segundo plano';

    public function handle(SalesAnalyst $analyst): int
    {
        set_time_limit(0);
        $run = AnalystRun::find($this->argument('run'));
        if (! $run || $run->status !== 'pendiente') {
            return self::SUCCESS;
        }
        $analyst->run($run);

        return self::SUCCESS;
    }
}
