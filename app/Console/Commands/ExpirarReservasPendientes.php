<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class ExpirarReservasPendientes extends Command
{
    protected $signature = 'app:reservas-expirar';

    protected $description = 'Libera las reservas pendientes de pago cuya retención venció';

    public function handle(): int
    {
        $n = Booking::releaseExpired();
        if ($n > 0) {
            $this->info("{$n} reserva(s) expirada(s).");
        }

        return self::SUCCESS;
    }
}
