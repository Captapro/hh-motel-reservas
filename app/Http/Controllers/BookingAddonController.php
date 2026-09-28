<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Booking;
use App\Models\Combo;
use App\Models\Product;
use App\Models\RateRule;
use App\Services\Booking\ConsumptionService;
use App\Services\Booking\AvailabilityChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingAddonController extends Controller
{
    private const CLOSED = ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'];

    public function extraHour(string $code, ConsumptionService $consumption, AvailabilityChecker $availability): RedirectResponse
    {
        $booking = Booking::with('room')->where('code', $code)->firstOrFail();

        if (in_array($booking->booking_status, self::CLOSED, true)) {
            return back()->withErrors(['booking' => 'Esta reserva ya está cerrada — no se le puede agregar más tiempo.']);
        }

        $newEndsAt = $booking->ends_at->copy()->addHour();
        if (! $availability->isAvailable($booking->room, $booking->ends_at, $newEndsAt, $booking->id)) {
            return back()->withErrors(['booking' => 'No se puede vender la hora adicional porque existe otra reserva después.']);
        }
        $rateName = strtoupper($booking->rate_rule_name_snapshot ?? 'HH');
        $amount = (int) (RateRule::where('name', $rateName)->value('extra_hour_price') ?? (str_contains($rateName, 'HOT') ? 15000 : 10000));
        $booking->update(['ends_at' => $newEndsAt]);
        $consumption->addCustom($booking, 'Hora adicional (1 hora)', $amount, auth()->id());
        return back()->with('status', 'Hora adicional agregada por $'.number_format($amount, 0, ',', '.').'. Nueva salida: '.$newEndsAt->timezone('America/Santiago')->format('H:i').'.');
    }

    public function store(Request $request, string $code, ConsumptionService $consumption): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();

        if (in_array($booking->booking_status, self::CLOSED, true)) {
            return back()->withErrors(['booking' => 'Esta reserva ya está cerrada — no se le puede agregar más consumo.']);
        }

        $validated = $request->validate([
            'item' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:200000'],
        ], [
            'quantity.max' => 'Máximo 20 unidades por línea — si son más, agrégalo en dos veces.',
            'amount.max' => 'Ese monto parece demasiado alto para un ítem de consumo — revísalo.',
        ]);

        try {
            [$type, $id] = array_pad(explode(':', $validated['item'] ?? '', 2), 2, null);
            $quantity = (int) ($validated['quantity'] ?? 1);

            if ($type === 'combo' && $id) {
                $consumption->addCombo($booking, Combo::findOrFail($id), $quantity, auth()->id());
            } elseif ($type === 'product' && $id) {
                $consumption->addProduct($booking, Product::findOrFail($id), $quantity, auth()->id());
            } else {
                $request->validate(['description' => ['required'], 'amount' => ['required']]);
                $consumption->addCustom($booking, $validated['description'], (int) $validated['amount'], auth()->id());
            }
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('reservations.show', $booking->code);
    }
}
