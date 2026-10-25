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
}
