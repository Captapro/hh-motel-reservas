<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GuestReviewController extends Controller
{
    public function index(): View
    {
        $reviews = GuestReview::with('booking.customer', 'booking.room')
            ->latest()
            ->limit(200)
            ->get();

        return view('admin.reviews.index', [
            'google' => $this->googleSummary(),
            'reviews' => $reviews,
            'lowCount' => GuestReview::where('rating', '<=', 3)->count(),
            'avgRating' => round((float) GuestReview::avg('rating'), 1),
        ]);
    }

    /** Borra todo el historial interno de calificaciones (limpiar datos de prueba). */
    public function destroyAll(Request $request): RedirectResponse
    {
        if (trim((string) $request->input('confirm_text')) !== 'ELIMINAR') {
            return back()->withErrors(['reviews' => 'Para borrar el historial tienes que escribir ELIMINAR en el cuadro de confirmación.']);
        }

        $count = GuestReview::count();
        $summary = ['cantidad' => $count, 'promedio' => round((float) GuestReview::avg('rating'), 1), 'bajas' => GuestReview::where('rating', '<=', 3)->count()];
        GuestReview::query()->delete();
        AuditLog::record(auth()->id(), 'opiniones.borrar_historial', 'GuestReview', 0, $summary, null);

        return back()->with('status', $count.' calificación(es) internas eliminadas.');
    }

    /**
     * Puntaje y cantidad de reseñas reales de Google, leídos de la página del
     * widget de GoHighLevel (viene en sus datos). Se guarda 1 hora; si falla,
     * se reintenta en 5 minutos y la pantalla sigue funcionando sin ese dato.
     *
     * @return array{rating: float, count: int}|null
     */
    private function googleSummary(): ?array
    {
        return \Illuminate\Support\Facades\Cache::remember('google_reviews_summary', 3600, function () {
            try {
                $html = \Illuminate\Support\Facades\Http::timeout(5)->get(config('services.hh_reviews.widget_url'))->body();
                if (preg_match('/"totalRating":\s*([0-9.]+)/', $html, $r) && preg_match('/"totalReviews":\s*(\d+)/', $html, $c)) {
                    return ['rating' => (float) $r[1], 'count' => (int) $c[1]];
                }
            } catch (\Throwable $e) {
                // se muestra sin el dato de Google
            }
            \Illuminate\Support\Facades\Cache::put('google_reviews_summary', null, 300);

            return null;
        });
    }

    /**
     * QR fijo (sin reserva asociada) para pegar en recepción -- apunta al
     * mismo /opinion que se manda por link después del check-out.
     */
    public function qrImage(): Response
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: route('reviews.create'),
            size: 400,
            margin: 12,
            labelText: 'Danos tu opinión',
        ))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }
}
