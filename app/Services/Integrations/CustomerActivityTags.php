<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tramo de cada cliente según los días desde su última visita (estadía
 * FINALIZADA), reflejado en GHL con una sola etiqueta a la vez:
 *
 *  - hh_pausa: vino hace menos de 15 días (fuera de la publicidad; los flujos
 *    de GHL lo pasan al público "en pausa" de Meta y, al salir, a "activos").
 *  - hh_inactivo_30 / 45 / 60 / 90: lleva 30, 45, 60 o 90+ días sin venir
 *    (campañas de "te extrañamos" y públicos por tramo).
 *  - entre 15 y 29 días no lleva ninguna.
 *
 * Solo llama a GHL cuando el cliente cambia de tramo (customers.activity_tag).
 */
class CustomerActivityTags
{
    public const PAUSE_DAYS = 15;

    /** Etiqueta => desde cuántos días sin venir aplica (de mayor a menor). */
    public const INACTIVE = ['hh_inactivo_90' => 90, 'hh_inactivo_60' => 60, 'hh_inactivo_45' => 45, 'hh_inactivo_30' => 30];

    public const PAUSE = 'hh_pausa';

    /** Tope de cambios por pasada, para no demorar el barrido; lo que quede sigue en la próxima. */
    private const MAX_CHANGES_PER_RUN = 60;

    public function __construct(private readonly GhlClient $client) {}

    public static function allTags(): array
    {
        return array_merge([self::PAUSE], array_keys(self::INACTIVE));
    }

    public static function tagForDays(int $days): ?string
    {
        if ($days < self::PAUSE_DAYS) {
            return self::PAUSE;
        }
        foreach (self::INACTIVE as $tag => $from) {
            if ($days >= $from) {
                return $tag;
            }
        }

        return null;
    }

    /** @return Collection<int, \Carbon\Carbon> customer_id => fecha de su última visita */
    public function lastVisits(): Collection
    {
        return Booking::where('booking_status', 'FINALIZADA')
            ->get(['customer_id', 'checked_out_at', 'ends_at'])
            ->groupBy('customer_id')
            ->map(fn (Collection $rows) => $rows->map(fn (Booking $b) => $b->checked_out_at ?? $b->ends_at)->filter()->max())
            ->filter();
    }

    /** Pone en GHL la etiqueta de tramo de quien cambió. Devuelve cuántos actualizó. Nunca lanza. */
    public function sync(): int
    {
        if (! Schema::hasColumn('customers', 'activity_tag')) {
            return 0;
        }

        $last = $this->lastVisits();
        $changed = 0;
        Customer::whereIn('id', $last->keys())->whereNotNull('phone_e164')->get()->each(function (Customer $customer) use ($last, &$changed) {
            if ($changed >= self::MAX_CHANGES_PER_RUN) {
                return false;
            }
            $tag = self::tagForDays((int) $last[$customer->id]->diffInDays(now()));
            if ($tag === $customer->activity_tag) {
                return null;
            }
            if ($this->apply($customer, $tag)) {
                $customer->forceFill(['activity_tag' => $tag])->saveQuietly();
                $changed++;
            }

            return null;
        });

        return $changed;
    }

    private function apply(Customer $customer, ?string $tag): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        try {
            $contactId = $customer->ghl_contact_id ?: $this->client->findContactIdByPhone($customer->phone_e164);
            if (! $contactId) {
                return false;
            }
            if ($tag) {
                $this->client->addTags($contactId, [$tag]);
            }
            $this->client->removeTags($contactId, array_values(array_diff(self::allTags(), [$tag])));

            return true;
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo actualizar el tramo de actividad del cliente '.$customer->id, ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Para el panel: cuántos clientes hay en cada tramo y quiénes son (los que más tiempo llevan primero).
     *
     * @return array<string, array{label: string, customers: Collection}>
     */
    public function report(): array
    {
        $last = $this->lastVisits();
        $customers = Customer::whereIn('id', $last->keys())->get()->keyBy('id');
        $labels = [self::PAUSE => 'Vinieron hace menos de 15 días (en pausa)', 'hh_inactivo_30' => '30 a 44 días sin venir', 'hh_inactivo_45' => '45 a 59 días sin venir', 'hh_inactivo_60' => '60 a 89 días sin venir', 'hh_inactivo_90' => '90 días o más sin venir'];

        $rows = $last->map(fn ($date, $id) => ['customer' => $customers[$id] ?? null, 'days' => (int) $date->diffInDays(now()), 'last' => $date])
            ->filter(fn ($r) => $r['customer'] !== null)
            ->sortByDesc('days');

        return collect($labels)->map(fn ($label, $tag) => [
            'label' => $label,
            'customers' => $rows->filter(fn ($r) => self::tagForDays($r['days']) === $tag)->values(),
        ])->all();
    }
}
