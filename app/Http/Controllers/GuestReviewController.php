<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\GuestReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página pública de "¿cómo fue tu experiencia?" -- 4-5 estrellas se manda
 * directo a la ficha de Google (config('services.hh_reviews.google_review_link')),
 * 0-3 se queda adentro pidiendo un comentario, para poder hacer algo con la
 * queja antes de que se vuelva una reseña pública. {code} es opcional: el
 * QR fijo de recepción no viene de una reserva puntual, el link mandado
 * después del check-out sí (así queda asociada a esa estadía).
 */
class GuestReviewController extends Controller
{
    private const LOW_RATING_MAX = 3;

    public function create(?string $code = null): View
    {
        $booking = $code ? Booking::where('code', $code)->first() : null;

        return view('reviews.create', ['booking' => $booking]);
    }

    public function store(Request $request, ?string $code = null): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $booking = $code ? Booking::where('code', $code)->first() : null;

        $review = GuestReview::create([
            'booking_id' => $booking?->id,
            'rating' => $validated['rating'],
        ]);

        // {review} en las rutas de comentario es solo el ID autoincremental
        // -- sin esta marca de sesión, cualquiera que adivinara/probara IDs
        // consecutivos podía sobrescribir el comentario de otra opinión.
        $request->session()->put('review_access.'.$review->id, true);

        $googleLink = config('services.hh_reviews.google_review_link');

        if ($validated['rating'] > self::LOW_RATING_MAX && $googleLink) {
            return redirect()->away($googleLink);
        }

        if ($validated['rating'] > self::LOW_RATING_MAX) {
            return redirect()->route('reviews.create')->with('status', '¡Gracias por tu opinión!');
        }

        return redirect()->route('reviews.comment.edit', $review);
    }

    public function editComment(Request $request, GuestReview $review): View|RedirectResponse
    {
        if ($review->rating > self::LOW_RATING_MAX || ! $request->session()->get('review_access.'.$review->id)) {
            return redirect()->route('reviews.create');
        }

        return view('reviews.comment', ['review' => $review]);
    }

    public function updateComment(Request $request, GuestReview $review): View|RedirectResponse
    {
        if (! $request->session()->get('review_access.'.$review->id)) {
            return redirect()->route('reviews.create');
        }

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update(['comment' => $validated['comment'] ?? null]);

        return view('reviews.thanks');
    }
}
