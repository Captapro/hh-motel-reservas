<?php

namespace App\Services\Integrations;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Permiso de publicidad del cliente reflejado en GHL con un tag: solo quien
 * aceptó recibir promociones lleva MARKETING_YES, y los flujos de publicidad
 * (correo y WhatsApp) filtran por ese tag. Quien no aceptó o se dio de baja
 * lleva MARKETING_NO y queda fuera solo. Los avisos de su reserva
 * (confirmación, pago pendiente) no dependen de esto y le siguen llegando.
 */
class GhlMarketingConsent
{
    public const MARKETING_YES = 'hh_marketing_si';

    public const MARKETING_NO = 'hh_marketing_no';

    public function __construct(private readonly GhlClient $client) {}

    /** Pone el tag que corresponde y saca el contrario. Lanza si GHL falla. */
    public function apply(Customer $customer, string $contactId): void
    {
        [$add, $remove] = $customer->marketing_opt_in
            ? [self::MARKETING_YES, self::MARKETING_NO]
            : [self::MARKETING_NO, self::MARKETING_YES];

        $this->client->addTags($contactId, [$add]);
        $this->client->removeTags($contactId, [$remove]);
    }

    /** Igual que apply(), buscando el contacto si hace falta. Nunca lanza. */
    public function sync(Customer $customer): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        try {
            $contactId = $customer->ghl_contact_id ?: $this->client->findContactIdByPhone($customer->phone_e164);
            if (! $contactId) {
                return false;
            }
            $this->apply($customer, $contactId);

            return true;
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo actualizar el permiso de marketing del cliente '.$customer->id, ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Cambia el permiso guardando cuándo y quién, y lo refleja en GHL. Al
     * darse de baja se apaga siempre; al aceptar, solo si no estaba aceptado.
     */
    public function change(Customer $customer, bool $optIn, ?int $userId, string $via): void
    {
        if ($customer->marketing_opt_in === $optIn) {
            return;
        }

        $customer->update(['marketing_opt_in' => $optIn, 'marketing_opt_in_at' => now()]);
        AuditLog::record($userId, $optIn ? 'cliente.marketing_si' : 'cliente.marketing_no', 'Customer', $customer->id, null, ['via' => $via]);
        $this->sync($customer);
    }
}
