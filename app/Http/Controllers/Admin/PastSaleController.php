<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\BookingGuest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Room;
use App\Services\Booking\BookingCodeGenerator;
use App\Support\Rut;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ventas que recepción anotó en la planilla en vez del sistema. Se cargan
 * como estadías ya finalizadas y pagadas, con su horario real, SIN tocar el
 * estado de la pieza (puede estar en aseo o ya disponible). No avisa a GHL
 * ni pide opinión: el cliente ya se fue. Los consumos no descuentan stock,
 * porque el conteo del turno ya lo reflejó.
 */
class PastSaleController extends Controller
{
    private const METHODS = ['debito', 'credito', 'efectivo', 'transferencia'];

    public function create(): View
    {
        return view('admin.past-sales.create', [
            'rooms' => Room::with('category')->orderBy('name')->get(),
            'methods' => PaymentMethod::whereIn('code', self::METHODS)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']),
            'recent' => Booking::with(['room', 'customer'])->where('notes', 'like', 'Cargada desde planilla%')->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request, BookingCodeGenerator $codes): RedirectResponse
    {
        $v = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:10'],
            'customer_name' => ['required', 'string', 'max:150'],
            'document_type' => ['required', 'in:rut,pasaporte'],
            'document_number' => ['required', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'companion' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:0'],
            'payment_method' => ['required', 'in:'.implode(',', self::METHODS)],
            'voucher_number' => ['nullable', 'string', 'max:30'],
            'receipt_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($v['document_type'] === 'rut' && ! Rut::isValid($v['document_number'])) {
            throw ValidationException::withMessages(['document_number' => 'El RUT no es válido — revisa el dígito verificador.']);
        }

        $tz = 'America/Santiago';
        $startsAt = Carbon::parse($v['date'].' '.$v['start_time'], $tz);
        $endsAt = Carbon::parse($v['date'].' '.$v['end_time'], $tz);
        if ($endsAt->lte($startsAt)) {
            $endsAt->addDay();
        }
        if ($endsAt->gt(now())) {
            throw ValidationException::withMessages(['end_time' => 'La salida todavía no pasa: esto es solo para estadías que ya terminaron.']);
        }
        // Eloquent guarda la hora tal cual viene, sin convertir: se pasa a la zona de la app.
        $startsAt->setTimezone(config('app.timezone'));
        $endsAt->setTimezone(config('app.timezone'));

        $room = Room::findOrFail($v['room_id']);
        $clash = $room->bookings()->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->first();
        if ($clash) {
            throw ValidationException::withMessages(['room_id' => "En ese horario la {$room->name} ya tiene la reserva {$clash->code}. Revisa que no esté cargada dos veces."]);
        }

        $customer = $this->customer($v);
        $method = PaymentMethod::where('code', $v['payment_method'])->firstOrFail();
        $userId = $request->user()->id;

        $booking = DB::transaction(function () use ($v, $room, $customer, $method, $startsAt, $endsAt, $codes, $userId) {
            $booking = Booking::create([
                'code' => $codes->generate($startsAt),
                'pass_token' => Str::random(40),
                'customer_id' => $customer->id,
                'room_id' => $room->id,
                'created_by' => $userId,
                'origin' => 'presencial',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
                'guests_count' => $v['guests_count'],
                'booking_status' => 'FINALIZADA',
                'payment_status' => $v['amount'] > 0 ? 'PAGADA' : 'NO_PAGADA',
                'price_original' => $v['amount'],
                'extra_guests_fee' => 0,
                'discount_amount' => 0,
                'price_final' => $v['amount'],
                'deposit_amount' => 0,
                'notes' => trim('Cargada desde planilla. '.($v['notes'] ?? '')),
                'checked_in_at' => $startsAt,
                'checked_out_at' => $endsAt,
                // El cliente ya se fue hace rato: no se le pide opinión por WhatsApp.
                'review_requested_at' => now(),
                'checkout_regularized' => true,
            ]);
            if (filled($v['companion'] ?? null)) {
                BookingGuest::create(['booking_id' => $booking->id, 'name' => $v['companion'], 'id_document' => null]);
            }
            if ($v['amount'] > 0) {
                $this->pay($booking, $method, $v['amount'], $v, $userId, $startsAt);
            }
            AuditLog::record($userId, 'reserva.crear_desde_planilla', 'Booking', $booking->id, null, $booking->toArray());

            return $booking;
        });

        return redirect()->route('admin.past_sales.create')
            ->with('status', "Listo: {$booking->code} — {$room->name} · {$customer->name}, finalizada y pagada. La pieza no se tocó.");
    }

    /** Consumo de planilla (ej. una bebida) agregado a una estadía ya cargada, con su propio pago. */
    public function storeAddon(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'booking_code' => ['required', 'string', 'exists:bookings,code'],
            // Producto del inventario, o un monto libre con descripción (ej. diferencia de una hora adicional mal cobrada).
            'product_id' => ['nullable', 'required_without:custom_amount', 'exists:products,id'],
            'custom_description' => ['nullable', 'required_with:custom_amount', 'string', 'max:150'],
            'custom_amount' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'sold_at' => ['required', 'date_format:H:i'],
            'payment_method' => ['required', 'in:'.implode(',', self::METHODS)],
            'voucher_number' => ['nullable', 'string', 'max:30'],
            'receipt_number' => ['nullable', 'string', 'max:30'],
        ]);
        $booking = Booking::where('code', $v['booking_code'])->firstOrFail();
        $product = filled($v['product_id'] ?? null) ? Product::findOrFail($v['product_id']) : null;
        $method = PaymentMethod::where('code', $v['payment_method'])->firstOrFail();
        $userId = $request->user()->id;
        $soldAt = Carbon::parse($booking->starts_at->timezone('America/Santiago')->toDateString().' '.$v['sold_at'], 'America/Santiago');
        if ($soldAt->lt($booking->starts_at)) {
            $soldAt->addDay();
        }
        $soldAt->setTimezone(config('app.timezone'));
        $name = $product?->name ?? $v['custom_description'];
        $amount = (int) ($product?->price ?? $v['custom_amount']) * (int) $v['quantity'];

        DB::transaction(function () use ($booking, $product, $name, $method, $v, $amount, $userId, $soldAt) {
            $addon = BookingAddon::create([
                'booking_id' => $booking->id,
                'product_id' => $product?->id,
                'description' => $v['quantity'] > 1 ? "{$v['quantity']} x {$name}" : $name,
                'quantity' => $v['quantity'],
                'amount' => $amount,
                'added_by' => $userId,
            ]);
            $addon->forceFill(['created_at' => $soldAt, 'updated_at' => $soldAt])->save();
            AuditLog::record($userId, 'reserva.consumo_desde_planilla', 'BookingAddon', $addon->id, null, $addon->toArray());
            $this->pay($booking, $method, $amount, $v, $userId, $soldAt, $v['notes'] ?? null);
        });

        return redirect()->route('admin.past_sales.create')
            ->with('status', "Consumo agregado a {$booking->code}: {$name} ({$v['quantity']}) por $".number_format($amount, 0, ',', '.').' — sin descontar stock.');
    }

    private function pay(Booking $booking, PaymentMethod $method, int $amount, array $v, int $userId, Carbon $at, ?string $note = null): void
    {
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'status' => 'aprobado',
            'voucher_number' => $v['voucher_number'] ?? null,
            'receipt_number' => $v['receipt_number'] ?? null,
            'registered_by' => $userId,
            'notes' => $note ?: 'Cargado desde planilla',
        ]);
        // Con la hora real del cobro, para que la caja del día y el cruce con la máquina calcen.
        $payment->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        AuditLog::record($userId, 'pago.registrar_desde_planilla', 'Payment', $payment->id, null, $payment->toArray());
    }

    private function customer(array $v): Customer
    {
        $rut = $v['document_type'] === 'rut' ? Rut::normalize($v['document_number']) : null;
        $passport = $v['document_type'] === 'pasaporte' ? $v['document_number'] : null;
        $customer = ($rut ? Customer::where('rut', $rut)->first() : null)
            ?? ($passport ? Customer::where('passport_number', $passport)->first() : null);

        return $customer ?? Customer::create([
            'name' => $v['customer_name'],
            'document_type' => $v['document_type'],
            'rut' => $rut,
            'passport_number' => $passport,
            'nationality' => $v['nationality'] ?? null,
        ]);
    }
}
