<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\StaffNotice;
use App\Models\User;
use App\Services\Marketing\GoogleConversions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Manda a Google Ads (vía la hoja de Apps Script) las ventas pagadas que llegaron por un anuncio
 * y aún no se avisaron. Lo dispara el ciclo del entrypoint cada 10 min; sin configurar no hace nada.
 * Si falla seguido, avisa una vez al día a la campanita de administración.
 */
class EnviarVentasGoogle extends Command
{
    protected $signature = 'app:google-ventas';

    protected $description = 'Avisa a Google Ads las ventas pagadas con clic de anuncio';

    public const ACTION_OK = 'sistema.google_ventas';

    public const ACTION_FAIL = 'sistema.google_ventas_fallo';

    /** Fallos seguidos (cada 10 min) antes de avisar: ~1 hora. */
    private const ALERT_AFTER = 6;

    public function handle(GoogleConversions $google): int
    {
        if (! GoogleConversions::enabled()) {
            return self::SUCCESS;
        }

        try {
            $sent = $google->sendPending();
            if ($sent > 0) {
                $this->record(self::ACTION_OK, ['ventas' => $sent]);
                $this->info("Ventas avisadas a Google: {$sent}");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->failed($e->getMessage());

            return self::FAILURE;
        }
    }

    private function record(string $action, array $data): void
    {
        try {
            AuditLog::record(null, $action, 'Google Ads', 'ventas', null, $data);
        } catch (Throwable) {
        }
    }

    private function failed(string $reason): void
    {
        try {
            $this->record(self::ACTION_FAIL, ['error' => substr($reason, 0, 300)]);
            $lastOk = AuditLog::where('action', self::ACTION_OK)->latest('id')->value('created_at');
            $fails = AuditLog::where('action', self::ACTION_FAIL)
                ->when($lastOk, fn ($q) => $q->where('created_at', '>', $lastOk))
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($fails >= self::ALERT_AFTER && Cache::add('google:ventas:alertado', true, now()->addDay())) {
                $admin = User::where('role', 'administrador')->orderBy('id')->first();
                if ($admin) {
                    StaffNotice::alertAdmins($admin->id, 'No se están avisando las ventas a Google Ads', substr($reason, 0, 200));
                }
            }
        } catch (Throwable) {
        }
    }
}
