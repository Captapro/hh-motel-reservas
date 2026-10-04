<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\Combo;
use App\Models\Product;
use App\Models\Room;
use App\Models\UpsellOffer;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingAllocationService;
use App\Services\Booking\ConsumptionService;
use App\Services\Pricing\RateRuleResolver;
use App\Services\Pricing\UpsellResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingCheckInController extends Controller
{
    private const TERMINAL = ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'];

    public function show(string $code, UpsellResolver $upsells): View|RedirectResponse
    {
        $booking = Booking::with(['room.category', 'customer', 'addons', 'guests'])->where('code', $code)->firstOrFail();

        if ($booking->checked_in_at || in_array($booking->booking_status, self::TERMINAL, true)) {
            return redirect()->route('reservations.show', $booking->code);
        }

        $products = Product::where('is_active', true)->orderBy('display_order')->get();
        $combos = Combo::with('items.product')->where('is_active', true)->orderBy('display_order')->get();

        $applicable = $upsells->applicableFor($booking->room, $booking->duration_minutes, $booking->starts_at, $booking->id)
            ->map(fn ($u) => [
                'id' => $u['offer']->id,
                'type' => $u['offer']->type,
                'label' => $u['label'],
                'price' => $u['price'],
                'detail' => $u['detail'],
            ]);

        return view('reservations.checkin', [
            'booking' => $booking,
            'products' => $products,
            'combos' => $combos,
            'upsells' => $applicable,
        ]);
    }

    public function store(Request $request, string $code, ConsumptionService $consumption, AvailabilityChecker $availability, RateRuleResolver $rateRules): RedirectResponse
    {
        return DB::transaction(function () use ($request, $code, $consumption, $availability, $rateRules) {
            // Antes esto bloqueaba TODAS las habitaciones (Room::orderBy('id')
            // ->lockForUpdate()->get() sin filtro) "por si" el check-in traía
            // un upgrade -- de paso dejaba a cualquier otra operación sobre
            // CUALQUIER pieza (crear una reserva nueva, extender otra, etc.)
            // esperando a que este check-in terminara, aunque fueran piezas
            // sin ninguna relación entre sí. Alcanza con bloquear la pieza
            // propia de esta reserva; los posibles destinos de upgrade ya
            // los bloquea BookingAllocationService::upgrade() por su cuenta,
            // acotado a la categoría destino real.
            $booking = Booking::with('room')->where('code', $code)->lockForUpdate()->firstOrFail();
            Room::whereKey($booking->room_id)->lockForUpdate()->get();

            return $this->checkInLocked($booking, $consumption, $availability, $rateRules, $request);
        }, 3);
    }

    private function checkInLocked(Booking $booking, ConsumptionService $consumption, AvailabilityChecker $availability, RateRuleResolver $rateRules, Request $request): RedirectResponse
    {
        if ($booking->checked_in_at) {
            return redirect()->route('reservations.show', $booking->code);
        }

        // Defensa además del filtro que ya hace show() — si llega un envío
        // directo a este endpoint (pestaña vieja, reenvío) para una reserva
        // que ya quedó cancelada/vencida, no hay que dejarla pasar igual.
        if (in_array($booking->booking_status, self::TERMINAL, true)) {
            return redirect()->route('reservations.show', $booking->code);
        }

        // La reserva pudo haberse hecho con la pieza disponible, pero si
        // después quedó en aseo/mantención (por ejemplo el huésped anterior
        // salió tarde) no se puede habilitar hasta reactivarla.
        if ($booking->room->operational_status !== 'activa') {
            return back()->withInput()->withErrors([
                'booking' => 'La habitación '.$booking->room->name.' está en estado "'.$booking->room->operational_status.'" — hay que reactivarla antes de poder hacer el check-in.',
            ]);
        }

        // operational_status no refleja si hay alguien adentro ahora mismo
        // (eso se calcula solo, ver RoomBoardService) -- si el huésped
        // anterior se pasó de horario y todavía no hizo check-out, no se
        // puede meter a otro encima.
        if ($this->roomHasGuestInside($booking->room, $booking->id)) {
            return back()->withInput()->withErrors([
                'booking' => 'La habitación '.$booking->room->name.' todavía tiene un huésped adentro (sin check-out) — hay que finalizar esa reserva antes de hacer este check-in.',
            ]);
        }

        $validated = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'combo_quantities' => ['nullable', 'array'],
            'combo_quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'accepted_upsells' => ['nullable', 'array'],
            'accepted_upsells.*' => ['integer', 'exists:upsell_offers,id'],
            'guest_names' => ['nullable', 'string'],
        ]);

        // El registro de acompañantes es obligatorio para mayores de 18 —
        // no bloquea crear/gestionar la reserva, pero sí habilitar la
        // habitación: sin el registro completo no se puede hacer check-in.
        // "Personas" incluye al titular (ya identificado por su cuenta), así
        // que hacen falta guests_count - 1 acompañantes con nombre y apellido.
        $guestNames = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['guest_names'] ?? ''))));
        $requiredGuests = max(0, $booking->guests_count - 1);
        if (count($guestNames) < $requiredGuests) {
            return back()->withInput()->withErrors([
                'guest_names' => 'Esta reserva es para '.$booking->guests_count.' personas — falta registrar nombre y apellido de '.($requiredGuests - count($guestNames)).' acompañante(s) más antes de poder hacer el check-in.',
            ]);
        }

        $arrival = now();
        if ($arrival->lt($booking->starts_at)
            && ! $availability->isAvailable($booking->room, $arrival, $booking->ends_at, $booking->id)) {
            return back()->withInput()->withErrors([
                'booking' => 'No se puede adelantar el check-in: hay otra reserva o un margen de aseo pendiente antes del horario reservado.',
            ]);
        }

        // Resolver el horario antes de registrar la entrada evita confirmar
        // una estancia cuya salida ya pasó y que no puede extenderse.
        $delay = (int) $booking->starts_at->diffInMinutes($arrival, false);
        $endsAt = $booking->ends_at;
        if ($delay > 0) {
            $extendedEnd = $endsAt->copy()->addMinutes($delay);
            if ($rateRules->resolve($booking->starts_at, $extendedEnd)
                && $availability->isAvailable($booking->room, $booking->starts_at, $extendedEnd, $booking->id)) {
                $endsAt = $extendedEnd;
            }
            if ($endsAt->lte($arrival)) {
                return back()->withInput()->withErrors([
                    'booking' => 'El horario de esta reserva ya terminó y no se puede extender. Reprograma la reserva antes de hacer el check-in.',
                ]);
            }
        }

        $old = $booking->only(['booking_status', 'checked_in_at', 'ends_at']);

        $booking->update([
            'checked_in_at' => $arrival,
            'ends_at' => $endsAt,
            'booking_status' => in_array($booking->booking_status, ['PENDIENTE_PAGO', 'RETENIDA', 'BORRADOR', 'CONFIRMADA'], true)
                ? 'CHECK_IN'
                : $booking->booking_status,
        ]);

        AuditLog::record(auth()->id(), 'reserva.check_in', 'Booking', $booking->id, $old, $booking->only(['booking_status', 'checked_in_at', 'ends_at']));

        // El check-in es la fuente de verdad del registro de acompañantes —
        // reemplaza lo que hubiera quedado (de la creación, si algo) por lo
        // recién confirmado con el huésped presente.
        $booking->guests()->delete();
        foreach ($guestNames as $name) {
            BookingGuest::create(['booking_id' => $booking->id, 'name' => $name]);
        }

        $this->applyUpsells($booking, $validated['accepted_upsells'] ?? [], $consumption, $availability, $rateRules);

        $status = $delay > 0 ? "Check-in registrado — {$delay} min de atraso." : 'Check-in registrado.';

        try {
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
            $roomName = $booking->fresh()->room->name;

            return redirect()->route('rooms.board')
                ->with('status', $status.' — '.$roomName.'. '.$e->getMessage().' Ese consumo no se pudo agregar — cárgalo manualmente.');
        }

        $roomName = $booking->fresh()->room->name;

        return redirect()->route('rooms.board')->with('status', $status.' — '.$roomName.'.');
    }

    /**
     * @param  array<int>  $upsellIds
     */
    private function applyUpsells(Booking $booking, array $upsellIds, ConsumptionService $consumption, AvailabilityChecker $availability, RateRuleResolver $rateRules): void
    {
        if (! $upsellIds) {
            return;
        }

        $offers = UpsellOffer::active()->with('combo')->whereIn('id', $upsellIds)
            ->orderByRaw("type = 'time_extension' desc")
            ->get();

        foreach ($offers as $u) {
            if ($u->type === 'time_extension' && $u->extra_minutes) {
                $newEndsAt = $booking->ends_at->copy()->addMinutes($u->extra_minutes);
                // Si la extensión pasa la hora de cierre o choca con la
                // siguiente reserva de la misma pieza, se omite (y no se
                // cobra) en vez de dejar el check-in a medio hacer con un
                // error de la restricción EXCLUDE de Postgres.
                if ($rateRules->resolve($booking->starts_at, $newEndsAt)
                    && $availability->isAvailable($booking->room, $booking->starts_at, $newEndsAt, $booking->id)) {
                    $booking->update(['ends_at' => $newEndsAt]);
                    $consumption->addCustom($booking, $u->name, (int) $u->price, auth()->id());
                }
            } elseif ($u->type === 'category_upgrade' && $u->to_room_category_id) {
                app(BookingAllocationService::class)->upgrade($booking, $u, auth()->id());
            } elseif ($u->type === 'combo' && $u->combo) {
                try {
                    $consumption->addCombo($booking, $u->combo, 1, auth()->id());
                } catch (InsufficientStockException $e) {
                    // sin stock — se omite
                }
            }
        }
    }

    /**
     * $excludeBookingId es la reserva que está por hacer check-in ahora
     * mismo -- nunca cuenta contra sí misma. isAvailable() solo pelea por
     * el HORARIO agendado; esto pelea por quién está adentro DE VERDAD en
     * este instante, que puede no coincidir si el huésped anterior se pasó
     * de hora.
     */
    private function roomHasGuestInside(Room $room, ?int $excludeBookingId = null): bool
    {
        return $room->bookings()
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereNotIn('booking_status', self::TERMINAL)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->exists();
    }
}
