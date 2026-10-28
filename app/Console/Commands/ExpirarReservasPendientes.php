<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Integrations\GhlAbandonedBookings;
use Illuminate\Console\Command;

class ExpirarReservasPendientes extends Command
{
    protected $signature = 'app:reservas-expirar';

    protected $description = 'Libera las reservas pendientes de pago cuya retención venció y avisa a GHL para rescatarlas';

    public function handle(GhlAbandonedBookings $abandoned): int
    {
        $n = Booking::releaseExpired();
        if ($n > 0) {
            $this->info("{$n} reserva(s) expirada(s).");
        }
        $abandoned->run();

        return self::SUCCESS;
    }
}
