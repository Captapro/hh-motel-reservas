<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Combo;
use App\Models\Product;
use App\Services\Booking\ConsumptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingFinalizeController extends Controller
{
    public function show(string $code): View
    {
        $booking = Booking::with(['room.category', 'customer', 'addons'])->where('code', $code)->firstOrFail();

            if (! $booking->checked_in_at) {
                abort(403, 'Esta reserva todavía no tiene check-in — no se puede finalizar antes de que llegue el huésped.');
            }
            if ($booking->balanceDue() > 0) {
                abort(403, 'Esta reserva todavía tiene un saldo pendiente — registra el pago completo antes de finalizarla.');
            }

        $products = Product::where('is_active', true)->orderBy('display_order')->get();
        $combos = Combo::with('items.product')->where('is_active', true)->orderBy('display_order')->get();

        return view('reservations.finalize', ['booking' => $booking, 'products' => $products, 'combos' => $combos]);
    }

    public function store(Request $request, string $code, ConsumptionService $consumption): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();

            if (! $booking->checked_in_at) {
                abort(403, 'Esta reserva todavía no tiene check-in — no se puede finalizar antes de que llegue el huésped.');
            }
            if ($booking->balanceDue() > 0) {
                abort(403, 'Esta reserva todavía tiene un saldo pendiente — registra el pago completo antes de finalizarla.');
            }

        if ($booking->booking_status === 'FINALIZADA') {
            return redirect()->route('reservations.show', $booking->code)->with('status', 'Esta reserva ya estaba finalizada.');
        }

        $validated = $request->validate([
            'confirm_room_checked' => ['required', 'accepted'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'combo_quantities' => ['nullable', 'array'],
            'combo_quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
        ], [
            'confirm_room_checked.required' => 'Tienes que confirmar que revisaste la habitación antes de cerrar la reserva.',
            'confirm_room_checked.accepted' => 'Tienes que confirmar que revisaste la habitación antes de cerrar la reserva.',
        ]);

        try {
            // Todo o nada: si un producto falla por stock a mitad del envío,
            // lo que ya se había agregado en este mismo envío se revierte —
            // así reintentar no deja el primer ítem duplicado.
            DB::transaction(function () use ($validated, $booking, $consumption) {
                foreach ($validated['combo_quantities'] ?? [] as $comboId => $qty) {
                    if ((int) $qty > 0) {
                        $consumption->addCombo($booking, Combo::findOrFail($comboId), (int) $qty, auth()->id());
                    }
                }
                foreach ($validated['quantities'] ?? [] as $productId => $qty) {
                    if ((int) $qty > 0) {
                        $consumption->addProduct($booking, Product::findOrFail($productId), (int) $qty, auth()->id());
                    }
                }
            });
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['booking' => $e->getMessage().' No se agregó nada de este envío — ajusta la cantidad y vuelve a intentar.']);
        }

        // Los consumos recién agregados en este mismo envío pueden dejar
        // saldo pendiente aunque la reserva ya estuviera pagada al entrar a
        // esta pantalla -- hay que revalidar después de agregarlos, no solo
        // antes. back() manda de vuelta a este mismo formulario, pero
        // show() aborta con 403 en cuanto detecta saldo pendiente -- eso
        // dejaba a recepción en una pantalla en blanco en vez de mostrarle
        // el error. Mandar directo a registrar el pago es lo único que
        // realmente destraba la situación.
        if ($booking->fresh()->balanceDue() > 0) {
            return redirect()->route('payments.create', $booking->code)
                ->withErrors(['booking' => 'Los extras que acabas de agregar dejaron un saldo pendiente — registra el pago antes de cerrar la reserva.']);
        }

        $checkedOutAt = $booking->checked_out_at ?? now();
        // Si el huésped se va antes de la hora reservada, la habitación tiene
        // que quedar libre desde ese momento real — no desde la hora original
        // — para que el chequeo de disponibilidad y la restricción de la base
        // de datos dejen de bloquear ese tramo ya desocupado.
        $newEndsAt = $checkedOutAt->lt($booking->ends_at) ? max($booking->starts_at, $checkedOutAt) : $booking->ends_at;

        $old = $booking->only(['booking_status', 'consumption_offered_at', 'checked_out_at', 'ends_at']);
        $booking->fresh()->update([
            'booking_status' => 'FINALIZADA',
            'consumption_offered_at' => now(),
            'consumption_offered_by' => auth()->id(),
            'checked_out_at' => $checkedOutAt,
            'ends_at' => $newEndsAt,
        ]);
        AuditLog::record(auth()->id(), 'reserva.finalizar', 'Booking', $booking->id, $old, $booking->fresh()->only(['booking_status', 'consumption_offered_at', 'checked_out_at', 'ends_at']));

        // La habitación queda "en aseo" (fuera de disponibles) hasta que
        // recepción la reactive a mano — sin cronómetro: a veces no hay
        // mucama y la pieza queda sin hacer hasta el otro día.
        if ($booking->room->operational_status === 'activa') {
            $roomOld = $booking->room->only(['operational_status']);
            $booking->room->update(['operational_status' => 'aseo', 'aseo_started_at' => now(), 'aseo_override_at' => null]);
            AuditLog::record(auth()->id(), 'habitacion.enviar_aseo', 'Room', $booking->room->id, $roomOld, ['operational_status' => 'aseo']);
        }

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Reserva finalizada.');
    }
}
