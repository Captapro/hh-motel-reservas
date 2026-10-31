<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\IntegrationOutbox;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Conecta una reserva del sistema con el contacto de GHL del cliente: lo
 * crea/actualiza, le pone un tag con la fecha de esta reserva (sacándole
 * cualquier tag de "última reserva" anterior -- así el tag queda siempre
 * único y refleja lo más reciente, en vez de acumular uno por reserva), y le
 * agrega una nota con el detalle -- el tag/campos actualizados por API no
 * mueven la columna "Última actividad" de GHL, pero una nota sí cuenta.
 *
 * Se dispara al CREAR la reserva (no al pagar) para captar al cliente lo
 * antes posible para remarketing, aunque después cancele o no pague -- es
 * el criterio por defecto hasta que se defina algo más granular.
 *
 * integration_outbox queda como bitácora/reintento: se intenta mandar al
 * toque, y si falla (o si en algún momento se agrega un worker real) el
 * registro 'pendiente'/'fallido' es lo que ese worker retomaría. Hoy no hay
 * un worker de cola corriendo en producción -- esto es el intento inmediato
 * más esa bitácora, no una cola de verdad todavía.
 */
class GhlBookingSync
{
    public const TAG_PREFIX = 'Reservó:';

    /** Marca al cliente con una reserva viva (para segmentar en GHL). */
    public const ACTIVE_STAY_TAG = 'hh_estadia_activa';

    /**
     * El flujo de bienvenida de WhatsApp en GHL solo corre si el contacto NO
     * tiene este tag (GHL admite un solo tag en ese filtro). El flujo lo pone
     * al enviarla, y acá se pone también al reservar: así no le llega la
     * bienvenida a quien coordina su llegada o ya está adentro.
     */
    public const WELCOME_TAG = 'hh_bienvenida_wa';

    /** Estados en que la reserva ya terminó: la estadía deja de estar activa. */
    public const CLOSED_STATUSES = ['FINALIZADA', 'CANCELADA', 'EXPIRADA', 'NO_SHOW'];

    public function __construct(private readonly GhlClient $client) {}

    /**
     * Nunca lanza -- una reserva ya confirmada en la base de datos no puede
     * terminar mostrándole un error al cliente/recepción solo porque GHL (o
     * la propia bitácora en integration_outbox) tuvo un problema.
     */
    public function sync(Booking $booking): void
    {
        try {
            $this->attemptSync($booking);
        } catch (Throwable $e) {
            Log::warning('GHL sync falló para la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }

    private function attemptSync(Booking $booking): void
    {
        $booking->loadMissing(['customer', 'room.category']);
        $customer = $booking->customer;

        $outbox = IntegrationOutbox::create([
            'type' => 'GHL',
            'booking_id' => $booking->id,
            'payload' => [
                'customer_id' => $customer->id,
                'phone' => $customer->phone_e164,
                'booking_code' => $booking->code,
            ],
            'status' => 'procesando',
        ]);

        try {
            if (! $this->client->isConfigured()) {
                throw new \RuntimeException('GHL no está configurado (falta GHL_PRIVATE_TOKEN o GHL_LOCATION_ID).');
            }

            $contactId = $this->client->upsertContact($customer->phone_e164, $customer->name, $customer->email);
            $tag = self::TAG_PREFIX.$booking->starts_at->timezone('America/Santiago')->toDateString();
            $this->client->replaceTagWithPrefix($contactId, self::TAG_PREFIX, $tag);
            $this->client->addTags($contactId, [self::ACTIVE_STAY_TAG, self::WELCOME_TAG]);
            (new GhlMarketingConsent($this->client))->apply($customer, $contactId);
            $this->client->addNote($contactId, sprintf(
                'Reserva %s: %s (%s) · %s · $%s',
                $booking->code,
                $booking->room->name,
                $booking->room->category->name,
                $booking->starts_at->timezone('America/Santiago')->locale('es')->isoFormat('D [de] MMMM, HH:mm'),
                number_format($booking->price_final, 0, ',', '.')
            ));

            if (! $customer->ghl_contact_id) {
                $customer->update(['ghl_contact_id' => $contactId, 'ghl_synced_at' => now()]);
            } else {
                $customer->update(['ghl_synced_at' => now()]);
            }

            $outbox->update(['status' => 'enviado', 'processed_at' => now()]);
        } catch (Throwable $e) {
            $outbox->update([
                'status' => 'fallido',
                'attempts' => $outbox->attempts + 1,
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Agenda sync() para después de responder: al reservar desde el catálogo
     * el cliente pasa al pago sin esperar las llamadas a GHL. Sin GHL
     * configurado no hace nada.
     */
    public static function syncLater(Booking $booking): void
    {
        if (! app(GhlClient::class)->isConfigured()) {
            return;
        }

        dispatch(fn () => app(self::class)->sync($booking))->afterResponse();
    }

    /**
     * Agenda releaseStay() para después de responder: se llama desde el modelo
     * cuando la reserva se cierra, y no debe demorar el checkout ni el barrido
     * de vencidas esperando a GHL. Sin GHL configurado no hace nada.
     */
    public static function releaseStayLater(Booking $booking): void
    {
        if (! app(GhlClient::class)->isConfigured()) {
            return;
        }

        dispatch(fn () => app(self::class)->releaseStay($booking))->afterResponse();
    }

    /**
     * Al cerrarse la estadía le saca al contacto el tag de estadía activa y el
     * de bienvenida enviada, para que si vuelve a escribir reciba la
     * bienvenida de nuevo. Si el cliente tiene otra reserva viva, no toca nada.
     * Nunca lanza, igual que sync().
     */
    public function releaseStay(Booking $booking): void
    {
        try {
            $customer = $booking->customer;
            if (! $customer || ! $this->client->isConfigured()) {
                return;
            }

            $hasOtherActive = Booking::where('customer_id', $customer->id)
                ->where('id', '!=', $booking->id)
                ->whereNotIn('booking_status', self::CLOSED_STATUSES)
                ->exists();
            if ($hasOtherActive) {
                return;
            }

            $contactId = $customer->ghl_contact_id ?: $this->client->findContactIdByPhone($customer->phone_e164);
            if ($contactId) {
                $this->client->removeTags($contactId, [self::ACTIVE_STAY_TAG, self::WELCOME_TAG]);
            }
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo liberar la estadía de la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }
}
