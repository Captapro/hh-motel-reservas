<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\GuestReview;
use App\Services\Integrations\GhlReviewRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página pública de "¿cómo fue tu experiencia?" -- 4-5 estrellas se manda
 * directo a la ficha de Google (config('services.hh_reviews.google_review_link')),
 * 0-3 se queda adentro pidiendo un comentario, para poder hacer algo con la
 * queja antes de que se vuelva una reseña pública. {token} es opcional: el
 * QR fijo de recepción no viene de una reserva puntual, el link mandado
 * después del check-out sí (así queda asociada a esa estadía). Es el
 * pass_token aleatorio y NO el código de la reserva: el código es correlativo
 * por día, y con él cualquiera podía opinar por otro cliente y dispararle el
 * cupón por WhatsApp. Un enlace antiguo con código cae como opinión sin reserva.
 */
class GuestReviewController extends Controller
{
    private const LOW_RATING_MAX = 3;

    public function create(?string $token = null): View
    {
        $booking = $this->bookingFor($token);

        return view('reviews.create', ['booking' => $booking]);
    }

    public function store(Request $request, GhlReviewRequests $rewards, ?string $token = null): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $booking = $this->bookingFor($token);

        // Una opinión por estadía: si vuelve a abrir el link, se usa la que ya dejó.
        $review = ($booking ? GuestReview::where('booking_id', $booking->id)->first() : null)
            ?? GuestReview::create([
                'booking_id' => $booking?->id,
                'rating' => $validated['rating'],
            ]);

        // {review} en las rutas de comentario es solo el ID autoincremental
        // -- sin esta marca de sesión, cualquiera que adivinara/probara IDs
        // consecutivos podía sobrescribir el comentario de otra opinión.
        $request->session()->put('review_access.'.$review->id, true);

        // Opinión desde el link de su estadía: 10% para la próxima visita,
        // cualquiera sea la nota (el premio es por opinar, no por la reseña en Google).
        if ($booking) {
            $rewards->rewardFor($review);
        }

        $googleLink = config('services.hh_reviews.google_review_link');

        if ($review->rating > self::LOW_RATING_MAX) {
            // QR fijo de recepción (sin reserva): directo a Google, como siempre.
            if (! $booking && $googleLink) {
                return redirect()->away($googleLink);
            }

            return $booking
                ? redirect()->route('reviews.thanks', $review)
                : redirect()->route('reviews.create')->with('status', '¡Gracias por tu opinión!');
        }

        return redirect()->route('reviews.comment.edit', $review);
    }

    private function bookingFor(?string $token): ?Booking
    {
        return $token ? Booking::where('pass_token', $token)->first() : null;
    }

    /** Gracias, con el cupón de la próxima visita y (si le gustó) la invitación opcional a Google. */
    public function thanks(Request $request, GuestReview $review): View|RedirectResponse
    {
        if (! $request->session()->get('review_access.'.$review->id)) {
            return redirect()->route('reviews.create');
        }

        return view('reviews.thanks', [
            'coupon' => $review->coupon,
            'review' => $review,
            'googleLink' => $review->rating > self::LOW_RATING_MAX ? config('services.hh_reviews.google_review_link') : null,
        ]);
    }

    /** Registra que tocó «Opinar en Google» y lo lleva a la ficha (para medir cuántos llegan). */
    public function toGoogle(Request $request, GuestReview $review): RedirectResponse
    {
        $googleLink = config('services.hh_reviews.google_review_link');

        if (! $googleLink || $review->rating <= self::LOW_RATING_MAX || ! $request->session()->get('review_access.'.$review->id)) {
            return redirect()->route('reviews.create');
        }

        if (! $review->google_clicked_at) {
            $review->forceFill(['google_clicked_at' => now()])->save();
        }

        return redirect()->away($googleLink);
    }

    public function editComment(Request $request, GuestReview $review): View|RedirectResponse
    {
        if ($review->rating > self::LOW_RATING_MAX || ! $request->session()->get('review_access.'.$review->id)) {
            return redirect()->route('reviews.create');
        }

        return view('reviews.comment', ['review' => $review]);
    }

    public function updateComment(Request $request, GuestReview $review): RedirectResponse
    {
        if (! $request->session()->get('review_access.'.$review->id)) {
            return redirect()->route('reviews.create');
        }

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update(['comment' => $validated['comment'] ?? null]);

        return redirect()->route('reviews.thanks', $review);
    }
}
