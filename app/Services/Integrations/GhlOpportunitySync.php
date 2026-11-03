<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Services\Marketing\Attribution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Cada reserva queda como una oportunidad en el embudo "Reservas HH" de GHL,
 * con su monto y el canal que la trajo (fuente), y se mueve de etapa según su
 * estado: así los tableros de GHL muestran reservas, ingresos y conversión.
 *
 * Lo corre app:reservas-expirar en cada pasada, de a pocas reservas: toma las
 * que nunca se subieron o cuyo estado cambió desde la última vez. Con eso
 * también se cargan solas las reservas pasadas desde GHL_OPPORTUNITIES_SINCE y
 * se reintenta lo que haya fallado. Si GHL falla, espera unos minutos.
 */
class GhlOpportunitySync
{
    public const ERROR_KEY = 'ghl:opps:error';

    public const LAST_RUN_KEY = 'ghl:opps:last';

    private const PAUSE_KEY = 'ghl:opps:pause';

    private const PIPELINE_CACHE_KEY = 'ghl:opps:pipeline';

    /** Etapa del embudo (por nombre, como se crearon en GHL) y estado de la oportunidad. */
    public const STAGES = [
        'reservo' => ['name' => 'Reservó (sin pagar)', 'status' => 'open'],
        'pago' => ['name' => 'Pagó', 'status' => 'open'],
        'estadia' => ['name' => 'En estadía', 'status' => 'open'],
        'vino' => ['name' => 'Vino', 'status' => 'won'],
        'cancelo' => ['name' => 'Canceló / venció', 'status' => 'lost'],
        'no_vino' => ['name' => 'No vino', 'status' => 'lost'],
    ];

    public function __construct(private readonly GhlClient $client) {}

    public static function stageFor(Booking $booking): string
    {
        return match ($booking->booking_status) {
            'CONFIRMADA' => 'pago',
            'CHECK_IN' => 'estadia',
            'FINALIZADA' => 'vino',
            'CANCELADA', 'EXPIRADA' => 'cancelo',
            'NO_SHOW' => 'no_vino',
            default => 'reservo',
        };
    }

    /** Reservas que faltan por subir o mover de etapa. */
    public static function pendingQuery()
    {
        return Booking::query()
            ->where('created_at', '>=', config('services.ghl.opportunities_since'))
            ->whereHas('customer', fn ($q) => $q->whereNotNull('phone_e164')->where('phone_e164', '!=', ''))
            ->where(fn ($q) => $q->whereNull('ghl_opportunity_state')->orWhereColumn('ghl_opportunity_state', '!=', 'booking_status'));
    }

    /** Sube o actualiza hasta $limit reservas. Devuelve cuántas quedaron al día. */
    public function syncPending(int $limit = 8): int
    {
        if (! $this->client->isConfigured() || Cache::has(self::PAUSE_KEY)) {
            return 0;
        }

        $done = 0;
        try {
            $bookings = self::pendingQuery()->with(['customer', 'room.category', 'trackingData'])->orderBy('id')->limit($limit)->get();
            if ($bookings->isEmpty()) {
                return 0;
            }

            $pipeline = $this->pipeline();
            foreach ($bookings as $booking) {
                $this->syncOne($booking, $pipeline);
                $done++;
            }
            Cache::forget(self::ERROR_KEY);
        } catch (Throwable $e) {
            // Se reintenta en unos minutos; sin martillar a GHL si el problema sigue.
            Cache::put(self::PAUSE_KEY, true, now()->addMinutes(10));
            Cache::put(self::ERROR_KEY, ['at' => now()->toIso8601String(), 'message' => mb_substr($e->getMessage(), 0, 300)], now()->addDays(7));
            report($e);
        }
        Cache::put(self::LAST_RUN_KEY, ['at' => now()->toIso8601String(), 'synced' => $done], now()->addDays(7));

        return $done;
    }

    public function syncOne(Booking $booking, array $pipeline): void
    {
        $customer = $booking->customer;
        $contactId = $customer->ghl_contact_id;
        if (! $contactId) {
            $contactId = $this->client->upsertContact($customer->phone_e164, $customer->name, $customer->email);
            $customer->forceFill(['ghl_contact_id' => $contactId, 'ghl_synced_at' => now()])->saveQuietly();
        }

        $stage = self::STAGES[self::stageFor($booking)];
        $data = [
            'pipelineId' => $pipeline['id'],
            'pipelineStageId' => $pipeline['stages'][self::stageFor($booking)],
            'status' => $stage['status'],
            'name' => $this->name($booking),
            'monetaryValue' => $booking->price_final + $booking->addonsTotal(),
            'source' => Attribution::sourceOf($booking),
        ];

        $id = $booking->ghl_opportunity_id;
        $created = false;
        if (! $id) {
            $id = $this->client->createOpportunity(['contactId' => $contactId, ...$data]);
            $created = $id !== null;
            // GHL permite una oportunidad por contacto: el cliente que vuelve
            // sigue en la suya, que pasa a mostrar su reserva más reciente.
            $id ??= $this->client->findOpportunity($contactId, $pipeline['id'])
                ?? throw new RuntimeException('GHL no dejó crear la oportunidad de '.$booking->code.' y el cliente no tiene otra en el embudo.');
        }
        if (! $created && ! $this->newerBookingShares($booking, $id)) {
            $this->client->updateOpportunity($id, $data);
        }

        $booking->forceFill(['ghl_opportunity_id' => $id, 'ghl_opportunity_state' => $booking->booking_status])->saveQuietly();
    }

    /** Una reserva más nueva del mismo cliente ya usa (o usará) esa oportunidad: la vieja no la pisa. */
    private function newerBookingShares(Booking $booking, string $opportunityId): bool
    {
        return Booking::where('customer_id', $booking->customer_id)->where('id', '>', $booking->id)
            ->where(fn ($q) => $q->where('ghl_opportunity_id', $opportunityId)
                ->orWhere(fn ($q) => $q->whereNull('ghl_opportunity_id')->where('created_at', '>=', config('services.ghl.opportunities_since'))))
            ->exists();
    }

    private function name(Booking $booking): string
    {
        return Str::limit(sprintf(
            '%s · %s · %s',
            $booking->customer->name,
            $booking->room?->category?->name ?? 'Playroom',
            $booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('D MMM HH:mm'),
        ), 190, '');
    }

    /**
     * ID del embudo y de cada etapa, buscados por nombre (se guardan una hora).
     *
     * @return array{id: string, stages: array<string, string>}
     */
    public function pipeline(): array
    {
        return Cache::remember(self::PIPELINE_CACHE_KEY, now()->addHour(), function () {
            $wanted = mb_strtolower(trim((string) config('services.ghl.pipeline_name')));
            $pipeline = collect($this->client->pipelines())->first(fn ($p) => mb_strtolower(trim($p['name'] ?? '')) === $wanted);
            if (! $pipeline) {
                throw new RuntimeException('No existe en GHL el embudo "'.config('services.ghl.pipeline_name').'".');
            }

            $byName = collect($pipeline['stages'] ?? [])->mapWithKeys(fn ($s) => [mb_strtolower(trim($s['name'])) => $s['id']]);
            $stages = [];
            foreach (self::STAGES as $key => $stage) {
                $stages[$key] = $byName[mb_strtolower($stage['name'])]
                    ?? throw new RuntimeException('Falta la etapa "'.$stage['name'].'" en el embudo de GHL.');
            }

            return ['id' => $pipeline['id'], 'stages' => $stages];
        });
    }
}
