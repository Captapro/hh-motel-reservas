<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\PricingException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Combo;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\OperationalSetting;
use App\Models\Product;
use App\Models\RateRulePrice;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\UpsellOffer;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\ConsumptionService;
use App\Services\Pricing\RateRuleResolver;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reserva self-service desde el catálogo público -- sin login, sin
 * documento (se verifica en persona al check-in). Recepción ve la reserva
 * igual que si hubiera entrado por teléfono: queda PENDIENTE_PAGO, sin nada
 * cobrado todavía. Los cupones con código (no las ofertas automáticas) sí
 * se pueden aplicar acá si el cliente llega con el link de un banner de
 * cupón -- la verificación de edad/identidad queda para el check-in.
 */
class PublicBookingController extends Controller
{
    public function create(Request $request): View
    {
        $categories = RoomCategory::where('is_active', true)->orderBy('display_order')->get();
        $selectedCategoryId = (int) $request->query('categoria', $categories->first()?->id);
        $selectedRoomId = $request->integer('room_id') ?: null;
        $selectedCoupon = $request->filled('cupon')
            ? Coupon::where('auto_apply', false)->where('is_active', true)
                ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($request->query('cupon')))])
                ->first()
            : null;

        $durationsByCategory = RateRulePrice::select('room_category_id', 'duration_minutes')
            ->distinct()
            ->get()
            ->groupBy('room_category_id')
            ->map(fn ($rows) => $rows->pluck('duration_minutes')->map(fn ($m) => (int) $m)->unique()->sort()->values());

        $publicUpsells = UpsellOffer::active()->where('type', 'combo')->with(['combo.items.product'])
            ->orderBy('display_order')->get()
            ->filter(fn (UpsellOffer $upsell) => $upsell->combo?->is_active && ! $upsell->combo->isOutOfStock())
            ->values();
        $categoryUpsells = UpsellOffer::active()->where('type', 'category_upgrade')
            ->with(['fromCategory', 'toCategory'])
            ->where('from_room_category_id', $selectedCategoryId)
            ->orderBy('display_order')->get();

        return view('catalog.reservar', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId,
            'selectedRoomId' => $selectedRoomId,
            'selectedCoupon' => $selectedCoupon,
            'durationsByCategory' => $durationsByCategory,
            'publicUpsells' => $publicUpsells,
            'categoryUpsells' => $categoryUpsells,
            'couponConstraints' => $selectedCoupon ? [
                'weekdays' => $selectedCoupon->allowedWeekdaysArray(),
                'timeStart' => $selectedCoupon->allowed_time_start ? substr($selectedCoupon->allowed_time_start, 0, 5) : null,
                'timeEnd' => $selectedCoupon->allowed_time_end ? substr($selectedCoupon->allowed_time_end, 0, 5) : null,
                'label' => $selectedCoupon->constraintsLabel(),
            ] : null,
        ]);
    }

    public function store(Request $request, BookingService $bookingService, ConsumptionService $consumption, AvailabilityChecker $availability, RateRuleResolver $rateRules): RedirectResponse
    {
        // "00" con cero a la izquierda no pasa la regla integer de Laravel
        // (FILTER_VALIDATE_INT la rechaza), y una hora en punto siempre manda "00".
        $request->merge([
            'time_hour' => is_numeric($request->input('time_hour')) ? (int) $request->input('time_hour') : $request->input('time_hour'),
            'time_minute' => is_numeric($request->input('time_minute')) ? (int) $request->input('time_minute') : $request->input('time_minute'),
        ]);

        $validated = $request->validate([
            'room_category_id' => ['required', 'exists:room_categories,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'time_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'duration_minutes' => ['required', 'integer'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:10'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:10'],
            'combo_quantities' => ['nullable', 'array'],
            'combo_quantities.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'accepted_upsells' => ['nullable', 'array'],
            'accepted_upsells.*' => ['integer', 'exists:upsell_offers,id'],
            // Campo trampa para bots -- invisible para una persona real
            // (oculto por CSS), un bot que autocompleta todo lo llena.
            'website' => ['prohibited'],
        ]);

        $startsAt = Carbon::parse($validated['date'].' '.sprintf('%02d:%02d', $validated['time_hour'], $validated['time_minute']));
        $endsAt = $startsAt->copy()->addMinutes((int) $validated['duration_minutes']);

        $room = $this->findAvailableRoom($validated['room_category_id'], $validated['room_id'] ?? null, $startsAt, $endsAt, $availability, $rateRules);

        if (! $room) {
            return back()->withInput()->withErrors(['duration_minutes' => 'No hay habitaciones libres de ese tipo para ese horario — prueba otra fecha, hora o duración.']);
        }

        $phone = Phone::toE164($validated['phone']);
        $customerName = trim($validated['first_name'].' '.$validated['last_name']);
        $customer = Customer::firstOrCreate(
            ['phone_e164' => $phone],
            ['name' => $customerName, 'email' => $validated['email'] ?? null, 'birth_date' => $validated['birth_date'] ?? null]
        );
        if ($customer->blacklist_status === 'blocked') {
            return back()->withInput()->withErrors(['phone' => 'No es posible solicitar la reserva con los datos ingresados.']);
        }
        // Cliente ya existía pero le faltaba la fecha de nacimiento (ej.
        // para el cupón de Expertos en Vida) -- se completa ahora.
        if (! empty($validated['birth_date']) && ! $customer->birth_date) {
            $customer->update(['birth_date' => $validated['birth_date']]);
        }

        try {
            $booking = $bookingService->create([
                'room' => $room,
                'customer' => $customer,
                'starts_at' => $startsAt,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'guests_count' => (int) $validated['guests_count'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'deposit_amount' => 0,
                'created_by' => null,
                'verified_by' => null,
                'notes' => 'Reserva online desde el catálogo público.',
            ]);
        } catch (RoomNotAvailableException|PricingException|InvalidCouponException $e) {
            return back()->withInput()->withErrors(['duration_minutes' => $e->getMessage()]);
        }

        // Los extras se vuelven a resolver desde la base de datos: el navegador
        // solo envía IDs y cantidades, nunca precios. Se guardan como consumo
        // de la reserva y el stock se descuenta con la misma lógica de recepción.
        // Cada ítem se intenta por separado -- que uno se haya agotado justo
        // antes de enviar no debe tumbar los demás que sí tenían stock.
        $soldOut = false;

        $productQuantities = collect($validated['quantities'] ?? [])
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => (int) $quantity])
            ->filter(fn ($quantity) => $quantity > 0);
        $products = Product::where('is_active', true)->whereIn('id', $productQuantities->keys())->get()->keyBy('id');
        foreach ($productQuantities as $productId => $quantity) {
            if (! isset($products[$productId])) {
                continue;
            }
            try {
                $consumption->addProduct($booking, $products[$productId], $quantity, null);
            } catch (InsufficientStockException $e) {
                $soldOut = true;
            }
        }

        $comboQuantities = collect($validated['combo_quantities'] ?? [])
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => (int) $quantity])
            ->filter(fn ($quantity) => $quantity > 0);
        $combos = Combo::with('items.product')->where('is_active', true)->whereIn('id', $comboQuantities->keys())->get()->keyBy('id');
        foreach ($comboQuantities as $comboId => $quantity) {
            if (! isset($combos[$comboId]) || $combos[$comboId]->isOutOfStock()) {
                if (isset($combos[$comboId])) {
                    $soldOut = true;
                }

                continue;
            }
            try {
                $consumption->addCombo($booking, $combos[$comboId], $quantity, null);
            } catch (InsufficientStockException $e) {
                $soldOut = true;
            }
        }

        if ($soldOut) {
            // La reserva ya tiene su habitación bloqueada; informamos para que
            // recepción pueda agregar el extra disponible manualmente. Los
            // demás extras que sí tenían stock ya quedaron agregados arriba.
            return redirect()->route('catalog.booked', $booking->pass_token)
                ->with('warning', 'La reserva quedó creada, pero uno de los extras seleccionados se agotó. Recepción podrá ofrecerte otra alternativa.');
        }

        // from_room_category_id sin comprobar acá dejaba aceptar CUALQUIER
        // upsell de upgrade activo por su ID, aunque fuera de una categoría
        // de origen distinta a la que el cliente realmente reservó --
        // UpsellResolver::evaluate() sí lo valida para el panel interno,
        // este flujo público arma la reserva a mano y se lo saltaba.
        $upgrade = UpsellOffer::active()->where('type', 'category_upgrade')
            ->where('from_room_category_id', $booking->room->room_category_id)
            ->with('toCategory')
            ->whereIn('id', $validated['accepted_upsells'] ?? [])->first();
        if ($upgrade?->to_room_category_id) {
            app(BookingAllocationService::class)->upgrade($booking, $upgrade, null);
        }

        return redirect()->route('catalog.booked', $booking->pass_token);
    }

    public function booked(string $token): View
    {
        $booking = Booking::with(['room.category', 'addons'])->where('pass_token', $token)->firstOrFail();

        return view('catalog.booked', ['booking' => $booking]);
    }

    /**
     * Chequeo en vivo desde el formulario -- antes el cliente solo se
     * enteraba de que no había disponibilidad DESPUÉS de llenar sus datos
     * de contacto y enviar todo. Usa la misma búsqueda que store(), así que
     * "disponible" acá nunca contradice el resultado real del envío (salvo
     * que otra reserva tome el horario justo entre medio).
     */
    public function checkAvailability(Request $request, AvailabilityChecker $availability, RateRuleResolver $rateRules): JsonResponse
    {
        $validated = $request->validate([
            'room_category_id' => ['required', 'exists:room_categories,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'time_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'duration_minutes' => ['required', 'integer'],
        ]);

        $startsAt = Carbon::parse($validated['date'].' '.sprintf('%02d:%02d', $validated['time_hour'], $validated['time_minute']));
        $endsAt = $startsAt->copy()->addMinutes((int) $validated['duration_minutes']);

        if ($startsAt->lt(now()->subMinutes(5))) {
            return response()->json(['available' => false]);
        }

        $room = $this->findAvailableRoom($validated['room_category_id'], $validated['room_id'] ?? null, $startsAt, $endsAt, $availability, $rateRules);

        return response()->json(['available' => (bool) $room]);
    }

    private function findAvailableRoom(int $categoryId, ?int $roomId, Carbon $startsAt, Carbon $endsAt, AvailabilityChecker $availability, RateRuleResolver $rateRules): ?Room
    {
        // Usada tanto por el chequeo en vivo (checkAvailability) como por
        // store() -- si acá falta alguna regla que store() sí aplica
        // después (categoría deshabilitada, fuera de la ventana operativa
        // de la tarifa), el chequeo en vivo miente diciendo "disponible" y
        // el envío real rechaza con un error de tarifa que no tiene nada
        // que ver con lo que el cliente vio en pantalla.
        if (! $rateRules->resolve($startsAt, $endsAt)) {
            return null;
        }

        $operationalSetting = OperationalSetting::current();
        $roomQuery = Room::where('room_category_id', $categoryId)
            ->where('operational_status', 'activa')
            ->categoryEnabled()
            ->floorWingEnabled($operationalSetting);
        if (! empty($roomId)) {
            $roomQuery->whereKey($roomId);
        }

        return $roomQuery->orderBy('name')->get()->first(fn (Room $r) => $availability->isAvailable($r, $startsAt, $endsAt));
    }
}
