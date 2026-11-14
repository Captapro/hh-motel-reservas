<?php

namespace App\Http\Controllers;

use App\Services\Booking\AseoReportService;
use Carbon\Carbon;
use Illuminate\View\View;

class DailyAseoController extends Controller
{
    public function show(AseoReportService $reports, ?string $date = null): View
    {
        $day = $date ? Carbon::parse($date, 'America/Santiago')->startOfDay() : now('America/Santiago')->startOfDay();

        $rows = $reports->rowsBetween($day->copy(), $day->copy()->endOfDay());

        // Carga de ropa de cama — solo categorías con cama (GO son cápsulas
        // sin cama, no generan sábanas que lavar). Un checkout = un juego de
        // ropa de cama a reponer. Se agrupa por habitación puntual (no solo
        // por categoría) para que la carga siempre quede trazable a una
        // habitación concreta — si dos PLUS salieron hoy, hay que saber
        // cuáles, no solo que fueron "2 PLUS".
        $linenRows = $reports->linenRows($rows);
        $linenByRoom = $linenRows->groupBy(fn (array $row) => $row['room']->id)
            ->map(fn ($group) => [
                'room' => $group->first()['room'],
                'count' => $group->count(),
            ])
            ->sortBy(fn ($entry) => $entry['room']->name)
            ->values();

        return view('aseo.daily', [
            'day' => $day,
            'rows' => $rows,
            'linenTotal' => $linenRows->count(),
            'linenByRoom' => $linenByRoom,
            'pieces' => $reports->piecesByRoom($rows),
        ]);
    }
}
