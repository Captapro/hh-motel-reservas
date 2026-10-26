<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Services\Booking\PaymentService;
use App\Services\Integrations\GhlReservationNotifier;
use App\Support\PaymentAlerts;
use Illuminate\Support\Facades\DB;

/**
 * Traduce un pago de Mercado Pago a nuestro registro de pagos. Es seguro
 * llamarlo muchas veces con el mismo pago: la referencia "MP-{id}" es única.
 */
class MercadoPagoPayments
{
    public function __construct(private PaymentService $payments) {}

    /** @param array<string, mixed> $mp respuesta de GET /v1/payments/{id} */
    public function record(array $mp): string
    {
        $mpId = (string) ($mp['id'] ?? '');
        $code = (string) ($mp['external_reference'] ?? '');
        $status = (string) ($mp['status'] ?? '');
        $booking = $code !== '' ? Booking::where('code', $code)->first() : null;

        if ($mpId === '' || ! $booking) {
            return 'ignorado';
        }

        $externalId = 'MP-'.$mpId;
        $existing = Payment::where('external_id', $externalId)->first();

        // Devuelto o contracargado desde el panel de Mercado Pago.
        if (in_array($status, ['refunded', 'charged_back'], true)) {
            return $existing ? $this->markRefunded($existing, 'Devuelto desde Mercado Pago ('.$status.')') : 'ignorado';
        }

        if ($status !== 'approved' || $existing) {
            return $existing ? 'ya_registrado' : 'ignorado';
        }

        return DB::transaction(function () use ($booking, $mp, $externalId) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();
            $amount = (int) round((float) ($mp['transaction_amount'] ?? 0));
            $wasClosed = in_array($locked->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW'], true);
            $overpaid = $amount > $locked->balanceDue();

            $method = PaymentMethod::firstOrCreate(
                ['code' => 'mercado_pago'],
                ['name' => 'Mercado Pago', 'requires_external_id' => true, 'is_active' => true],
            );

            // El dinero ya entró: se registra siempre, aunque la reserva ya no esté vigente.
            $payment = Payment::create([
                'booking_id' => $locked->id,
                'payment_method_id' => $method->id,
                'amount' => $amount,
                'status' => 'aprobado',
                'external_id' => $externalId,
                'raw_payload' => ['id' => $mp['id'] ?? null, 'status' => $mp['status'] ?? null, 'payment_type_id' => $mp['payment_type_id'] ?? null, 'date_approved' => $mp['date_approved'] ?? null],
                'notes' => 'Pago en línea (Mercado Pago)',
            ]);
            $statusBefore = (string) $locked->booking_status;
            $this->payments->recalculateStatus($locked->fresh());
            DB::afterCommit(fn () => app(GhlReservationNotifier::class)->paymentRegistered($locked->fresh(), $payment, $statusBefore));

            if ($wasClosed || $overpaid) {
                $aviso = $wasClosed
                    ? '⚠ Pago de Mercado Pago recibido con la reserva '.strtolower($locked->booking_status).': revisa si corresponde reactivarla o devolver el dinero.'
                    : '⚠ Pago de Mercado Pago por más que el saldo: revisa si corresponde devolver la diferencia.';
                $locked->update(['notes' => trim(($locked->notes ? $locked->notes."\n" : '').$aviso)]);
            }

            PaymentAlerts::record($payment->id, $locked->code, $amount, $wasClosed || $overpaid);
            AuditLog::record(null, 'pago.mercadopago', 'Payment', $payment->id, null, $payment->only(['booking_id', 'amount', 'external_id']) + ['revisar' => $wasClosed || $overpaid]);

            return $wasClosed || $overpaid ? 'registrado_revisar' : 'registrado';
        });
    }

    /** Marca un pago como devuelto y deja el registro de la devolución. */
    public function markRefunded(Payment $payment, string $reason, ?int $userId = null): string
    {
        if ($payment->status !== 'aprobado') {
            return 'ya_registrado';
        }

        DB::transaction(function () use ($payment, $reason, $userId) {
            $payment->update(['status' => 'reembolsado']);
            Refund::create(['payment_id' => $payment->id, 'amount' => $payment->amount, 'reason' => $reason, 'status' => 'procesado', 'processed_by' => $userId]);
            $this->payments->recalculateStatus($payment->booking()->first());
            AuditLog::record($userId, 'pago.devolver', 'Payment', $payment->id, ['status' => 'aprobado'], ['status' => 'reembolsado', 'motivo' => $reason]);
        });

        return 'devuelto';
    }
}
