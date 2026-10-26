<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Pagos en línea recibidos (Mercado Pago) que el tablero muestra en su
 * banner de avisos, junto a los pases escaneados. Vive en el caché unos
 * minutos: es un aviso, no un registro (el registro es el pago mismo).
 */
class PaymentAlerts
{
    private const KEY = 'payment_alerts';

    private const TTL_SECONDS = 900;

    public static function record(int $paymentId, string $code, int $amount, bool $review = false): void
    {
        $alerts = self::recent();
        $alerts[$paymentId] = ['code' => $code, 'amount' => $amount, 'review' => $review, 'at' => now()->timestamp];
        Cache::put(self::KEY, $alerts, self::TTL_SECONDS);
    }

    /** @return array<int, array{code: string, amount: int, review: bool, at: int}> */
    public static function recent(): array
    {
        $limit = now()->timestamp - self::TTL_SECONDS;

        return array_filter((array) Cache::get(self::KEY, []), fn ($a) => ($a['at'] ?? 0) >= $limit);
    }
}
