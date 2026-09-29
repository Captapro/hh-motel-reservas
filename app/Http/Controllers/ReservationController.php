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
use App\Models\RateRule;
use App\Models\RateRulePrice;
use App\Models\RateRuleWindow;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\UpsellOffer;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\ConsumptionService;
use App\Services\Booking\RoomBoardService;
use App\Services\Pricing\CouponValidator;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PromotionResolver;
use App\Services\Pricing\RateRuleResolver;
use App\Services\Pricing\UpsellResolver;
use App\Support\Phone;
use App\Support\Rut;
use App\Support\TimeFormat;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function create(Request $request, RoomBoardService $board, RateRuleResolver $rates): View
    {
        $operationalSetting = OperationalSetting::current();
        $rooms = Room::with('category')
            ->where('operational_status', 'activa')
            ->categoryEnabled()
            ->floorWingEnabled($operationalSetting)
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) use ($board) {
                $room->current_status = $board->statusFor($room);

                return $room;
            });

        // Sin preselección: recepción elige la habitación a mano. Solo viene
        // marcada si se entró desde "Reservar →" de una tarjeta (?room_id=)
        // o al volver del formulario con un error de validación.
        $selectedRoomId = old('room_id', $request->query('room_id'));

        $today = now()->toDateString();
        $coupons = Coupon::where('is_active', true)
            ->where('auto_apply', false)
            ->where(function ($q) use ($today) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->orderBy('code')
            ->get();

        $now = now();
        $tariff = $rates->currentStatus($now);
        $nextOpening = $tariff ? null : $rates->nextOpening($now);

        // Ventanas horarias activas — se mandan tal cual a la vista para que el
        // JS pueda avisar de inmediato si la hora elegida cae fuera de cualquier
        // tarifa, sin tener que reescribir el horario a mano ahí (y quedar
        // desactualizado el día que cambien las tarifas desde el admin).
        $windows = RateRuleWindow::whereHas('rateRule', fn ($q) => $q->where('is_active', true))
            ->get(['weekday', 'start_time', 'end_time', 'wraps_midnight'])
            ->map(fn (RateRuleWindow $w) => [
                'weekday' => $w->weekday,
                'start' => substr($w->start_time, 0, 5),
                'end' => substr($w->end_time, 0, 5),
                'wraps' => $w->wraps_midnight,
            ]);

        $products = Product::where('is_active', true)->orderBy('display_order')->get();
        $combos = Combo::with('items.product')->where('is_active', true)->orderBy('display_order')->get();

        return view('reservations.create', [
            'rooms' => $rooms, 'selectedRoomId' => $selectedRoomId, 'coupons' => $coupons,
            'showTariff' => true,
            'tariff' => $tariff,
            'closesLabel' => $tariff ? TimeFormat::minutes((int) max(0, $now->diffInMinutes($tariff['closes_at'], false))) : null,
            'nextOpeningLabel' => $nextOpening ? TimeFormat::minutes((int) max(0, $now->diffInMinutes($nextOpening, false))) : null,
            'windows' => $windows,
            'products' => $products,
            'combos' => $combos,
            'categoryDurations' => $this->durationsByCategorySlug(),
        ]);
    }

    /**
     * Duraciones reservables por categoría (slug), según los precios cargados
     * en las tarifas — GO solo 1-2 h, LITE solo 3 h, PLUS/MAX 3-6-12 h. El
     * formulario muestra solo esos botones para no dejar elegir un largo que
     * no tiene precio configurado.
     *
     * @return array<string, array<int>>
     */
    private function durationsByCategorySlug(): array
    {
        $slugById = RoomCategory::pluck('name', 'id')
            ->map(fn ($name) => Str::slug($name));

        return RateRulePrice::select('room_category_id', 'duration_minutes')
            ->distinct()
            ->get()
            ->groupBy('room_category_id')
            ->mapWithKeys(fn ($rows, $catId) => [
                $slugById[$catId] => $rows->pluck('duration_minutes')->map(fn ($m) => (int) $m)->unique()->sort()->values()->all(),
            ])
            ->all();
    }

    public function store(Request $request, BookingService $bookingService, ConsumptionService $consumption, AvailabilityChecker $availability): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'time_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'time_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'duration_minutes' => ['required', 'integer'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:10'],
            'customer_first_name' => ['required', 'string', 'max:150'],
            'customer_last_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['required', 'email'],
            'document_type' => ['required', 'in:rut,pasaporte'],
            'document_number' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('document_type') === 'rut' && $value && ! Rut::isValid($value)) {
                    $fail('El RUT no es válido — revisa el dígito verificador.');
                }
            }],
            'nationality' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'coupon_code' => ['nullable', 'string'],
            'deposit_amount' => ['nullable', 'integer', 'min:0'],
            'guest_names' => ['nullable', 'string'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'combo_quantities' => ['nullable', 'array'],
            'combo_quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'accepted_upsells' => ['nullable', 'array'],
            'accepted_upsells.*' => ['integer', 'exists:upsell_offers,id'],
        ]);

        $room = Room::with('category')->findOrFail($validated['room_id']);
        $time = sprintf('%02d:%02d', $validated['time_hour'], $validated['time_minute']);
        $startsAt = Carbon::parse($validated['date'].' '.$time);
        $baseDuration = (int) $validated['duration_minutes'];

        // Upsells aceptados en el modal — se resuelven de nuevo acá (autoridad
        // del servidor). La reserva se crea y cobra con la habitación y
        // duración ORIGINALES; el upsell suma su precio fijo como consumo:
        //  - "más tiempo": alarga ends_at (extra_minutes), precio base
        //  - "subir categoría": cambia room_id a una libre de la cat. destino
        //    DESPUÉS de crear, sin reprecio (pagan lo original + el fijo)
        //  - combo: se agrega como consumo
        $extraMinutes = 0;
        $upsellCharges = [];
        $upsellCombos = [];
        $upgradeTargetRoomId = null;
        $upgradeOffer = null;
        $upsells = UpsellOffer::active()->with('combo')
            ->whereIn('id', $validated['accepted_upsells'] ?? [])
            ->orderByRaw("type = 'time_extension' desc")
            ->get();

        foreach ($upsells as $u) {
            if ($u->type === 'time_extension' && $u->extra_minutes) {
                $extraMinutes += $u->extra_minutes;
                $upsellCharges[] = ['label' => $u->name, 'amount' => (int) $u->price];
            } elseif ($u->type === 'category_upgrade' && $u->to_room_category_id && ! $upgradeTargetRoomId && (int) $u->from_room_category_id === (int) $room->room_category_id) {
                $endsAt = $startsAt->copy()->addMinutes($baseDuration + $extraMinutes);
                $target = Room::where('room_category_id', $u->to_room_category_id)
                    ->where('operational_status', 'activa')->where('id', '!=', $room->id)
                    ->orderBy('name')->get()
                    ->first(fn (Room $r) => $availability->isAvailable($r, $startsAt, $endsAt));
                if ($target) {
                    $upgradeTargetRoomId = $target->id;
                    $upgradeOffer = $u;
                }
            } elseif ($u->type === 'combo' && $u->combo) {
                $upsellCombos[] = $u->combo;
            }
        }
        $customerName = trim($validated['customer_first_name'].' '.$validated['customer_last_name']);

        // Documento: un solo campo en el form ("document_number"), pero se
        // guarda en rut o passport_number según el tipo elegido — nunca los
        // dos a la vez.
        $documentType = $validated['document_type'] ?? null;
        $documentNumber = $validated['document_number'] ?? null;
        $rut = $documentType === 'rut' && $documentNumber ? Rut::normalize($documentNumber) : null;
        $passportNumber = $documentType === 'pasaporte' ? $documentNumber : null;

        // Buscar cliente por teléfono normalizado; crearlo si no existe (caché local — sección 08 del doc).
        $phone = Phone::toE164($validated['customer_phone']);
        $customer = Customer::firstOrCreate(
            ['phone_e164' => $phone],
            [
                'name' => $customerName,
                'email' => $validated['customer_email'],
                'document_type' => $documentType,
                'rut' => $rut,
                'passport_number' => $passportNumber,
                'nationality' => $validated['nationality'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
            ]
        );

        // Cliente ya conocido con datos distintos: actualizamos con lo último
        // que ingresó recepción (el teléfono manda como identidad, el resto
        // puede cambiar).
        if (! $customer->wasRecentlyCreated) {
            $customer->fill(array_filter([
                'name' => $customerName,
                'email' => $validated['customer_email'],
                'document_type' => $documentType,
                'rut' => $rut,
                'passport_number' => $passportNumber,
                'nationality' => $validated['nationality'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
            ]))->save();
        }
        if ($customer->blacklist_status === 'blocked') {
            return back()->withInput()->withErrors(['customer_phone' => 'Cliente bloqueado en lista negra. Revisar con administración antes de reservar.']);
        }

        try {
            $booking = $bookingService->create([
                'room' => $room,
                'customer' => $customer,
                'starts_at' => $startsAt,
                'duration_minutes' => $baseDuration,
                'extra_minutes' => $extraMinutes,
                'guests_count' => (int) $validated['guests_count'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'deposit_amount' => (int) ($validated['deposit_amount'] ?? 0),
                'created_by' => auth()->id(),
                'verified_by' => auth()->id(),
                'notes' => null,
                'guest_names' => preg_split('/\r\n|\r|\n/', $validated['guest_names'] ?? ''),
            ]);
        } catch (RoomNotAvailableException|PricingException|InvalidCouponException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }

        // Upgrade de categoría: se cambia la habitación después de crear, para
        // que el precio de tarifa siga siendo el de la categoría reservada.
        // Si la habitación destino se ocupó en el medio, se deja la original.
        if ($upgradeOffer) {
            app(BookingAllocationService::class)->upgrade($booking, $upgradeOffer, auth()->id());
        }

        foreach ($upsellCharges as $c) {
            if ($c['amount'] > 0) {
                $consumption->addCustom($booking, $c['label'], $c['amount'], auth()->id());
            }
        }

        try {
            foreach ($upsellCombos as $combo) {
                $consumption->addCombo($booking, $combo, 1, auth()->id());
            }
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
        } catch (InsufficientStockException $e) {
            $finalRoomName = $booking->fresh()->room->name;
            $stockStatus = 'Reserva '.$booking->code.' creada — '.$finalRoomName.'. '.$e->getMessage().' No se pudo agregar ese consumo — agrégalo manualmente si corresponde.';

            if ($booking->fresh()->balanceDue() > 0) {
                return redirect()->route('payments.create', ['code' => $booking->code, 'after' => 'board'])
                    ->with('status', $stockStatus.' Registrá el medio de pago.');
            }

            return redirect()->route('rooms.board')->with('status', $stockStatus);
        }

        $finalRoomName = $booking->fresh()->room->name;

        // Después de registrar la habitación siempre se pasa por el registro
        // de pago — no depende de si recepción cargó algo en "Abono", sino de
        // si queda saldo por cobrar. Sin saldo (p. ej. cortesía) se salta
        // directo al tablero, porque no hay nada que registrar.
        if ($booking->fresh()->balanceDue() > 0) {
            return redirect()->route('payments.create', ['code' => $booking->code, 'after' => 'board'])
                ->with('status', 'Reserva '.$booking->code.' creada — '.$finalRoomName.' · '.$customerName.'. Registrá el medio de pago.');
        }

        return redirect()->route('rooms.board')->with('status', 'Reserva '.$booking->code.' creada — '.$finalRoomName.' · '.$customerName.'.');
    }

    public function edit(string $code, RoomBoardService $board): View
    {
        $booking = Booking::with(['room.category', 'customer'])->where('code', $code)->firstOrFail();

        if (in_array($booking->booking_status, ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'], true)) {
            abort(403, 'Esta reserva ya no se puede modificar.');
        }

        $rooms = Room::with('category')
            ->where('operational_status', 'activa')
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) use ($board) {
                $room->current_status = $board->statusFor($room);

                return $room;
            });

        return view('reservations.edit', [
            'booking' => $booking,
            'rooms' => $rooms,
            'categoryDurations' => $this->durationsByCategorySlug(),
        ]);
    }

    public function update(Request $request, string $code, BookingService $bookingService): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();

        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'time_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'time_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'duration_minutes' => ['required', 'integer'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $room = Room::findOrFail($validated['room_id']);
        $time = sprintf('%02d:%02d', $validated['time_hour'], $validated['time_minute']);
        $startsAt = Carbon::parse($validated['date'].' '.$time);

        try {
            $bookingService->reschedule($booking, [
                'room' => $room,
                'starts_at' => $startsAt,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'guests_count' => (int) $validated['guests_count'],
                'updated_by' => auth()->id(),
            ]);
        } catch (RoomNotAvailableException|PricingException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('reservations.show', $booking->code)->with('status', 'Reserva modificada.');
    }

    /**
     * Precio en vivo para el formulario de reserva. Regla: una oferta
     * programada gana y el código promocional NO acumula sobre ella — solo
     * cuando la habitación no está en oferta el código aplica su descuento.
     */
    public function priceQuote(Request $request, PriceCalculator $calculator, PromotionResolver $promotions, CouponValidator $couponValidator, UpsellResolver $upsells): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'time_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'time_minute' => ['required', 'integer', 'min:0', 'max:59'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'guests_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'coupon_code' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
        ]);

        $duration = (int) $data['duration_minutes'];
        $room = Room::with('category')->findOrFail($data['room_id']);
        $startsAt = Carbon::parse(sprintf('%s %02d:%02d', $data['date'], (int) $data['time_hour'], (int) $data['time_minute']));
        $endsAt = $startsAt->copy()->addMinutes($duration);
        $guests = (int) ($data['guests_count'] ?? $room->category->base_capacity);
        $code = trim((string) ($data['coupon_code'] ?? ''));

        try {
            $pricing = $calculator->calculate($room, $startsAt, $endsAt, $duration, $guests);
        } catch (PricingException $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

        $priceOriginal = $pricing['price_original'] + $pricing['extra_guests_fee'];
        $offer = $promotions->bestFor($room, $startsAt, $duration, null, $priceOriginal, $guests, (int) $pricing['rate_rule']->extra_person_price);

        $response = [
            'price_original' => $offer['price_original'] ?? $priceOriginal,
            'tariff' => $pricing['rate_rule']->name,
            'applied' => null,
            'code_blocked' => false,
            'code_error' => null,
            'upsells' => $upsells->applicableFor($room, $duration, $startsAt)
                ->map(fn ($u) => [
                    'id' => $u['offer']->id,
                    'type' => $u['offer']->type,
                    'label' => $u['label'],
                    'price' => $u['price'],
                    'detail' => $u['detail'],
                ])->values(),
        ];

        if ($offer) {
            $response['applied'] = [
                'kind' => 'offer',
                'label' => $offer['coupon']->internal_name,
                'discount' => $offer['discount'],
                'price_final' => max(0, $offer['price_original'] - $offer['discount']),
            ];
            $response['code_blocked'] = $code !== '';

            return response()->json($response);
        }

        if ($code !== '') {
            $coupon = Coupon::where('auto_apply', false)
                ->whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])
                ->first();

            if (! $coupon) {
                $response['code_error'] = 'El código no existe.';

                return response()->json($response);
            }

            // Sin guardar nada: solo para que el validador pueda chequear
            // cupones con edad mínima (ej. Expertos en Vida) mientras
            // recepción todavía está completando el formulario.
            $previewCustomer = ! empty($data['birth_date']) ? new Customer(['birth_date' => $data['birth_date']]) : null;

            try {
                $discount = $couponValidator->validate($coupon, $room, $startsAt, $duration, $previewCustomer, $priceOriginal);
                $response['applied'] = [
                    'kind' => 'code',
                    'label' => $coupon->code,
                    'discount' => $discount,
                    'price_final' => max(0, $priceOriginal - $discount),
                ];
            } catch (InvalidCouponException $e) {
                $response['code_error'] = $e->getMessage();
            }
        }

        return response()->json($response);
    }

    /**
     * Búsqueda de cliente por teléfono para el formulario de reserva — se
     * pega el número de WhatsApp y trae nombre/email/historial si ya existe.
     */
    public function lookupCustomer(Request $request): JsonResponse
    {
        $raw = (string) $request->query('phone', '');

        if (! Phone::looksComplete($raw)) {
            return response()->json(['found' => false]);
        }

        $customer = Customer::where('phone_e164', Phone::toE164($raw))->first();

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        $parts = preg_split('/\s+/', trim($customer->name), 2);
        $seg = $customer->segment();

        return response()->json([
            'found' => true,
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'email' => $customer->email,
            'segment_label' => $seg['label'],
            'segment_type' => $seg['type'],
            'total_stays' => $seg['total_stays'],
            'last_visit' => $seg['last_visit_at']?->timezone('America/Santiago')->format('d/m/Y'),
            'monthly_visits' => $customer->monthlyVisits(),
            'history_url' => route('admin.customers.show', $customer),
            'document_type' => $customer->document_type,
            'document_number' => $customer->documentNumber(),
            'nationality' => $customer->nationality,
            'birth_date' => $customer->birth_date?->toDateString(),
        ]);
    }

    public function show(string $code): View
    {
        $booking = Booking::with(['room.category', 'customer', 'coupon', 'payments.paymentMethod', 'guests', 'addons'])
            ->where('code', $code)
            ->firstOrFail();

        $products = Product::where('is_active', true)->orderBy('display_order')->get();
        $combos = Combo::with('items.product')->where('is_active', true)->orderBy('display_order')->get();

        $extraHourPrice = (int) (RateRule::where('name', $booking->rate_rule_name_snapshot)->value('extra_hour_price') ?? 0);

        return view('reservations.show', ['booking' => $booking, 'products' => $products, 'combos' => $combos, 'extraHourPrice' => $extraHourPrice]);
    }

    /**
     * Cámara para escanear el QR del pase de reserva (ver pass-pdf.blade.php)
     * -- identifica al huésped en recepción sin tener que buscarlo a mano.
     * Misma mecánica que ShiftRoundController::scan(): decodifica en el
     * navegador con jsQR y navega directo, validando que la URL decodificada
     * sea del propio dominio y con el patrón /reservas/{code} antes de
     * seguirla.
     */
    public function scan(): View
    {
        return view('reservations.scan');
    }

    /**
     * QR "puente" para el tablero (pantalla de PC): la cámara está en el
     * celular, no en el PC, así que esto se muestra en pantalla para que
     * recepción lo escanee con su celular y ahí se le abra directo la
     * cámara de /reservas/escanear -- no requiere tipear nada.
     */
    public function scanQrImage(): Response
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: route('reservations.scan'),
            size: 400,
            margin: 8,
        ))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }
}
