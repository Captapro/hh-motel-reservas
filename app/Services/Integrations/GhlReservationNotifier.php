<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa a GHL (por webhook de entrada) cuando una reserva queda confirmada, para
 * que su flujo mande el correo y el WhatsApp "Reserva confirmada" al cliente. Nunca lanza:
 * un problema con GHL no puede romper el registro del pago.
 *
 *  - Solo se avisa la primera vez que un pago deja la reserva confirmada (ese
 *    correo ya incluye lo pagado y el saldo). Los pagos siguientes, como el
 *    cobro de consumos, no mandan nada al cliente.
 *  - Los ingresos presenciales no mandan nada: el cliente está en recepción.
 */
class GhlReservationNotifier
{
    public function paymentRegistered(Booking $booking, Payment $payment, string $statusBefore): void
    {
        try {
            $booking->loadMissing(['customer', 'room.category']);
            // Sin correo ni teléfono no hay a quién avisar; con uno de los dos, el flujo
            // de GHL manda el correo y/o el WhatsApp según lo que tenga el cliente.
            if ($booking->origin === 'presencial' || (! filled($booking->customer?->email) && ! filled($booking->customer?->phone_e164))) {
                return;
            }

            if ($statusBefore !== 'CONFIRMADA' && $booking->booking_status === 'CONFIRMADA') {
                $this->send('confirmada', config('services.ghl.webhook_confirmada'), $booking, $payment);
            }
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo preparar el aviso de la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Recepción canceló la reserva y marcó "Avisar al cliente". Los ingresos presenciales no
     * avisan (el cliente está en recepción); el motivo interno nunca se manda.
     */
    public function cancelled(Booking $booking): void
    {
        $this->notifyChange('cancelada', config('services.ghl.webhook_cancelada'), $booking);
    }

    /**
     * Recepción cambió el Playroom, la hora o la duración y marcó "Avisar al cliente".
     *
     * @param  array{habitacion: string, fecha_hora: string}  $before  cómo estaba antes
     */
    public function modified(Booking $booking, array $before): void
    {
        $this->notifyChange('modificada', config('services.ghl.webhook_modificada'), $booking, [
            'habitacion_anterior' => $before['habitacion'],
            'fecha_hora_anterior' => $before['fecha_hora'],
        ]);
    }

    /** Cómo estaba la reserva, para decirle al cliente qué cambió. */
    public function snapshot(Booking $booking): array
    {
        $p = $this->payload($booking);

        return ['habitacion' => $p['habitacion'], 'fecha_hora' => $p['fecha_hora'].' · '.$p['duracion']];
    }

    private function notifyChange(string $kind, ?string $url, Booking $booking, array $extra = []): void
    {
        try {
            $booking->loadMissing('customer');
            if ($booking->origin === 'presencial' || ! filled($booking->customer?->phone_e164) || ! filled($url)) {
                return;
            }
            $response = Http::timeout(6)->acceptJson()->post($url, $this->payload($booking) + $extra);
            if (! $response->successful()) {
                Log::warning('GHL: el webhook "'.$kind.'" respondió '.$response->status().' para la reserva '.$booking->code);
            }
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo avisar "'.$kind.'" de la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }

    /** ¿Tiene sentido ofrecer la casilla "Avisar al cliente"? Hay a quién avisar, no es presencial y el aviso está configurado. */
    public function canNotify(Booking $booking): bool
    {
        $booking->loadMissing('customer');

        return $booking->origin !== 'presencial' && filled($booking->customer?->phone_e164)
            && (filled(config('services.ghl.webhook_cancelada')) || filled(config('services.ghl.webhook_modificada')));
    }

    /** @return array<string, string> */
    public function payload(Booking $booking, ?Payment $payment = null): array
    {
        $booking->loadMissing(['customer', 'room.category']);
        $tz = 'America/Santiago';
        $money = fn (int $n) => '$'.number_format($n, 0, ',', '.');
        $date = ucfirst($booking->starts_at->timezone($tz)->locale('es')->isoFormat('dddd D [de] MMMM'));
        $minutes = (int) $booking->duration_minutes;
        $total = (int) $booking->price_final + $booking->addonsTotal();
        $customer = $booking->customer;
        $parts = preg_split('/\s+/', trim((string) $customer->name), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'email' => (string) $customer->email,
            'phone' => (string) $customer->phone_e164,
            'codigo' => $booking->code,
            'habitacion' => $booking->room->name,
            'categoria' => $booking->room->category->name,
            'fecha' => $date,
            'hora_entrada' => $booking->starts_at->timezone($tz)->format('H:i'),
            // Junto, para la plantilla de WhatsApp (Meta pide pocas variables por texto).
            'fecha_hora' => $date.' a las '.$booking->starts_at->timezone($tz)->format('H:i'),
            'hora_salida' => $booking->ends_at->timezone($tz)->format('H:i'),
            'duracion' => $minutes >= 60 ? rtrim(rtrim(number_format($minutes / 60, 1, ',', ''), '0'), ',').' h' : $minutes.' min',
            'personas' => (string) $booking->guests_count,
            'total' => $money($total),
            'pagado' => $money($booking->paidAmount()),
            'saldo' => $money($booking->balanceDue()),
            'monto' => $payment ? $money((int) $payment->amount) : '',
            'medio_pago' => $payment?->paymentMethod?->name ?? '',
            'token' => (string) $booking->pass_token,
        ];
    }

    private function send(string $kind, ?string $url, Booking $booking, Payment $payment): void
    {
        if (! filled($url)) {
            return;
        }

        $response = Http::timeout(6)->acceptJson()->post($url, $this->payload($booking, $payment));
        if (! $response->successful()) {
            Log::warning('GHL: el webhook "'.$kind.'" respondió '.$response->status().' para la reserva '.$booking->code);
        }
    }
}
