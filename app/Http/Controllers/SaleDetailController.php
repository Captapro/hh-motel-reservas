<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\View\View;

class SaleDetailController extends Controller
{
    /**
     * Ficha completa de una venta (reserva) para el panel lateral de Ventas:
     * cliente, estadía, precio, consumos, pagos y acompañantes, sin salir de
     * la lista. Devuelve solo el fragmento HTML del panel.
     */
    public function show(string $code): View
    {
        $booking = Booking::with([
            'customer', 'room.category', 'createdBy', 'guests',
            'addons.addedBy', 'payments.paymentMethod', 'payments.registeredBy',
        ])->where('code', $code)->firstOrFail();

        return view('sales._detail', [
            'booking' => $booking,
            'extras' => (int) $booking->addons->sum('amount'),
            'paid' => $booking->paidAmount(),
            'balance' => $booking->balanceDue(),
        ]);
    }
}
