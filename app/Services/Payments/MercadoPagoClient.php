<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API de Mercado Pago (Checkout Pro). Solo hace tres
 * cosas: abrir el cobro de una reserva, leer un pago y devolverlo.
 */
class MercadoPagoClient
{
    private const BASE = 'https://api.mercadopago.com';

    public function enabled(): bool
    {
        return filled(config('services.mercadopago.access_token'));
    }

    private function http(): PendingRequest
    {
        // trim: una clave pegada en Render con un salto de línea al final hacía fallar todos los cobros.
        return Http::withToken(trim((string) config('services.mercadopago.access_token')))->acceptJson()->timeout(15);
    }

    /** Abre el cobro por el saldo de la reserva y devuelve la dirección de pago. */
    public function checkoutUrl(Booking $booking, int $amount): string
    {
        $isHttps = str_starts_with(url('/'), 'https://');
        $back = fn (string $result) => route('catalog.booked', ['token' => $booking->pass_token, 'mp' => $result]);

        $payload = [
            'items' => [[
                'id' => $booking->code,
                'title' => 'Reserva HH Motel '.$booking->code,
                'description' => ($booking->room?->name ?? 'Playroom').' · '.$booking->starts_at->timezone('America/Santiago')->format('d/m/Y H:i'),
                'quantity' => 1,
                'currency_id' => 'CLP',
                'unit_price' => $amount,
            ]],
            'external_reference' => $booking->code,
            'statement_descriptor' => 'HH MOTEL',
            // Solo aprobado o rechazado: nada de pagos "en proceso" que dejen la reserva en el aire.
            'binary_mode' => true,
            'back_urls' => ['success' => $back('ok'), 'pending' => $back('pendiente'), 'failure' => $back('fallo')],
        ];
        if ($isHttps) {
            $payload['auto_return'] = 'approved';
            $payload['notification_url'] = route('webhooks.mercadopago');
        }
        if ($booking->customer?->email) {
            $payload['payer'] = ['email' => $booking->customer->email];
        }
        // El cobro vence junto con la retención de la habitación.
        if ($booking->expires_at && $booking->expires_at->isFuture()) {
            $payload['expires'] = true;
            $payload['expiration_date_to'] = $booking->expires_at->copy()->setTimezone('America/Santiago')->format('Y-m-d\TH:i:s.vP');
        }

        $response = $this->http()->post(self::BASE.'/checkout/preferences', $payload);
        if (! $response->successful() || ! $response->json('init_point')) {
            throw new RuntimeException('Mercado Pago no abrió el cobro (HTTP '.$response->status().').');
        }

        return (string) $response->json('init_point');
    }

    /** Datos reales de un pago, leídos directo de Mercado Pago (nunca se confía en lo que trae el aviso). */
    public function payment(string $id): array
    {
        $response = $this->http()->get(self::BASE.'/v1/payments/'.urlencode($id));
        if (! $response->successful()) {
            throw new RuntimeException('No se pudo leer el pago '.$id.' en Mercado Pago (HTTP '.$response->status().').');
        }

        return $response->json();
    }

    /** Devolución total del pago. */
    /**
     * Devuelve un pago completo o, con $amount, solo esa parte. La clave de
     * idempotencia identifica cada devolución para que un reintento no la repita.
     */
    public function refund(string $paymentId, ?int $amount = null, ?string $idempotencyKey = null): array
    {
        $response = $this->http()->withHeaders(['X-Idempotency-Key' => $idempotencyKey ?? 'hh-refund-'.$paymentId])
            ->post(self::BASE.'/v1/payments/'.urlencode($paymentId).'/refunds', $amount !== null ? ['amount' => $amount] : []);
        if (! $response->successful()) {
            throw new RuntimeException('Mercado Pago no aceptó la devolución: '.($response->json('message') ?: 'HTTP '.$response->status()));
        }

        return $response->json();
    }
}
