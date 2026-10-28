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
use App\Models\ShortStaySetting;
use App\Models\UpsellOffer;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\ConsumptionService;
use App\Services\Payments\MercadoPagoClient;
use App\Services\Payments\MercadoPagoPayments;
use App\Services\Pricing\RateRuleResolver;
use App\Services\Pricing\ShortStayPricer;
use App\Support\Phone;
use App\Support\TimeFormat;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        $durationsByCategory = RateRulePrice::whereHas('rateRule', fn ($q) => $q->where('is_active', true))
            ->select('room_category_id', 'duration_minutes')
            ->distinct()
            ->get()
            ->groupBy('room_category_id')
            ->map(fn ($rows) => $rows->pluck('duration_minutes')->map(fn ($m) => (int) $m)->unique()->sort()->values());

        // Combos que el administrador marcó "En catálogo" (Administración → Combos).
        $catalogCombos = Combo::with('items.product')->where('is_active', true)->where('show_in_catalog', true)
            ->orderBy('display_order')->orderBy('name')->get()
            ->reject(fn (Combo $combo) => $combo->isOutOfStock() || $combo->items->isEmpty())
            ->values();
        $categoryUpsells = UpsellOffer::active()->where('type', 'category_upgrade')
            ->with(['fromCategory', 'toCategory'])
            ->where('from_room_category_id', $selectedCategoryId)
            ->orderBy('display_order')->get();

        // "Reservar de nuevo" (WhatsApp de rescate) trae fecha, hora y duración ya elegidas.
        $prefill = [
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('fecha')) && $request->query('fecha') >= now()->toDateString() ? $request->query('fecha') : null,
            'time' => preg_match('/^([01]\d|2[0-3]):(00|15|30|45)$/', (string) $request->query('hora'), $m) ? ['hour' => (int) $m[1], 'minute' => (int) $m[2]] : null,
            'duration' => $request->integer('duracion') ?: null,
        ];

        return view('catalog.reservar', [
            'prefill' => $prefill,
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId,
            'selectedRoomId' => $selectedRoomId,
            'selectedCoupon' => $selectedCoupon,
            'durationsByCategory' => $durationsByCategory,
            'catalogCombos' => $catalogCombos,
            'categoryUpsells' => $categoryUpsells,
            'couponConstraints' => $selectedCoupon ? [
                'weekdays' => $selectedCoupon->allowedWeekdaysArray(),
                'timeStart' => $selectedCoupon->allowed_time_start ? substr($selectedCoupon->allowed_time_start, 0, 5) : null,
                'timeEnd' => $selectedCoupon->allowed_time_end ? substr($selectedCoupon->allowed_time_end, 0, 5) : null,
                'label' => $selectedCoupon->constraintsLabel(),
            ] : null,
        ]);
    }

    public function store(Request $request, BookingService $bookingService, ConsumptionService $consumption, AvailabilityChecker $availability, RateRuleResolver $rateRules, ShortStayPricer $shortStays): RedirectResponse
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
            'email' => ['required', 'email', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:10'],
            'combo_quantities' => ['nullable', 'array'],
            'combo_quantities.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'accepted_upsells' => ['nullable', 'array'],
            'accepted_upsells.*' => ['integer', 'exists:upsell_offers,id'],
            'short_stay_accept' => ['nullable', 'boolean'],
            // Ley de datos personales: aceptar el aviso es obligatorio para reservar;
            // recibir promociones es un permiso aparte y opcional.
            'accept_privacy' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            // Campo trampa para bots -- invisible para una persona real
            // (oculto por CSS), un bot que autocompleta todo lo llena.
            'website' => ['prohibited'],
        ], [
            'accept_privacy.accepted' => 'Para reservar tienes que aceptar el aviso de privacidad.',
        ]);

        // Un solo envío a la vez por persona y horario: el segundo espera y ve la reserva ya creada.
        $lock = Cache::lock('catalog-booking:'.md5(Phone::toE164($validated['phone']).'|'.$validated['date'].'|'.$validated['time_hour'].':'.$validated['time_minute'].'|'.$validated['duration_minutes']), 20);
        try {
            $lock->block(10);
        } catch (LockTimeoutException $e) {
            return back()->withInput()->withErrors(['duration_minutes' => 'Estamos procesando tu solicitud, espera unos segundos y revisa si ya quedó creada.']);
        }

        try {
            $startsAt = Carbon::parse($validated['date'].' '.sprintf('%02d:%02d', $validated['time_hour'], $validated['time_minute']));
            $endsAt = $startsAt->copy()->addMinutes((int) $validated['duration_minutes']);

            // Doble toque / doble envío: si esta misma persona ya pidió exactamente este horario hace
            // un momento, se le muestra esa reserva en vez de crear una segunda en otra habitación.
            $knownCustomer = Customer::where('phone_e164', Phone::toE164($validated['phone']))->first();
            if ($knownCustomer) {
                $duplicate = Booking::where('customer_id', $knownCustomer->id)
                    ->where('starts_at', $startsAt)
                    ->where('duration_minutes', (int) $validated['duration_minutes'])
                    ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
                    ->where('created_at', '>=', now()->subMinutes(3))
                    ->latest('id')->first();
                if ($duplicate) {
                    return redirect()->route('catalog.booked', $duplicate->pass_token);
                }
            }

            // Estadía corta por cierre: sale a la hora de cierre y el cliente
            // tiene que aceptarlo explícitamente en el formulario.
            $short = $shortStays->quoteForCategory((int) $validated['room_category_id'], $startsAt, (int) $validated['duration_minutes'], 'catalogo');
            if ($short) {
                $endsAt = $short['closes_at']->copy();
                if (empty($validated['short_stay_accept'])) {
                    return back()->withInput()->withErrors(['duration_minutes' => 'A esa hora queda poco para el cierre: la estadía termina a las '.$short['closes_at']->copy()->timezone('America/Santiago')->format('H:i').'. Marca que aceptas salir a esa hora para continuar.']);
                }
            }

            $room = $this->findAvailableRoom($validated['room_category_id'], $validated['room_id'] ?? null, $startsAt, $endsAt, $availability, $rateRules, $short);

            if (! $room) {
                return back()->withInput()->withErrors(['duration_minutes' => 'No hay Playrooms libres de ese tipo para ese horario — prueba otra fecha, hora o duración.']);
            }

            $phone = Phone::toE164($validated['phone']);
            $customerName = trim($validated['first_name'].' '.$validated['last_name']);
            $customer = Customer::firstOrCreate(
                ['phone_e164' => $phone],
                ['name' => $customerName, 'email' => $validated['email'] ?? null, 'birth_date' => $validated['birth_date'] ?? null]
            );
            // Cliente que ya existía sin correo: se completa con el de esta reserva.
            if (! $customer->email) {
                $customer->update(['email' => $validated['email']]);
            }
            if ($customer->blacklist_status === 'blocked') {
                return back()->withInput()->withErrors(['phone' => 'No es posible solicitar la reserva con los datos ingresados.']);
            }
            // Constancia del consentimiento: aceptó el aviso ahora, y el permiso de
            // promociones solo se enciende si lo marcó (no se apaga por no marcarlo).
            $consent = ['privacy_accepted_at' => now()];
            if (! empty($validated['marketing_opt_in']) && ! $customer->marketing_opt_in) {
                $consent += ['marketing_opt_in' => true, 'marketing_opt_in_at' => now()];
            }
            $customer->update($consent);
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
                    'hold_minutes' => Booking::HOLD_MINUTES,
                    'short_stay' => (bool) $short,
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
            $combos = Combo::with('items.product')->where('is_active', true)->where('show_in_catalog', true)->whereIn('id', $comboQuantities->keys())->get()->keyBy('id');
            foreach ($comboQuantities as $comboId => $quantity) {
                if (! isset($combos[$comboId]) || $combos[$comboId]->isOutOfStock()) {
                    if (isset($combos[$comboId])) {
                        $soldOut = true;
                    }

                    continue;
                }
                try {
                    $consumption->addCombo($booking, $combos[$comboId], $quantity, null, (array) request()->input("combo_choices.$comboId", []));
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

            // Con pago en línea, el cliente sigue directo a Mercado Pago; si el
            // cobro no se puede abrir, catalog.pay lo devuelve a su reserva.
            if (app(MercadoPagoClient::class)->enabled()) {
                return redirect()->route('catalog.pay', $booking->pass_token);
            }

            return redirect()->route('catalog.booked', $booking->pass_token);
        } finally {
            $lock->release();
        }
    }

    public function booked(Request $request, string $token): View
    {
        Booking::releaseExpired();
        $booking = Booking::with(['room.category', 'addons'])->where('pass_token', $token)->firstOrFail();

        // Al volver de Mercado Pago no se espera el aviso: se consulta el pago
        // directo a su API y se registra (idempotente) si es de esta reserva.
        $this->reconcileMercadoPago($request, $booking);

        // Mismo QR del pase PDF: recepción lo escanea y abre la ficha.
        $qr = (new Builder(
            writer: new PngWriter,
            data: route('bookings.validate', $booking->code),
            size: 220,
            margin: 0,
        ))->build();

        return view('catalog.booked', [
            'booking' => $booking,
            'qrDataUri' => $qr->getDataUri(),
            'passUrl' => route('bookings.pass.pdf', $booking->pass_token),
            'pay' => config('services.hh_payment'),
            'mpEnabled' => app(MercadoPagoClient::class)->enabled(),
        ]);
    }

    private function reconcileMercadoPago(Request $request, Booking $booking): void
    {
        $paymentId = (string) $request->query('payment_id', '');
        $mp = app(MercadoPagoClient::class);
        if ($paymentId === '' || ! ctype_digit($paymentId) || ! $mp->enabled() || $booking->booking_status !== 'PENDIENTE_PAGO') {
            return;
        }

        try {
            $payment = $mp->payment($paymentId);
            if (($payment['external_reference'] ?? null) !== $booking->code) {
                return;
            }
            app(MercadoPagoPayments::class)->record($payment);
            $booking->refresh()->load(['room.category', 'addons']);
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago: no se pudo confirmar el pago al volver', ['booking' => $booking->code, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Chequeo en vivo desde el formulario -- antes el cliente solo se
     * enteraba de que no había disponibilidad DESPUÉS de llenar sus datos
     * de contacto y enviar todo. Usa la misma búsqueda que store(), así que
     * "disponible" acá nunca contradice el resultado real del envío (salvo
     * que otra reserva tome el horario justo entre medio).
     */
    public function checkAvailability(Request $request, AvailabilityChecker $availability, RateRuleResolver $rateRules, ShortStayPricer $shortStays): JsonResponse
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

        $short = $shortStays->quoteForCategory((int) $validated['room_category_id'], $startsAt, (int) $validated['duration_minutes'], 'catalogo');
        if ($short) {
            $endsAt = $short['closes_at']->copy();
        }

        $room = $this->findAvailableRoom($validated['room_category_id'], $validated['room_id'] ?? null, $startsAt, $endsAt, $availability, $rateRules, $short);

        return response()->json([
            'available' => (bool) $room,
            'short_stay' => $short ? [
                'closes_at' => $short['closes_at']->copy()->timezone('America/Santiago')->format('H:i'),
                'minutes_label' => TimeFormat::minutes($short['minutes']),
                'price' => $short['price'],
                'full_price' => $short['full_price'],
            ] : null,
        ]);
    }

    /**
     * Horas de llegada que de verdad se pueden pedir ese día para esa categoría: las que caen dentro
     * del horario de alguna tarifa vigente (no todas las del reloj). Se calcula con la misma
     * regla que usa la reserva, con la duración más corta que se vende, y se guarda unos minutos.
     */
    public function times(Request $request, RateRuleResolver $rateRules, ShortStayPricer $shortStays): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'room_category_id' => ['required', 'exists:room_categories,id'],
        ]);
        $category = (int) $validated['room_category_id'];
        $date = Carbon::parse($validated['date'])->toDateString();

        $minDuration = RateRulePrice::where('room_category_id', $category)->min('duration_minutes');
        if (! $minDuration) {
            return response()->json(['times' => []]);
        }

        // La versión de las reglas de estadía corta entra en la llave: al
        // cambiarlas en Administración, el catálogo no sigue con horas viejas.
        $shortVersion = md5(json_encode(ShortStaySetting::current()->only(['enabled', 'catalog_enabled', 'min_minutes', 'max_minutes'])));
        $times = Cache::remember("catalog-times:{$date}:{$category}:{$minDuration}:{$shortVersion}", 300, function () use ($date, $category, $minDuration, $rateRules, $shortStays) {
            // Son 96 horarios por día: lo que no cambia entre ellos se consulta una sola vez.
            $pricedRules = RateRulePrice::where('room_category_id', $category)->where('duration_minutes', (int) $minDuration)->pluck('rate_rule_id')->all();
            $offersShortStay = ShortStaySetting::current()->offeredOn('catalogo');
            $valid = [];
            for ($minute = 0; $minute < 24 * 60; $minute += 15) {
                $start = Carbon::parse($date)->addMinutes($minute);
                $rate = $rateRules->resolve($start, $start->copy()->addMinutes((int) $minDuration));
                if ($rate && in_array($rate->id, $pricedRules, true)) {
                    $valid[] = $start->format('H:i');
                } elseif ($offersShortStay && $shortStays->quoteForCategory($category, $start, (int) $minDuration, 'catalogo')) {
                    // Cerca del cierre: se puede pedir como estadía corta.
                    $valid[] = $start->format('H:i');
                }
            }

            return $valid;
        });

        // Hoy no se ofrecen las horas que ya pasaron (se filtra fuera del caché).
        if ($date === now()->toDateString()) {
            $limit = now()->subMinutes(5)->format('H:i');
            $times = array_values(array_filter($times, fn ($t) => $t >= $limit));
        }

        return response()->json(['times' => $times]);
    }

    private function findAvailableRoom(int $categoryId, ?int $roomId, Carbon $startsAt, Carbon $endsAt, AvailabilityChecker $availability, RateRuleResolver $rateRules, ?array $shortStay = null): ?Room
    {
        // Una retención vencida no debe seguir bloqueando la habitación.
        Booking::releaseExpired();

        // Usada tanto por el chequeo en vivo (checkAvailability) como por
        // store() -- si acá falta alguna regla que store() sí aplica
        // después (categoría deshabilitada, fuera de la ventana operativa
        // de la tarifa), el chequeo en vivo miente diciendo "disponible" y
        // el envío real rechaza con un error de tarifa que no tiene nada
        // que ver con lo que el cliente vio en pantalla.
        // La estadía corta ya se validó contra la tarifa (ShortStayPricer):
        // termina al cierre, así que su largo no coincide con un precio.
        $rate = $shortStay ? null : $rateRules->resolve($startsAt, $endsAt);
        if (! $shortStay && (! $rate || ! $rate->prices()
            ->where('room_category_id', $categoryId)
            ->where('duration_minutes', (int) $startsAt->diffInMinutes($endsAt, false))
            ->exists())) {
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

        // Si el cliente no eligió una pieza concreta, se llena primero el ala norte.
        return $roomQuery->orderBy('name')->get()
            ->sortBy(fn (Room $r) => $r->wingPriority())->values()
            ->first(fn (Room $r) => $availability->isAvailable($r, $startsAt, $endsAt));
    }
}
