<?php

namespace App\Http\Controllers;

use App\Exceptions\PricingException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Room;
use App\Services\Booking\BookingAllocationService;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cambio de pieza con diferencia: mueve la reserva a otra pieza sin recalcular lo contratado
 * y deja la diferencia como un cobro propio (con su voucher) en la misma reserva.
 */
class RoomChangeController extends Controller
{
    private const CLOSED = ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'];

    public function show(string $code, BookingAllocationService $allocation, PriceCalculator $calculator): View|RedirectResponse
    {
        $booking = Booking::with(['room.category', 'customer', 'payments'])->where('code', $code)->firstOrFail();
        if (in_array($booking->booking_status, self::CLOSED, true)) {
            return redirect()->route('reservations.show', $code)->withErrors(['booking' => 'Esta reserva ya está cerrada: no se le puede cambiar la pieza.']);
        }

        $options = Room::with('category')->where('id', '!=', $booking->room_id)->orderBy('name')->get()
            ->map(fn (Room $room) => [
                'room' => $room,
                'problem' => $allocation->roomProblem($room, $booking),
                'suggested' => $this->suggested($booking, $room, $calculator),
            ])
            ->sortBy(fn ($o) => [$o['problem'] ? 1 : 0, $o['room']->name])->values();

        return view('reservations.change-room', [
            'booking' => $booking,
            'options' => $options,
            'canEditAmount' => (bool) auth()->user()?->isAdministrador(),
        ]);
    }

    public function store(Request $request, string $code, BookingAllocationService $allocation, PriceCalculator $calculator): RedirectResponse
    {
        $booking = Booking::with(['room.category'])->where('code', $code)->firstOrFail();
        $v = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:500000'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ], ['reason.required' => 'Escribe el motivo del cambio (mínimo 5 letras).', 'reason.min' => 'Escribe el motivo del cambio (mínimo 5 letras).']);

        $target = Room::with('category')->findOrFail($v['room_id']);
        $suggested = $this->suggested($booking, $target, $calculator);

        // La diferencia sugerida sale de la tarifa; solo un administrador puede cambiarla.
        if (auth()->user()?->isAdministrador() && $request->filled('amount')) {
            $difference = (int) $v['amount'];
        } elseif ($suggested !== null) {
            $difference = $suggested;
        } else {
            return back()->withInput()->withErrors(['room_id' => 'No hay una tarifa para calcular la diferencia: pídele a un administrador que la ingrese.']);
        }

        try {
            $allocation->changeRoom($booking, $target, $difference, null, auth()->id(), $v['reason']);
        } catch (RoomNotAvailableException $e) {
            return back()->withInput()->withErrors(['room_id' => $e->getMessage()]);
        }

        if ($difference > 0) {
            return redirect()->route('payments.create', $code)
                ->with('status', "Pieza cambiada a {$target->name}. Cobra ahora la diferencia de $".number_format($difference, 0, ',', '.').' con su voucher.');
        }

        return redirect()->route('reservations.show', $code)->with('status', "Pieza cambiada a {$target->name}. Sin diferencia que cobrar.");
    }

    /** Diferencia sugerida = lo que costaría hoy la pieza nueva menos lo ya contratado (nunca negativa). null si no hay tarifa. */
    private function suggested(Booking $booking, Room $room, PriceCalculator $calculator): ?int
    {
        try {
            $pricing = $calculator->calculate($room, $booking->starts_at, $booking->ends_at, (int) $booking->duration_minutes, (int) $booking->guests_count);
        } catch (PricingException) {
            return null;
        }
        $newFinal = max(0, (int) $pricing['price_original'] + (int) $pricing['extra_guests_fee'] - (int) $booking->discount_amount);

        return max(0, $newFinal - (int) $booking->price_final);
    }
}
