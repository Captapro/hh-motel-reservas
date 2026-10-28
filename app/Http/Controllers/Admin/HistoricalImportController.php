<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
use App\Services\Historical\HistoricalSalesImporter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Carga de planillas de ventas anteriores al sistema (solo soporte). El archivo
 * se lee en el momento y no se guarda: lo que queda son las ventas históricas
 * (para el analista y la repetición de clientes), nunca reservas ni caja.
 */
class HistoricalImportController extends Controller
{
    public function index(): View
    {
        return view('admin.historical.index', [
            'sources' => HistoricalSale::query()
                ->selectRaw('source, count(*) as lines, min(sold_on) as first_day, max(sold_on) as last_day')
                ->groupBy('source')->orderBy('first_day')->get(),
            'guests' => HistoricalGuest::count(),
            'months' => $this->months(),
            'returning' => $this->returningGuests(),
        ]);
    }

    /** Detalle de lo cargado: un mes día por día y, al elegir un día, cada una de sus ventas. */
    public function sales(Request $request): View
    {
        $available = HistoricalSale::query()->get(['sold_on'])->map(fn (HistoricalSale $s) => $s->sold_on->format('Y-m'))->unique()->sort()->values();
        $month = (string) $request->query('mes', $available->last() ?? now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = (string) ($available->last() ?? now()->format('Y-m'));
        }
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $lines = HistoricalSale::query()->whereBetween('sold_on', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])->get();

        $days = $lines->groupBy(fn (HistoricalSale $s) => $s->sold_on->toDateString())->map(function (Collection $g, string $date) {
            $stays = $g->where('sale_type', 'ALOJAMIENTO');

            return [
                'date' => $date,
                'weekday' => Carbon::parse($date)->locale('es')->isoFormat('dddd'),
                'stays' => $stays->count(),
                'lodging' => (int) $stays->sum(fn (HistoricalSale $s) => $s->total()),
                'others' => (int) $g->where('sale_type', '!=', 'ALOJAMIENTO')->sum(fn (HistoricalSale $s) => $s->total()),
                'total' => (int) $g->sum(fn (HistoricalSale $s) => $s->total()),
                'cash' => (int) $g->where('payment_method', 'efectivo')->sum(fn (HistoricalSale $s) => $s->total()),
                'card' => (int) $g->where('payment_method', 'tarjeta')->sum(fn (HistoricalSale $s) => $s->total()),
                'transfer' => (int) $g->where('payment_method', 'transferencia')->sum(fn (HistoricalSale $s) => $s->total()),
            ];
        })->sortKeys()->values();

        $day = (string) $request->query('dia', '');
        $detail = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)
            ? $lines->filter(fn (HistoricalSale $s) => $s->sold_on->toDateString() === $day)->sortBy(['starts_time', 'source_row'])->values()->load('guests')
            : collect();

        return view('admin.historical.sales', [
            'available' => $available,
            'month' => $month,
            'days' => $days,
            'day' => $detail->isNotEmpty() ? $day : null,
            'detail' => $detail,
            'byCategory' => $this->breakdown($lines->where('sale_type', 'ALOJAMIENTO'), fn (HistoricalSale $s) => $s->category ?: 'Sin dato'),
            'byPayment' => $this->breakdown($lines, fn (HistoricalSale $s) => $s->payment_method ?: 'sin dato'),
            'byShift' => $this->breakdown($lines->where('sale_type', 'ALOJAMIENTO'), fn (HistoricalSale $s) => $s->shift ?: 'Sin dato'),
        ]);
    }

    private function breakdown(Collection $rows, callable $key): Collection
    {
        return $rows->groupBy($key)->map(fn (Collection $g, string $k) => ['label' => $k, 'count' => $g->count(), 'total' => (int) $g->sum(fn (HistoricalSale $s) => $s->total())])->sortByDesc('total')->values();
    }

    /** Estadías y venta por mes de lo cargado. */
    private function months(): Collection
    {
        return HistoricalSale::query()->get(['sold_on', 'sale_type', 'amount_main', 'amount_extras'])
            ->groupBy(fn (HistoricalSale $s) => $s->sold_on->format('Y-m'))
            ->map(fn (Collection $g, string $month) => [
                'month' => $month,
                'stays' => $g->where('sale_type', 'ALOJAMIENTO')->count(),
                'total' => (int) $g->sum(fn (HistoricalSale $s) => $s->total()),
            ])->sortKeys()->values();
    }

    /**
     * Personas (por RUT válido) con más estadías, con lo que gastaron como
     * titulares. Solo se muestra a soporte: es lo único donde se ven nombres.
     */
    private function returningGuests(int $limit = 15): Collection
    {
        $rows = HistoricalGuest::query()
            ->where('document_kind', 'rut')
            ->join('historical_sales', 'historical_sales.id', '=', 'historical_guests.historical_sale_id')
            ->where('historical_sales.sale_type', 'ALOJAMIENTO')
            ->orderBy('historical_sales.sold_on')
            ->get(['historical_guests.name', 'historical_guests.document', 'historical_guests.is_holder', 'historical_sales.id as sale_id', 'historical_sales.sold_on', 'historical_sales.amount_main', 'historical_sales.amount_extras']);

        return $rows->groupBy('document')
            ->map(fn (Collection $g, string $document) => [
                'document' => $document,
                'name' => $g->last()->name,
                'visits' => $g->pluck('sale_id')->unique()->count(),
                'spent' => (int) $g->where('is_holder', true)->unique('sale_id')->sum(fn ($r) => $r->amount_main + $r->amount_extras),
                'first' => Carbon::parse($g->first()->sold_on)->format('d/m/Y'),
                'last' => Carbon::parse($g->last()->sold_on)->format('d/m/Y'),
            ])
            ->filter(fn (array $p) => $p['visits'] >= 2)
            ->sortByDesc('visits')->take($limit)->values();
    }

    public function store(Request $request, HistoricalSalesImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'fuente' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'anio' => ['required', 'integer', 'min:2015', 'max:2100'],
            'accion' => ['required', 'in:simular,cargar'],
        ], [
            'fuente.regex' => 'El nombre de la planilla solo puede llevar letras, números, guiones y guion bajo (por ejemplo planilla-2026-10).',
            'archivo.mimes' => 'El archivo tiene que ser un CSV: en Google Sheets, Archivo → Descargar → Valores separados por comas (.csv).',
        ]);

        $dry = $data['accion'] === 'simular';

        try {
            $summary = $importer->import($request->file('archivo')->getRealPath(), $data['fuente'], (int) $data['anio'], $dry);
        } catch (\Throwable $e) {
            return back()->withInput($request->except('archivo'))->withErrors(['archivo' => 'No se pudo leer la planilla: '.$e->getMessage()]);
        }

        if (! $dry) {
            AuditLog::record(auth()->id(), 'historico.cargar', 'HistoricalSale', $data['fuente'], null, ['fuente' => $data['fuente'], 'lineas' => $summary['lineas'], 'huespedes' => $summary['huespedes']]);
        }

        return redirect()->route('admin.historical.index')
            ->with('status', $dry ? 'Simulación lista: no se guardó nada.' : 'Planilla cargada.')
            ->with('historical_summary', ['dry' => $dry, 'source' => $data['fuente']] + $summary);
    }

    /** Quita una planilla entera (por si se cargó con el año o el archivo equivocado). */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['fuente' => ['required', 'string', 'max:60']]);

        $deleted = DB::transaction(fn () => HistoricalSale::where('source', $data['fuente'])->delete());
        AuditLog::record(auth()->id(), 'historico.quitar', 'HistoricalSale', $data['fuente'], ['fuente' => $data['fuente'], 'lineas' => $deleted], null);

        return redirect()->route('admin.historical.index')->with('status', "Se quitó la planilla {$data['fuente']} ({$deleted} líneas).");
    }
}
