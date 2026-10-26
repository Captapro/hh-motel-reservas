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
        $view = in_array($request->query('view'), ['month', 'week', 'day', 'history'], true) ? $request->query('view') : 'day';
        // "Historial" es una vista aparte (planilla de detalle) que se puede ver
        // por día, semana o mes; las otras vistas son su propio período.
        $period = $view === 'history'
            ? (in_array($request->query('periodo'), ['day', 'week', 'month'], true) ? $request->query('periodo') : 'month')
            : $view;
        $date = Carbon::parse($request->query('date', now()->toDateString()), config('app.timezone'));
        // La grilla del mes incluye días de las semanas vecinas; la planilla, solo el mes.
        $gridMonth = $view === 'month';
        $rangeStart = $period === 'month' ? ($gridMonth ? $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY) : $date->copy()->startOfMonth()) : $date->copy()->startOf($period === 'week' ? 'week' : 'day');
        $rangeEnd = $period === 'month' ? ($gridMonth ? $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY) : $date->copy()->endOfMonth()) : $date->copy()->endOf($period === 'week' ? 'week' : 'day');

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

        $step = $period === 'month' ? '1 month' : ($period === 'week' ? '1 week' : '1 day');
        $previous = $date->copy()->sub($step)->toDateString();
        $next = $date->copy()->add($step)->toDateString();

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

        return view('calendar.index', compact('view', 'period', 'date', 'rangeStart', 'rangeEnd', 'bookings', 'days', 'previous', 'next', 'pastBookings', 'upcomingBookings', 'soon', 'soonStats'));
    }
}
