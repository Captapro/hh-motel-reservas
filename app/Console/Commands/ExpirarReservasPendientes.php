<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\InspectionPhotoStore;
use App\Services\Integrations\GhlAbandonedBookings;
use App\Services\Integrations\GhlReviewRequests;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExpirarReservasPendientes extends Command
{
    protected $signature = 'app:reservas-expirar';

    protected $description = 'Libera las reservas pendientes de pago cuya retención venció y avisa a GHL para rescatarlas y para pedir la opinión tras el check-out';

    public function handle(GhlAbandonedBookings $abandoned, GhlReviewRequests $reviews, InspectionPhotoStore $photos): int
    {
        $n = Booking::releaseExpired();
        if ($n > 0) {
            $this->info("{$n} reserva(s) expirada(s).");
        }
        $abandoned->run();
        $reviews->sendDue();
        // Fotos de inspección de más de 30 días: se revisa una vez por hora.
        if (Cache::add('inspection-photos-prune', true, 3600)) {
            $photos->prune();
        }

        return self::SUCCESS;
    }
}
