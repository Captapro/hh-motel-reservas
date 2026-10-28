<?php

namespace App\Http\Controllers;

use App\Exceptions\PricingException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingReactivateController extends Controller
{
    /**
     * Reabre una reserva vencida sin pago, con la misma hora o una nueva, para
     * volver a mandarle el link de pago al cliente sin crearla desde cero.
     */
    public function store(Request $request, string $code, BookingService $bookingService): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'hold_minutes' => ['required', 'in:15,30,60'],
        ]);

        $booking = Booking::where('code', $code)->firstOrFail();

        try {
            $booking = $bookingService->reactivate(
                $booking,
                Carbon::parse($validated['date'].' '.$validated['time']),
                (int) $validated['hold_minutes'],
                auth()->id(),
            );
        } catch (RoomNotAvailableException|PricingException $e) {
            return back()->withInput()->withErrors(['reactivar' => $e->getMessage()]);
        }

        return redirect()->route('reservations.show', $booking->code)
            ->with('status', 'Reserva reactivada hasta las '.$booking->expires_at->timezone('America/Santiago')->format('H:i').'. Envíale el link de pago al cliente.');
    }
}
