<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;

class BookingPassController extends Controller
{
    private function pdf(string $token)
    {
        $booking = Booking::with(['room.category', 'customer', 'payments', 'addons'])->where('pass_token', $token)->firstOrFail();

        // Antes esto era un "▣ ▦ ▣" decorativo, no un QR de verdad -- ahora
        // codifica la ficha interna de la reserva, para que recepción la
        // escanee con /reservas/escanear y la identifique al toque, sin
        // buscarla a mano.
        $qr = (new Builder(
            writer: new PngWriter(),
            data: route('reservations.show', $booking->code),
            size: 220,
            margin: 0,
        ))->build();

        return Pdf::loadView('reservations.pass-pdf', [
            'booking' => $booking,
            'paid' => $booking->paidAmount(),
            'balance' => $booking->balanceDue(),
            'logo' => public_path('images/hh-motel-logo.png'),
            'qrDataUri' => $qr->getDataUri(),
        ])->setPaper([0, 0, 396, 720], 'portrait');
    }

    public function preview(string $token): Response
    {
        return $this->pdf($token)->stream('pase.pdf');
    }

    public function download(string $token): Response
    {
        return $this->pdf($token)->download('pase.pdf');
    }
}
