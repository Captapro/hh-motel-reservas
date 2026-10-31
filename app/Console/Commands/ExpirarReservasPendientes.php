<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\CouponProofs;
use App\Services\Booking\InspectionPhotoStore;
use App\Services\Integrations\CustomerActivityTags;
use App\Services\Integrations\GhlAbandonedBookings;
use App\Services\Integrations\GhlReviewRequests;
use App\Services\Payments\MercadoPagoReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExpirarReservasPendientes extends Command
{
    protected $signature = 'app:reservas-expirar';

    protected $description = 'Libera las reservas pendientes de pago cuya retención venció y avisa a GHL para rescatarlas y para pedir la opinión tras el check-out';

    public function handle(GhlAbandonedBookings $abandoned, GhlReviewRequests $reviews, InspectionPhotoStore $photos, CouponProofs $proofs, CustomerActivityTags $activity, MercadoPagoReconciler $mercadoPago): int
    {
        // Antes de vencer, se le pregunta a Mercado Pago si alguna ya está pagada.
        $mercadoPago->checkExpiring();
        $n = Booking::releaseExpired();
        if ($n > 0) {
            $this->info("{$n} reserva(s) expirada(s).");
        }
        $abandoned->run();
        $reviews->sendDue();
        // Fotos de inspección y de documentos de cupones de más de 30 días: se revisa una vez por hora.
        if (Cache::add('inspection-photos-prune', true, 3600)) {
            $photos->prune();
            $proofs->prune();
        }
        // Tramos de actividad de los clientes en GHL (pausa / 30 / 45 / 60 / 90 días): cada 10 minutos.
        if (Cache::add('customer-activity-sync', true, 600)) {
            $activity->sync();
        }

        return self::SUCCESS;
    }
}
