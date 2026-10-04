<?php

namespace App\Services\Booking;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El "pagado" de una reserva nunca es un campo suelto — se recalcula siempre
 * a partir de la suma de pagos aprobados en payments (sección 17 del
 * documento de arquitectura). Registrar un pago y recalcular el estado
 * financiero ocurre siempre junto, en la misma transacción.
 */
class PaymentService
{
    public function register(Booking $booking, PaymentMethod $method, int $amount, ?string $externalId, ?string $notes, ?int $registeredBy, ?string $voucherNumber = null, ?string $receiptNumber = null, ?string $requestToken = null): Payment
    {
        return DB::transaction(function () use ($booking, $method, $amount, $externalId, $notes, $registeredBy, $voucherNumber, $receiptNumber, $requestToken) {
            // Bloqueo de fila: sin esto, dos pagos enviados casi al mismo
            // tiempo (doble clic, dos pestañas) podrían leer el mismo saldo
            // pendiente y pasar los dos la validación, sumando más de lo que
            // realmente se debe. El saldo se vuelve a comprobar acá adentro,
            // con la fila bloqueada, no solo en el controller.
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();

            if ($requestToken !== null) {
                $existing = $locked->payments()->where('request_token', $requestToken)->first();
                if ($existing) {
                    if ((int) $existing->amount !== $amount || (int) $existing->payment_method_id !== (int) $method->id
                        || $existing->receipt_number !== $receiptNumber || $existing->voucher_number !== $voucherNumber) {
                        throw ValidationException::withMessages(['amount' => 'Este formulario ya registró otro pago. Abre un formulario nuevo para una operación distinta.']);
                    }

                    return $existing;
                }
            }

            if ($amount > $locked->balanceDue()) {
                throw new PaymentExceedsBalanceException(
                    'El saldo pendiente cambió justo antes de registrar este pago (probablemente por otro pago simultáneo) — el monto ya no corresponde. Revisa la reserva y volvé a intentar.'
                );
            }

            // external_id tiene un índice único (payments_external_id_unique)
            // pensado para que una notificación repetida de Mercado Pago
            // nunca duplique un pago -- pero el mismo campo es "Referencia /
            // N° de operación" en el formulario manual, que cualquiera puede
            // tipear. Repetir esa referencia desde otro formulario (otro
            // request_token, así que el chequeo de reenvío de arriba no lo
            // detecta) chocaba contra ese índice con un QueryException sin
            // capturar -- un 500 en vez de un error entendible.
            if ($externalId !== null && Payment::where('external_id', $externalId)->exists()) {
                throw ValidationException::withMessages(['external_id' => 'Esa referencia / N° de operación ya está usada en otro pago. Si es una operación distinta, dejá el campo vacío o escribí una referencia distinta.']);
            }

            try {
                $payment = Payment::create([
                    ...($requestToken !== null ? ['request_token' => $requestToken] : []),
                    'booking_id' => $booking->id,
                    'payment_method_id' => $method->id,
                    'amount' => $amount,
                    'status' => 'aprobado', // registro manual: el staff ya verificó el dinero/transferencia
                    'external_id' => $externalId,
                    'voucher_number' => $voucherNumber,
                    'receipt_number' => $receiptNumber,
                    'registered_by' => $registeredBy,
                    'notes' => $notes,
                ]);
            } catch (QueryException $e) {
                // Red de seguridad ante la carrera: dos formularios con la
                // misma referencia enviados casi al mismo tiempo pueden
                // pasar el chequeo de arriba los dos antes de que cualquiera
                // inserte -- acá lo agarra el índice único mismo.
                if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'payments_external_id_unique')) {
                    throw ValidationException::withMessages(['external_id' => 'Esa referencia / N° de operación ya está usada en otro pago. Si es una operación distinta, dejá el campo vacío o escribí una referencia distinta.']);
                }
                throw $e;
            }

            $this->recalculateStatus($booking->fresh());

            AuditLog::record($registeredBy, 'pago.registrar', 'Payment', $payment->id, null, $payment->toArray());

            return $payment;
        });
    }

    public function recalculateStatus(Booking $booking): void
    {
        $paid = $booking->paidAmount();
        $totalDue = $booking->price_final + $booking->addonsTotal();

        $paymentStatus = match (true) {
            $paid <= 0 => 'NO_PAGADA',
            $paid < $totalDue => 'PARCIALMENTE_PAGADA',
            default => 'PAGADA',
        };

        $bookingStatus = $booking->booking_status;
        if ($paid > 0 && in_array($booking->booking_status, ['PENDIENTE_PAGO', 'RETENIDA', 'BORRADOR'], true)) {
            $bookingStatus = 'CONFIRMADA';
        }

        $booking->update([
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
        ]);
    }
}
