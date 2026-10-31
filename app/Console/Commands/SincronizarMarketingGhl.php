<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Integrations\GhlMarketingConsent;
use Illuminate\Console\Command;

class SincronizarMarketingGhl extends Command
{
    protected $signature = 'app:ghl-marketing-sincronizar {--simular : Solo cuenta, no toca GHL}';

    protected $description = 'Pone en GHL el tag de permiso de publicidad (hh_marketing_si / hh_marketing_no) a los clientes que ya existen';

    public function handle(GhlMarketingConsent $consent): int
    {
        $customers = Customer::query()->whereNotNull('phone_e164')->orderBy('id');
        $this->info($customers->count().' clientes; con permiso: '.(clone $customers)->where('marketing_opt_in', true)->count().'.');
        if ($this->option('simular')) {
            return self::SUCCESS;
        }

        $done = 0;
        $missing = 0;
        foreach ($customers->cursor() as $customer) {
            $consent->sync($customer) ? $done++ : $missing++;
            usleep(250000); // GHL limita las llamadas por segundo.
        }
        $this->info("Actualizados en GHL: {$done}. Sin contacto en GHL o con error: {$missing}.");

        return self::SUCCESS;
    }
}
