<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentMethod;
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

    public function test_only_online_payments_have_a_mercado_pago_operation_number(): void
    {
        $this->assertSame('123456', $this->payment('mercado_pago', 'MP-123456')->mercadoPagoOperation());
        $this->assertSame('123456', $this->payment('mercado_pago', 'MP-123456-RESTO-7')->mercadoPagoOperation());
        $this->assertNull($this->payment('transferencia', 'REC-261106-ABCD')->mercadoPagoOperation());
    }
}
