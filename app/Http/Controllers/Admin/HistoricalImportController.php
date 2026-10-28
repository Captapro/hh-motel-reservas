<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HistoricalGuest;
use App\Models\HistoricalSale;
use App\Services\Historical\HistoricalSalesImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
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
