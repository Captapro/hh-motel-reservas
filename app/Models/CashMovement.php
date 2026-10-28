<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Movimiento manual de caja chica — plata que entra (ingreso) o sale
 * (egreso) sin pasar por una reserva. El efectivo de las ventas se calcula
 * aparte, directo desde Payment (medio de pago = efectivo).
 */
#[Fillable(['type', 'amount', 'description', 'created_by'])]
class CashMovement extends Model
{
    /**
     * Efectivo que debería haber físicamente en caja: todo lo cobrado en
     * efectivo, más los ingresos manuales, menos los egresos — histórico
     * completo, porque la caja no se vacía sola cada medianoche.
     *
     * @return array{sales: int, ingresos: int, egresos: int, saldo: int}
     */
    public static function balance(): array
    {
        $cashMethodId = PaymentMethod::where('code', 'efectivo')->value('id');
        $sales = (int) Payment::where('payment_method_id', $cashMethodId)->where('status', 'aprobado')->sum('amount');
        $ingresos = (int) static::where('type', 'ingreso')->sum('amount');
        $egresos = (int) static::where('type', 'egreso')->sum('amount');

        return ['sales' => $sales, 'ingresos' => $ingresos, 'egresos' => $egresos, 'saldo' => $sales + $ingresos - $egresos];
    }
}
