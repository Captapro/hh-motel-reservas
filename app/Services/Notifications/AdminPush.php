<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

use function Illuminate\Support\defer;

/** Notificaciones al celular de los administradores. Se envían después de responder y nunca frenan una venta. */
class AdminPush
{
    /** Una venta presencial crea reserva y pago juntos: ese pago no se avisa por separado. */
    private const SAME_SALE_SECONDS = 120;

    public static function newBooking(Booking $booking): void
    {
        self::send('Nueva reserva · '.$booking->originLabel(), sprintf(
            '%s · %s · $%s',
            $booking->room?->name,
            $booking->starts_at->timezone('America/Santiago')->format('d/m H:i'),
            number_format((int) $booking->price_final, 0, ',', '.'),
        ));
    }

    public static function newPayment(Payment $payment): void
    {
        $booking = $payment->booking;
        if ($payment->amount <= 0 || ($booking && $booking->created_at->diffInSeconds(now()) < self::SAME_SALE_SECONDS)) {
            return;
        }

        self::send('Venta registrada', '$'.number_format((int) $payment->amount, 0, ',', '.').($booking?->room ? ' · '.$booking->room->name : ''));
    }

    public static function send(string $title, string $body): void
    {
        if (! config('services.webpush.public_key') || ! config('services.webpush.private_key')) {
            return;
        }

        defer(function () use ($title, $body) {
            try {
                self::deliver(json_encode(['title' => $title, 'body' => $body, 'url' => '/panel']));
            } catch (Throwable $e) {
                Log::warning('No se pudo enviar la notificación push: '.$e->getMessage());
            }
        });
    }

    private static function deliver(string $payload): void
    {
        $push = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]);

        foreach (PushSubscription::all() as $device) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $device->endpoint,
                'publicKey' => $device->public_key,
                'authToken' => $device->auth_token,
            ]), $payload);
        }

        foreach ($push->flush() as $report) {
            // El celular se dio de baja o desinstaló la app.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
