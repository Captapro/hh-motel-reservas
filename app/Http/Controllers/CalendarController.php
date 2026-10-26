<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $view = in_array($request->query('view'), ['month', 'week', 'day'], true) ? $request->query('view') : 'day';
        $date = Carbon::parse($request->query('date', now()->toDateString()), config('app.timezone'));
        $rangeStart = $view === 'month' ? $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY) : $date->copy()->startOf($view === 'week' ? 'week' : 'day');
        $rangeEnd = $view === 'month' ? $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY) : $date->copy()->endOf($view === 'week' ? 'week' : 'day');

        $bookings = Booking::with(['room.category', 'customer', 'payments', 'addons'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->orderBy('starts_at')
            ->get();

        $days = collect();
        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
            $days->push(['date' => $cursor->copy(), 'bookings' => $bookings->filter(fn ($booking) => $booking->starts_at->lt($cursor->copy()->endOfDay()) && $booking->ends_at->gt($cursor->copy()->startOfDay()))]);
        }

        $previous = $date->copy()->sub($view === 'month' ? '1 month' : ($view === 'week' ? '1 week' : '1 day'))->toDateString();
        $next = $date->copy()->add($view === 'month' ? '1 month' : ($view === 'week' ? '1 week' : '1 day'))->toDateString();

        // Separadas para que se distinga de un vistazo qué ya pasó de qué
        // todavía no llega -- antes era una sola lista "Historial" mezclando
        // ambas, sin ningún indicio de cuáles ya sucedieron.
        $now = now();
        [$pastBookings, $upcomingBookings] = $bookings->partition(fn ($booking) => $booking->starts_at->lt($now));

        // Próximas reservas desde ahora, sin importar si se mira el mes, la semana o el día.
        $futureBase = Booking::whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'])
            ->whereNull('checked_in_at')->where('starts_at', '>=', $now);
        $soon = (clone $futureBase)->with(['room.category', 'customer'])->orderBy('starts_at')->limit(40)->get();
        $soonStats = [
            'today' => (clone $futureBase)->where('starts_at', '<=', $now->copy()->endOfDay())->count(),
            'tomorrow' => (clone $futureBase)->whereBetween('starts_at', [$now->copy()->addDay()->startOfDay(), $now->copy()->addDay()->endOfDay()])->count(),
            'week' => (clone $futureBase)->where('starts_at', '<=', $now->copy()->addDays(6)->endOfDay())->count(),
            'total' => (clone $futureBase)->count(),
        ];

        return view('calendar.index', compact('view', 'date', 'rangeStart', 'rangeEnd', 'bookings', 'days', 'previous', 'next', 'pastBookings', 'upcomingBookings', 'soon', 'soonStats'));
    }
}
