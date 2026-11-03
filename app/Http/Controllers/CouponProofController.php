<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CouponProof;
use App\Services\Booking\CouponProofs;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Foto del documento de un cupón con verificación (ej. credencial de
 * estudiante). El check-in del computador muestra un QR con un enlace firmado
 * y temporal: recepción lo escanea con cualquier celular, sin iniciar sesión,
 * saca la foto y el check-in se actualiza solo.
 */
class CouponProofController extends Controller
{
    private const QR_LINK_HOURS = 2;

    /** Página del celular para sacar la foto. */
    public function create(Request $request, string $code, CouponProofs $proofs): View
    {
        $booking = Booking::with(['coupon', 'customer', 'room'])->where('code', $code)->firstOrFail();

        return view('reservations.coupon-proof', [
            'booking' => $booking,
            'required' => $proofs->required($booking),
            'proof' => $proofs->latest($booking),
            'action' => $request->routeIs('coupon_proof.phone') ? $request->fullUrl() : route('bookings.coupon_proof.store', $booking->code),
        ]);
    }

    public function store(Request $request, string $code, CouponProofs $proofs): RedirectResponse
    {
        $booking = Booking::with('coupon')->where('code', $code)->firstOrFail();
        abort_unless($proofs->required($booking), 404);

        $request->validate(
            ['photo' => ['required', 'image', 'max:12288']],
            ['photo.required' => 'Saca la foto del documento.', 'photo.image' => 'El archivo tiene que ser una foto.', 'photo.max' => 'La foto es muy pesada (máx. 12 MB).'],
        );
        $phone = $request->routeIs('coupon_proof.phone.store');
        $proofs->store($booking, (string) file_get_contents($request->file('photo')->getRealPath()), $phone ? (int) $request->query('por') ?: null : auth()->id());

        return redirect()->to($phone ? $request->fullUrl() : route('bookings.coupon_proof.create', $booking->code))
            ->with('status', 'Foto guardada. Ya puedes seguir con el check-in en el computador.');
    }

    /** Lo consulta el check-in del computador para saber cuándo llegó la foto. */
    public function status(string $code, CouponProofs $proofs): JsonResponse
    {
        $booking = Booking::where('code', $code)->firstOrFail();

        return response()->json(['ok' => (bool) $proofs->latest($booking)]);
    }

    public function qr(string $code): Response
    {
        $booking = Booking::where('code', $code)->firstOrFail();
        $link = URL::temporarySignedRoute('coupon_proof.phone', now()->addHours(self::QR_LINK_HOURS), ['code' => $booking->code, 'por' => auth()->id()]);
        $result = (new Builder(writer: new PngWriter, data: $link, size: 320, margin: 10))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType(), 'Cache-Control' => 'private, no-store']);
    }

    public function show(string $code): Response
    {
        $booking = Booking::where('code', $code)->firstOrFail();
        $proof = CouponProof::where('booking_id', $booking->id)->latest('id')->firstOrFail();

        return response($proof->contents(), 200, ['Content-Type' => $proof->mime, 'Cache-Control' => 'private, no-store']);
    }

    /** No trae el documento: se quita el cupón y se cobra la diferencia antes del check-in. */
    public function removeCoupon(Request $request, string $code, CouponProofs $proofs): RedirectResponse
    {
        $booking = Booking::with('coupon')->where('code', $code)->firstOrFail();
        if ($booking->checked_in_at || ! $proofs->missing($booking)) {
            return redirect()->route('bookings.checkin.show', $booking->code);
        }

        $label = $booking->coupon_code_snapshot;
        $proofs->removeCoupon($booking, auth()->id());
        $after = $request->input('flujo') === 'ingreso' ? 'checkin' : 'pase';

        return redirect()->route('payments.create', ['code' => $booking->code, 'after' => $after])
            ->with('split', 'Se quitó el cupón '.$label.' (no trajo el documento). Cobra la diferencia para seguir con el check-in.');
    }
}
