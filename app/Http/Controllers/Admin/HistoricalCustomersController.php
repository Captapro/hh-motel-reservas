<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Historical\HistoricalCustomerAnalysis;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Clientes que vuelven»: quiénes repiten según las planillas cargadas, con filtro
 * por período (un mes, dos, tres…). Solo soporte: aquí sí se ven nombres y RUT.
 */
class HistoricalCustomersController extends Controller
{
    private const LIMIT = 300;

    public function index(Request $request, HistoricalCustomerAnalysis $analysis): View
    {
        $loaded = $analysis->loadedMonths();
        if ($loaded === null) {
            return view('admin.historical.customers', ['loaded' => null]);
        }

        $valid = fn (?string $m) => is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : null;
        $fromMonth = $valid($request->query('desde')) ?? $loaded['first'];
        $toMonth = $valid($request->query('hasta')) ?? $loaded['last'];
        if ($fromMonth > $toMonth) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }
        $from = Carbon::parse($fromMonth.'-01')->startOfMonth();
        $to = Carbon::parse($toMonth.'-01')->endOfMonth();

        $min = max(1, min(10, (int) $request->query('min', 2)));
        $order = in_array($request->query('orden'), ['visitas', 'gasto', 'ultima', 'nombre'], true) ? $request->query('orden') : 'visitas';
        $role = $request->query('rol') === 'titular' ? 'titular' : 'todos';
        $search = trim((string) $request->query('q', ''));

        $everyone = $analysis->people($from, $to);

        // Cifras del período sobre todas las personas, antes de filtrar la lista.
        $stats = [
            'people' => $everyone->count(),
            'returned' => $everyone->where('visits', '>=', 2)->count(),
            'came_before' => $everyone->where('before', '>', 0)->count(),
            'first_time' => $everyone->where('before', 0)->count(),
        ];

        $rows = $everyone
            ->filter(fn (array $p) => $p['visits'] >= $min)
            ->when($role === 'titular', fn ($c) => $c->filter(fn (array $p) => $p['as_holder'] > 0))
            ->when($search !== '', function ($c) use ($search) {
                $needle = mb_strtolower($search);
                $digits = preg_replace('/[^0-9kK]/', '', $search);

                return $c->filter(fn (array $p) => str_contains(mb_strtolower($p['name']), $needle)
                    || ($digits !== '' && str_contains(strtolower(preg_replace('/[^0-9kK]/', '', $p['document'])), strtolower($digits))));
            });

        $rows = match ($order) {
            'gasto' => $rows->sortByDesc(fn (array $p) => [$p['spent'], $p['visits']]),
            'ultima' => $rows->sortByDesc(fn (array $p) => $p['last']->timestamp),
            'nombre' => $rows->sortBy(fn (array $p) => mb_strtolower($p['name'])),
            default => $rows->sortByDesc(fn (array $p) => [$p['visits'], $p['spent']]),
        };

        $monthName = fn (string $m) => Carbon::parse($m.'-01')->locale('es')->isoFormat('MMM YY');
        $last = Carbon::parse($loaded['last'].'-01');
        $span = fn (int $n) => ['desde' => max($loaded['first'], $last->copy()->subMonths($n - 1)->format('Y-m')), 'hasta' => $loaded['last']];

        return view('admin.historical.customers', [
            'loaded' => $loaded,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'min' => $min,
            'order' => $order,
            'role' => $role,
            'search' => $search,
            'stats' => $stats,
            'total' => $rows->count(),
            'rows' => $rows->take(self::LIMIT)->values(),
            'limit' => self::LIMIT,
            'monthName' => $monthName,
            'chips' => [
                'Último mes' => $span(1),
                'Últimos 2 meses' => $span(2),
                'Últimos 3 meses' => $span(3),
                'Últimos 6 meses' => $span(6),
                'Últimos 12 meses' => $span(12),
                'Todo lo cargado' => ['desde' => $loaded['first'], 'hasta' => $loaded['last']],
            ],
        ]);
    }
}
