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

        $bookings = Booking::with(['room.category', 'customer'])
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

        return view('calendar.index', compact('view', 'date', 'rangeStart', 'rangeEnd', 'bookings', 'days', 'previous', 'next', 'pastBookings', 'upcomingBookings'));
    }
}
