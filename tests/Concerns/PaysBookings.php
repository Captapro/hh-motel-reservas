<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;

/**
 * Sin la habitación pagada no hay check-in: los tests que hacen check-in
 * pagan primero lo que falte de la habitación.
 */
trait PaysBookings
{
    protected function payRoom(Booking $booking): static
    {
        $due = $booking->fresh()->roomBalanceDue();
        if ($due > 0) {
            // Pago aprobado directo, sin PaymentService: así el estado de la
            // reserva queda como lo armó cada test.
            $method = PaymentMethod::firstOrCreate(['code' => 'test-pago'], ['name' => 'Pago de prueba']);
            Payment::create(['booking_id' => $booking->id, 'payment_method_id' => $method->id, 'amount' => $due, 'status' => 'aprobado']);
        }

        return $this;
    }
}
