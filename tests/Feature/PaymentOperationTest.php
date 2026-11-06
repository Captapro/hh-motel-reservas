<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Payments\MercadoPagoHistory;
use Carbon\Carbon;
use Tests\TestCase;

class PaymentOperationTest extends TestCase
{
    private function payment(string $code, ?string $externalId = null): Payment
    {
        $payment = new Payment(['external_id' => $externalId]);

        return $payment->setRelation('paymentMethod', new PaymentMethod(['code' => $code, 'name' => ucfirst($code)]));
    }

    public function test_the_method_says_if_mercado_pago_was_online_or_the_machine(): void
    {
        $this->assertSame('Mercado Pago · Online', $this->payment('mercado_pago')->methodLabel());
        $this->assertSame('Mercado Pago · Máquina · Débito', $this->payment('debito')->methodLabel());
        $this->assertSame('Mercado Pago · Máquina · Crédito', $this->payment('credito')->methodLabel());
        $this->assertSame('Transferencia', $this->payment('transferencia')->methodLabel());
        $this->assertFalse($this->payment('efectivo')->isMercadoPago());
    }

    public function test_a_charge_noted_the_next_day_is_compensated_but_other_differences_are_not(): void
    {
        $day = fn (string $date, int $machine, int $system) => ['day' => Carbon::parse($date), 'machine' => $machine, 'system' => $system];

        $days = MercadoPagoHistory::markCompensated(collect([
            $day('2026-11-06', 0, 4500),
            $day('2026-11-05', 255500, 251000),
            $day('2026-11-03', 162000, 167000),
            $day('2026-11-01', 752300, 743500),
        ]))->keyBy(fn (array $d) => $d['day']->toDateString());

        $this->assertSame('2026-11-05', $days['2026-11-06']['compensated_with']->toDateString());
        $this->assertSame('2026-11-06', $days['2026-11-05']['compensated_with']->toDateString());
        $this->assertNull($days['2026-11-03']['compensated_with']);
        $this->assertNull($days['2026-11-01']['compensated_with']);
    }

    public function test_price_splits_the_playroom_from_the_extra_guests(): void
    {
        $room = (new Room)->setRelation('category', new RoomCategory(['base_capacity' => 2]));
        $booking = (new Booking(['price_original' => 49500, 'extra_guests_fee' => 17500, 'guests_count' => 3]))->setRelation('room', $room);

        $this->assertSame(32000, $booking->basePrice());
        $this->assertSame(1, $booking->extraGuests());
    }

    public function test_only_online_payments_have_a_mercado_pago_operation_number(): void
    {
        $this->assertSame('123456', $this->payment('mercado_pago', 'MP-123456')->mercadoPagoOperation());
        $this->assertSame('123456', $this->payment('mercado_pago', 'MP-123456-RESTO-7')->mercadoPagoOperation());
        $this->assertNull($this->payment('transferencia', 'REC-261106-ABCD')->mercadoPagoOperation());
    }
}
