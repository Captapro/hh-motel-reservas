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
use Illuminate\Support\Str;
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

        $money = fn (Collection $c) => (int) $c->sum(fn (HistoricalSale $s) => $s->total());
        $of = fn (Collection $c, string $concept) => $money($c->filter(fn (HistoricalSale $s) => $s->concept() === $concept));
        $days = $lines->groupBy(fn (HistoricalSale $s) => $s->sold_on->toDateString())->map(fn (Collection $g, string $date) => [
            'date' => $date,
            'weekday' => Carbon::parse($date)->locale('es')->isoFormat('dddd'),
            'stays' => $g->where('sale_type', 'ALOJAMIENTO')->count(),
            'lodging' => $of($g, 'alojamiento'),
            'extra_hour' => $of($g, 'hora_extra'),
            'extra_person' => $of($g, 'persona_adicional'),
            'services' => $of($g, 'servicios'),
            'extras' => $of($g, 'extras'),
            'split' => $of($g, 'fraccionado'),
            'total' => $money($g),
            'cash' => $money($g->where('payment_method', 'efectivo')),
            'card' => $money($g->where('payment_method', 'tarjeta')),
            'transfer' => $money($g->where('payment_method', 'transferencia')),
        ])->sortKeys()->values();

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
            'archivo' => ['required', 'file', 'max:10240'],
            'fuente' => ['required', 'string', 'max:60'],
            'anio' => ['required', 'integer', 'min:2015', 'max:2100'],
            'accion' => ['required', 'in:simular,cargar'],
        ], [
            'archivo.required' => 'Elige el archivo CSV de la planilla.',
            'archivo.max' => 'El archivo pesa más de 10 MB: revisa que sea una sola pestaña del mes.',
            'archivo.uploaded' => 'El archivo no se pudo subir: vuelve a elegirlo e inténtalo otra vez.',
            'fuente.required' => 'Escribe un nombre para la planilla, por ejemplo planilla-2026-10.',
        ]);

        // El nombre se deja en minúsculas y con guiones (así «Planilla 2026 10» queda «planilla-2026-10»).
        $source = Str::slug($data['fuente']);
        if ($source === '') {
            return back()->withInput($request->except('archivo'))->withErrors(['fuente' => 'Escribe un nombre con letras o números, por ejemplo planilla-2026-10.']);
        }

        $file = $request->file('archivo');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls', 'ods', 'pdf', 'zip'], true)) {
            return back()->withInput($request->except('archivo'))->withErrors(['archivo' => "Ese archivo es .{$extension}. En Google Sheets abre la pestaña del mes y descárgala como CSV: Archivo → Descargar → Valores separados por comas (.csv)."]);
        }

        $dry = $data['accion'] === 'simular';

        try {
            $summary = $importer->import($file->getRealPath(), $source, (int) $data['anio'], $dry);
        } catch (\Throwable $e) {
            return back()->withInput($request->except('archivo'))->withErrors(['archivo' => 'No se pudo leer la planilla: '.$e->getMessage()]);
        }

        if ($summary['lineas'] === 0) {
            return back()->withInput($request->except('archivo'))->withErrors(['archivo' => 'No encontré ventas en ese archivo. Revisa que sea la pestaña de ventas del mes (la que tiene "Fecha", "Tipo de Venta" y los montos) descargada como CSV.']);
        }

        if (! $dry) {
            AuditLog::record(auth()->id(), 'historico.cargar', 'HistoricalSale', $source, null, ['fuente' => $source, 'lineas' => $summary['lineas'], 'huespedes' => $summary['huespedes']]);
        }

        return redirect()->route('admin.historical.index')
            ->with('status', $dry ? 'Revisión lista: todavía NO se guardó nada. Si los totales cuadran, vuelve a elegir el archivo y presiona «2 · Guardar planilla».' : 'Planilla guardada.')
            ->with('historical_summary', ['dry' => $dry, 'source' => $source] + $summary);
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
