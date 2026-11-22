<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Booking\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(Request $request, string $code): View
    {
        $booking = Booking::with(['room.category', 'customer', 'payments.paymentMethod', 'addons'])
            ->where('code', $code)->firstOrFail();

        // Mercado Pago no se ofrece: la máquina de recepción cobra como
        // débito o crédito, y así se registra.
        $methods = PaymentMethod::where('is_active', true)->where('code', '!=', 'mercado_pago')->orderBy('name')->get();
        $after = in_array($request->query('after'), ['board', 'checkin', 'pase'], true) ? $request->query('after') : null;

        // Recién creada con abono: se sugiere ese monto en vez del saldo
        // completo — recepción puede ajustarlo si corresponde otra cosa.
        $suggestedAmount = $booking->deposit_amount > 0 && ! session('split')
            ? min((int) $booking->deposit_amount, $booking->balanceDue())
            : $booking->balanceDue();

        return view('payments.create', [
            'booking' => $booking,
            'methods' => $methods,
            'after' => $after,
            'requestToken' => (string) Str::uuid(),
            'suggestedAmount' => $suggestedAmount,
        ]);
    }

    public function store(Request $request, string $code, PaymentService $paymentService): RedirectResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();

        $request->validate(['payment_method_id' => ['required', 'exists:payment_methods,id']]);
        $method = PaymentMethod::findOrFail($request->input('payment_method_id'));

        // Efectivo/transferencia solo traen boleta; débito/crédito solo
        // traen voucher -- no tiene sentido pedir el otro. Para medios sin
        // esta convención clara (Mercado Pago, Otro) basta con uno de los
        // dos, como antes. En efectivo y transferencia la boleta puede venir
        // después (el SII demora, o se entrega cuando llega el cliente): queda
        // en la lista de pendientes de la Caja.
        $referenceRules = match ($method->code) {
            'efectivo', 'transferencia' => [
                'receipt_number' => ['nullable', 'string', 'max:100'],
                'voucher_number' => ['nullable', 'string', 'max:100'],
            ],
            'debito', 'credito' => [
                'voucher_number' => ['required', 'string', 'max:100'],
                'receipt_number' => ['nullable', 'string', 'max:100'],
            ],
            default => [
                'voucher_number' => ['nullable', 'required_without:receipt_number', 'string', 'max:100'],
                'receipt_number' => ['nullable', 'required_without:voucher_number', 'string', 'max:100'],
            ],
        };

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'request_token' => ['required', 'uuid'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'after' => ['nullable', 'in:board,checkin,pase'],
            'overpay' => ['nullable', 'boolean'],
            'overpay_reason' => ['nullable', 'string', 'max:200'],
            ...$referenceRules,
        ], [
            'amount.max' => 'El monto no puede superar el saldo pendiente ($'.number_format($booking->balanceDue(), 0, ',', '.').').',
            'voucher_number.required' => 'Ingresa el N° de voucher.',
            'receipt_number.required' => 'Ingresa el N° de boleta.',
            'voucher_number.required_without' => 'Ingresa el N° de voucher o el N° de boleta (al menos uno de los dos).',
            'receipt_number.required_without' => 'Ingresa el N° de voucher o el N° de boleta (al menos uno de los dos).',
        ]);

        // Si recepción no anota una referencia propia (ej. N° de operación de
        // una transferencia), generamos una nosotros -- así todo pago manual
        // queda igual de rastreable, sin depender de que alguien la tipee.
        $externalId = ($validated['external_id'] ?? null) ?: 'REC-'.now()->format('ymdHis').'-'.strtoupper(Str::random(4));

        // Dos veces en dos días recepción anotó un N° de voucher que ya estaba
        // usado (PLUS 307 el 21/11, PLUS 307 el 22/11): el cruce con Mercado
        // Pago deja de calzar y parece que falta plata aunque esté todo
        // cobrado. Se avisa antes de guardar, sin bloquear: un mismo cobro de
        // la máquina puede pagar dos piezas y ahí el voucher se repite de
        // verdad. El correlativo del voucher es del mes, así que se busca en
        // el mes corrido.
        $voucher = trim((string) ($validated['voucher_number'] ?? ''));
        if ($voucher !== '' && ctype_digit($voucher) && ! $request->boolean('voucher_ok')) {
            $repetido = Payment::with(['booking:id,code,room_id', 'booking.room:id,name'])
                ->where('status', 'aprobado')
                ->whereRaw('TRIM(voucher_number) = ?', [$voucher])
                ->where('created_at', '>=', now()->startOfMonth())
                ->orderByDesc('created_at')
                ->first();
            if ($repetido) {
                $donde = $repetido->booking?->code
                    ? $repetido->booking->code.($repetido->booking->room?->name ? ' ('.$repetido->booking->room->name.')' : '')
                    : 'otro pago';

                return back()->withInput()->with('voucher_repetido', true)->withErrors(['voucher_number' =>
                    'El voucher '.$voucher.' ya está anotado en '.$donde.' por $'.number_format((int) $repetido->amount, 0, ',', '.')
                    .' ('.$repetido->created_at->format('d/m H:i').'). Mira el número en el voucher del POS: si te equivocaste, corrígelo.',
                ]);
            }
        }

        $overpayReason = null;
        if ($request->boolean('overpay')) {
            $overpayReason = trim((string) ($validated['overpay_reason'] ?? ''));
            if ($overpayReason === '') {
                return back()->withInput()->with('overpay_offer', true)->withErrors(['overpay_reason' => 'Escribe el motivo del cobro de más (ej. se cobró sin el descuento).']);
            }
        }

        try {
            $paymentService->register(
                $booking,
                $method,
                (int) $validated['amount'],
                $externalId,
                $validated['notes'] ?? null,
                auth()->id(),
                $validated['voucher_number'] ?? null,
                $validated['receipt_number'] ?? null,
                $validated['request_token'],
                $overpayReason,
            );
        } catch (PaymentExceedsBalanceException $e) {
            return back()->withInput()->with('overpay_offer', true)->withErrors(['amount' => $e->getMessage()]);
        }

        // Pago dividido (ej. parte débito y parte efectivo): si todavía falta
        // para el Playroom, se vuelve al formulario con el resto sugerido en
        // vez de rebotar con error desde el check-in.
        $paidLine = $method->name.' · $'.number_format($validated['amount'], 0, ',', '.');
        if (in_array($validated['after'] ?? null, ['checkin', 'pase'], true) && ($rest = $booking->fresh()->roomBalanceDue()) > 0) {
            return redirect()->route('payments.create', ['code' => $booking->code, 'after' => $validated['after']])
                ->with('split', '✅ Registrado: '.$paidLine.'. Falta $'.number_format($rest, 0, ',', '.').' — registra el otro medio de pago.');
        }

        if (($validated['after'] ?? null) === 'checkin') {
            return redirect()->route('bookings.checkin.show', ['code' => $booking->code, 'flujo' => 'ingreso'])
                ->with('status', 'Pago registrado — '.$booking->code.' · '.$method->name.' · $'.number_format($validated['amount'], 0, ',', '.').'. Ahora el check-in.');
        }

        if (($validated['after'] ?? null) === 'pase') {
            return redirect()->route('bookings.checkin.show', $booking->code)
                ->with('status', 'Pago registrado — '.$booking->code.' · '.$method->name.' · $'.number_format($validated['amount'], 0, ',', '.').'. Ahora el check-in.');
        }

        if (($validated['after'] ?? null) === 'board') {
            return redirect()->route('rooms.board')
                ->with('status', 'Pago registrado — '.$booking->code.' · '.$method->name.' · $'.number_format($validated['amount'], 0, ',', '.').'.');
        }

        return redirect()->route('reservations.show', $booking->code);
    }
}
