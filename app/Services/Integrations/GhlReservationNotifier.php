<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa a GHL (por webhook de entrada) cuando una reserva queda confirmada, para
 * que su flujo mande el correo "Reserva confirmada" al cliente. Nunca lanza:
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
            if ($booking->origin === 'presencial' || ! filled($booking->customer?->email)) {
                return;
            }

            if ($statusBefore !== 'CONFIRMADA' && $booking->booking_status === 'CONFIRMADA') {
                $this->send('confirmada', config('services.ghl.webhook_confirmada'), $booking, $payment);
            }
        } catch (Throwable $e) {
            Log::warning('GHL: no se pudo preparar el aviso de la reserva '.$booking->code, ['error' => $e->getMessage()]);
        }
    }

    /** @return array<string, string> */
    public function payload(Booking $booking, ?Payment $payment = null): array
    {
        $booking->loadMissing(['customer', 'room.category']);
        $tz = 'America/Santiago';
        $money = fn (int $n) => '$'.number_format($n, 0, ',', '.');
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
            'fecha' => ucfirst($booking->starts_at->timezone($tz)->locale('es')->isoFormat('dddd D [de] MMMM')),
            'hora_entrada' => $booking->starts_at->timezone($tz)->format('H:i'),
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
