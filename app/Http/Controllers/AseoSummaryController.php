<?php

namespace App\Http\Controllers;

use App\Services\Booking\AseoReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AseoSummaryController extends Controller
{
    public function week(AseoReportService $reports, ?string $date = null): View
    {
        $anchor = $date ? Carbon::parse($date, 'America/Santiago') : now('America/Santiago');
        $start = $anchor->copy()->startOfWeek();
        $end = $anchor->copy()->endOfWeek();

        return view('aseo.summary', $this->buildSummary($reports, $start, $end, [
            'period' => 'week',
            'title' => 'Semana del '.$start->format('d/m').' al '.$end->format('d/m/Y'),
            'prevUrl' => route('aseo.week', $start->copy()->subWeek()->toDateString()),
            'nextUrl' => route('aseo.week', $start->copy()->addWeek()->toDateString()),
            'currentUrl' => route('aseo.week'),
            'isCurrent' => now('America/Santiago')->between($start, $end),
        ]));
    }

    public function month(AseoReportService $reports, ?string $date = null): View
    {
        $anchor = $date ? Carbon::parse($date, 'America/Santiago') : now('America/Santiago');
        $start = $anchor->copy()->startOfMonth();
        $end = $anchor->copy()->endOfMonth();

        return view('aseo.summary', $this->buildSummary($reports, $start, $end, [
            'period' => 'month',
            'title' => ucfirst($start->locale('es')->isoFormat('MMMM YYYY')),
            'prevUrl' => route('aseo.month', $start->copy()->subMonth()->toDateString()),
            'nextUrl' => route('aseo.month', $start->copy()->addMonth()->toDateString()),
            'currentUrl' => route('aseo.month'),
            'isCurrent' => now('America/Santiago')->isSameMonth($start),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(AseoReportService $reports, Carbon $start, Carbon $end, array $meta): array
    {
        $rows = $reports->rowsBetween($start->copy()->startOfDay(), $end->copy()->endOfDay());
        $linenRows = $reports->linenRows($rows);

        // Una fila por día del rango — aunque no haya habido ninguna salida
        // ese día, así el hueco queda a la vista en vez de simplemente faltar.
        $days = Collection::make();
        for ($cursor = $start->copy()->startOfDay(); $cursor->lte($end); $cursor->addDay()) {
            $dayRows = $rows->filter(fn (array $row) => $row['checkout_at']->isSameDay($cursor));
            $dayLinen = $reports->linenRows($dayRows);

            $days->push([
                'date' => $cursor->copy(),
                'count' => $dayRows->count(),
                'closed' => $dayRows->where('pending', false)->count(),
                'pending' => $dayRows->where('pending', true)->count(),
                'linen' => $dayLinen->count(),
            ]);
        }

        $linenByRoom = $linenRows->groupBy(fn (array $row) => $row['room']->id)
            ->map(fn ($group) => [
                'room' => $group->first()['room'],
                'count' => $group->count(),
            ])
            ->sortByDesc(fn ($entry) => $entry['count'])
            ->values();

        return array_merge($meta, [
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'total' => $rows->count(),
            'totalClosed' => $rows->where('pending', false)->count(),
            'totalPending' => $rows->where('pending', true)->count(),
            'linenTotal' => $linenRows->count(),
            'linenByRoom' => $linenByRoom,
            'pieces' => $reports->piecesByRoom($rows),
        ]);
    }
}
