<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rescate de reservas del catálogo que no se pagaron. Corre junto con la
 * liberación de retenciones vencidas (app:reservas-expirar, cada 30 s) y
 * avisa a GHL por webhook para que su flujo mande el WhatsApp:
 *
 *  - pago_pendiente: a la reserva le quedan 5 minutos de retención y sigue
 *    sin pagar ("te mantenemos la reserva hasta las HH:MM").
 *  - reserva_vencida: la retención venció sin pago, el horario todavía no
 *    pasa y el cliente no volvió a reservar ("¿te la reservamos de nuevo?").
 *
 * Cada aviso se manda una sola vez (payment_reminder_sent_at /
 * abandon_notified_at). Sin la URL del webhook configurada no se hace nada.
 * Nunca lanza: un problema con GHL no puede frenar la liberación.
 */
class GhlAbandonedBookings
{
    public const REMINDER_MINUTES_LEFT = 5;

    public function __construct(private readonly GhlReservationNotifier $notifier) {}

    public function run(): void
    {
        $this->sendReminders();
        $this->sendAbandonNotices();
    }

    private function sendReminders(): void
    {
        $url = config('services.ghl.webhook_pago_pendiente');
        if (! filled($url)) {
            return;
        }

        Booking::with(['customer', 'room.category'])
            ->where('origin', 'catalogo')
            ->where('booking_status', 'PENDIENTE_PAGO')
            ->whereNull('payment_reminder_sent_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addMinutes(self::REMINDER_MINUTES_LEFT))
            ->get()
            ->each(function (Booking $booking) use ($url) {
                $booking->update(['payment_reminder_sent_at' => now()]);
                if ($booking->balanceDue() > 0 && filled($booking->customer?->phone_e164)) {
                    $this->send('pago_pendiente', $url, $booking);
                }
            });
    }

    private function sendAbandonNotices(): void
    {
        $url = config('services.ghl.webhook_reserva_vencida');
        if (! filled($url)) {
            return;
        }

        Booking::with(['customer', 'room.category'])
            ->where('origin', 'catalogo')
            ->where('booking_status', 'EXPIRADA')
            ->whereNull('abandon_notified_at')
            // Solo las recién vencidas: al configurar el webhook no se le
            // escribe a gente que abandonó hace días.
            ->where('expires_at', '>=', now()->subHours(2))
            ->get()
            ->each(function (Booking $booking) use ($url) {
                $booking->update(['abandon_notified_at' => now()]);
                if ($this->worthRecovering($booking)) {
                    $this->send('reserva_vencida', $url, $booking);
                }
            });
    }

    /** Horario todavía por delante, nada pagado, y el cliente no volvió a reservar después. */
    private function worthRecovering(Booking $booking): bool
    {
        if (! filled($booking->customer?->phone_e164) || $booking->starts_at->isPast() || $booking->paidAmount() > 0) {
            return false;
        }

        return ! Booking::where('customer_id', $booking->customer_id)
            ->where('id', '!=', $booking->id)
            ->where('created_at', '>=', $booking->created_at)
            ->whereNotIn('booking_status', ['EXPIRADA', 'CANCELADA'])
            ->exists();
    }

    private function send(string $kind, string $url, Booking $booking): void
    {
        try {
            $tz = 'America/Santiago';
            $slot = [
                'categoria' => $booking->room->room_category_id,
                'fecha' => $booking->starts_at->timezone($tz)->toDateString(),
                'hora' => $booking->starts_at->timezone($tz)->format('H:i'),
                'duracion' => (int) $booking->duration_minutes,
            ];
            // Los botones de las plantillas de WhatsApp solo dejan variable la
            // parte final del enlace: el token (pase) y la consulta (reservar).
            // La consulta lleva su "?" porque el botón de la plantilla pega el
            // valor justo detrás de /catalogo/reservar.
            $payload = $this->notifier->payload($booking) + [
                'hora_limite' => $booking->expires_at?->timezone($tz)->format('H:i') ?? '',
                'link_pago' => route('catalog.booked', ['token' => $booking->pass_token]),
                'link_reservar' => route('catalog.reserve', $slot),
                'reservar_query' => '?'.http_build_query($slot),
            ];
            $response = Http::timeout(6)->acceptJson()->post($url, $payload);
            if (! $response->successful()) {
                Log::warning('GHL: el webhook "'.$kind.'" respondió '.$response->status().' para la reserva '.$booking->code);
            }
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo mandar el aviso "'.$kind.'" de la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }
}
