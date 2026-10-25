<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ComboItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingDeleteController extends Controller
{
    /**
     * Eliminar una reserva (solo soporte / master): pruebas, errores o limpieza
     * durante la puesta en marcha. Incluye las que ya tuvieron check-in o
     * quedaron finalizadas: se borran con sus pagos y consumos (sale de caja
     * y de ventas), se devuelve al inventario lo que se había consumido y
     * queda todo registrado en auditoría.
     */
    public function destroy(Request $request, string $code): RedirectResponse
    {
        $booking = Booking::with(['payments', 'addons', 'customer', 'room'])->where('code', $code)->firstOrFail();

        if (trim((string) $request->input('confirm_text')) !== 'ELIMINAR') {
            return back()->withErrors(['booking' => 'Para eliminar la reserva tienes que escribir ELIMINAR en el cuadro de confirmación.']);
        }

        $old = [
            'code' => $booking->code, 'estado' => $booking->booking_status, 'cliente' => $booking->customer?->name, 'habitacion' => $booking->room?->name,
            'inicio' => $booking->starts_at?->toDateTimeString(), 'precio' => $booking->price_final,
            'pagos' => $booking->payments->map(fn ($p) => ['monto' => $p->amount, 'estado' => $p->status, 'voucher' => $p->voucher_number, 'boleta' => $p->receipt_number])->all(),
            'consumos' => $booking->addons->map(fn ($a) => ['detalle' => $a->description, 'monto' => $a->amount])->all(),
            'tuvo_checkin' => $booking->checked_in_at !== null,
        ];

        DB::transaction(function () use ($booking) {
            $this->restoreStock($booking);
            DB::table('payments')->where('booking_id', $booking->id)->delete();
            $booking->delete();
        });
        AuditLog::record(auth()->id(), 'reserva.eliminar', 'Booking', $booking->id, $old, null);

        return redirect()->route('rooms.board')->with('status', 'Reserva '.$old['code'].' eliminada.');
    }

    /** Devuelve al inventario los productos que esta reserva había descontado. */
    private function restoreStock(Booking $booking): void
    {
        foreach ($booking->addons as $addon) {
            if ($addon->product_id) {
                Product::where('id', $addon->product_id)->where('track_inventory', true)->increment('stock', (int) $addon->quantity);
            } elseif ($addon->combo_id) {
                foreach (ComboItem::where('combo_id', $addon->combo_id)->get() as $item) {
                    Product::where('id', $item->product_id)->where('track_inventory', true)->increment('stock', (int) $item->quantity * (int) $addon->quantity);
                }
            }
        }
    }
}
