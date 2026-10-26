<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Payments\MercadoPagoClient;
use App\Services\Payments\MercadoPagoPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoController extends Controller
{
    /**
     * "Pagar ahora": se abre el cobro de Mercado Pago recién cuando el cliente
     * lo pide (no al mostrar la reserva), por el saldo pendiente completo.
     */
    public function pay(string $token, MercadoPagoClient $mp): RedirectResponse
    {
        Booking::releaseExpired();
        $booking = Booking::with(['room', 'customer'])->where('pass_token', $token)->firstOrFail();
        $back = redirect()->route('catalog.booked', $booking->pass_token);

        if (! $mp->enabled() || ! in_array($booking->booking_status, ['PENDIENTE_PAGO', 'CONFIRMADA'], true) || $booking->balanceDue() <= 0) {
            return $back;
        }

        try {
            return redirect()->away($mp->checkoutUrl($booking, $booking->balanceDue()));
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago: no se pudo abrir el cobro', ['booking' => $booking->code, 'error' => $e->getMessage()]);

            return $back->with('warning', 'No pudimos abrir el pago en este momento. Intenta de nuevo en unos minutos o paga por transferencia.');
        }
    }

    /**
     * Aviso de Mercado Pago. Se valida la firma, se vuelve a leer el pago
     * directo desde su API (el aviso solo trae el número) y se registra una
     * sola vez. Un error devuelve 500 para que Mercado Pago reintente.
     */
    public function webhook(Request $request, MercadoPagoClient $mp, MercadoPagoPayments $payments): JsonResponse
    {
        $secret = (string) config('services.mercadopago.webhook_secret');
        // PHP convierte el punto de "data.id" en guion bajo al leer la dirección.
        $dataId = (string) ($request->query('data_id') ?? $request->input('data.id') ?? '');
        if ($secret === '' || ! $this->validSignature($request, $secret, $dataId)) {
            return response()->json(['ok' => false], 401);
        }

        $type = (string) ($request->input('type') ?? $request->query('type') ?? '');
        if ($type !== 'payment' || $dataId === '') {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        try {
            $result = $payments->record($mp->payment($dataId));
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: fallo al procesar el aviso', ['payment' => $dataId, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true, 'result' => $result]);
    }

    /** Firma "x-signature": ts=...,v1=HMAC-SHA256("id:{data.id};request-id:{x-request-id};ts:{ts};"). */
    private function validSignature(Request $request, string $secret, string $dataId): bool
    {
        $header = (string) $request->header('x-signature', '');
        parse_str(str_replace(',', '&', $header), $parts);
        $ts = (string) ($parts['ts'] ?? '');
        $v1 = (string) ($parts['v1'] ?? '');
        if ($ts === '' || $v1 === '') {
            return false;
        }

        $id = ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
        $manifest = 'id:'.$id.';request-id:'.$request->header('x-request-id', '').';ts:'.$ts.';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($v1));
    }
}
