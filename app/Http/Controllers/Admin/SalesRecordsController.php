<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analyst\HistoricalDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Récords de venta»: qué días se vendió más dentro de lo cargado de las planillas, en
 * total y por día de la semana (el lunes récord, el martes récord…). Solo cifras.
 */
class SalesRecordsController extends Controller
{
    public function index(Request $request, HistoricalDataService $historical): View
    {
        $coverage = $historical->coverage();
        if ($coverage === null) {
            return view('admin.analytics.records', ['coverage' => null]);
        }

        $first = Carbon::parse($coverage['desde'])->format('Y-m');
        $last = Carbon::parse($coverage['hasta'])->format('Y-m');
        $valid = fn (?string $m) => is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : null;
        $fromMonth = $valid($request->query('desde')) ?? $first;
        $toMonth = $valid($request->query('hasta')) ?? $last;
        if ($fromMonth > $toMonth) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        $lastMonth = Carbon::parse($last.'-01');
        $span = fn (int $n) => ['desde' => max($first, $lastMonth->copy()->subMonths($n - 1)->format('Y-m')), 'hasta' => $last];

        return view('admin.analytics.records', [
            'coverage' => $coverage,
            'first' => $first,
            'last' => $last,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'records' => $historical->records(Carbon::parse($fromMonth.'-01')->startOfMonth(), Carbon::parse($toMonth.'-01')->endOfMonth()),
            'partialLastMonth' => Carbon::parse($coverage['hasta'])->lt(Carbon::parse($last.'-01')->endOfMonth()->startOfDay()),
            'lastDay' => Carbon::parse($coverage['hasta']),
            'chips' => [
                'Todo lo cargado' => ['desde' => $first, 'hasta' => $last],
                'Últimos 6 meses' => $span(6),
                'Últimos 3 meses' => $span(3),
                'Último mes' => $span(1),
            ],
        ]);
    }
}
