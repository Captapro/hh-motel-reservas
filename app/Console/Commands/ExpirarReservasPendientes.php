<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\CouponProofs;
use App\Services\Booking\InspectionPhotoStore;
use App\Services\Integrations\CustomerActivityTags;
use App\Services\Integrations\GhlAbandonedBookings;
use App\Services\Integrations\GhlOpportunitySync;
use App\Services\Integrations\GhlReviewRequests;
use App\Services\Payments\MercadoPagoReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExpirarReservasPendientes extends Command
{
    protected $signature = 'app:reservas-expirar';

    protected $description = 'Libera las reservas pendientes de pago cuya retención venció y avisa a GHL para rescatarlas y para pedir la opinión tras el check-out';

    /** Cache con la hora de la última vuelta y el último error de cada paso (se ve en Admin → Integración GHL). */
    public const LAST_RUN_KEY = 'jobs:reservas-expirar:last-run';

    public const ERROR_KEY_PREFIX = 'jobs:reservas-expirar:error:';

    public const STEPS = ['mercado_pago', 'expirar', 'rescate', 'opiniones', 'fotos', 'actividad', 'embudo_ghl'];

    public function handle(GhlAbandonedBookings $abandoned, GhlReviewRequests $reviews, InspectionPhotoStore $photos, CouponProofs $proofs, CustomerActivityTags $activity, MercadoPagoReconciler $mercadoPago, GhlOpportunitySync $opportunities): int
    {
        // Cada paso va por separado: si uno falla, los demás igual corren (antes un
        // error al principio dejaba sin pedir las opiniones, sin dejar rastro).
        $this->step('mercado_pago', function () use ($mercadoPago) {
            // Antes de vencer, se le pregunta a Mercado Pago si alguna ya está pagada.
            $mercadoPago->checkExpiring();
        });
        $this->step('expirar', function () {
            $n = Booking::releaseExpired();
            if ($n > 0) {
                $this->info("{$n} reserva(s) expirada(s).");
            }
        });
        $this->step('rescate', fn () => $abandoned->run());
        $this->step('opiniones', fn () => $reviews->sendDue());
        // Fotos de inspección y de documentos de cupones de más de 30 días: se revisa una vez por hora.
        if (Cache::add('inspection-photos-prune', true, 3600)) {
            $this->step('fotos', function () use ($photos, $proofs) {
                $photos->prune();
                $proofs->prune();
            });
        }
        // Tramos de actividad de los clientes en GHL (pausa / 30 / 45 / 60 / 90 días): cada 10 minutos.
        if (Cache::add('customer-activity-sync', true, 600)) {
            $this->step('actividad', fn () => $activity->sync());
        }
        // Reservas al embudo de GHL (nuevas y cambios de estado), de a pocas por pasada.
        $this->step('embudo_ghl', fn () => $opportunities->syncPending());

        Cache::put(self::LAST_RUN_KEY, now()->toIso8601String(), now()->addDays(2));

        return self::SUCCESS;
    }

    private function step(string $name, callable $run): void
    {
        try {
            $run();
            Cache::forget(self::ERROR_KEY_PREFIX.$name);
        } catch (Throwable $e) {
            report($e);
            Cache::put(self::ERROR_KEY_PREFIX.$name, ['at' => now()->toIso8601String(), 'message' => $e->getMessage()], now()->addDays(2));
            $this->error("[{$name}] ".$e->getMessage());
        }
    }
}
