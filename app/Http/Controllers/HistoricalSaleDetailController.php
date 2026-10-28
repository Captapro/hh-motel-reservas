<?php

namespace App\Http\Controllers;

use App\Models\HistoricalSale;
use Illuminate\View\View;

class HistoricalSaleDetailController extends Controller
{
    /**
     * Ficha de una venta de la planilla (antes del sistema) para el panel
     * lateral de Ventas del día. Los nombres y RUT solo los ve soporte.
     */
    public function show(HistoricalSale $sale): View
    {
        $sale->load('guests');

        // Lo que se cobró junto con esta estadía: mismas fechas y mismo voucher o boleta.
        $related = collect();
        if (filled($sale->voucher_number) || filled($sale->receipt_number)) {
            $related = HistoricalSale::query()
                ->where('id', '!=', $sale->id)
                ->whereDate('sold_on', $sale->sold_on)
                ->where(function ($q) use ($sale) {
                    $q->when(filled($sale->voucher_number), fn ($w) => $w->orWhere('voucher_number', $sale->voucher_number))
                        ->when(filled($sale->receipt_number), fn ($w) => $w->orWhere('receipt_number', $sale->receipt_number));
                })
                ->orderBy('source_row')->get();
        }

        return view('sales._historical_detail', [
            'sale' => $sale,
            'related' => $related,
            'showNames' => (bool) auth()->user()?->isSoporte(),
        ]);
    }
}
