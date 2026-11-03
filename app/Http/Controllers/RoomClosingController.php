<?php

namespace App\Http\Controllers;

use App\Services\Rooms\RoomClosingLog;
use Carbon\Carbon;
use Illuminate\View\View;

/** Operaciones → Cierre de Playrooms: cómo quedaron al cierre y a qué hora se habilitaron. */
class RoomClosingController extends Controller
{
    public function show(RoomClosingLog $log, ?string $date = null): View
    {
        $latest = RoomClosingLog::lastClosedDate();
        $day = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? Carbon::parse($date, RoomClosingLog::TZ)->startOfDay()->min($latest)
            : $latest;

        return view('aseo.closing', [
            'report' => $log->day($day),
            'history' => $log->history($latest),
            'latest' => $latest,
            'labels' => RoomClosingLog::STATUS_LABELS,
        ]);
    }
}
