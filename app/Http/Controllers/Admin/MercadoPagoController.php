<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\MercadoPagoClient;
use App\Services\Payments\MercadoPagoHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/** Historial de la cuenta de Mercado Pago del motel, cruzado con el sistema. */
class MercadoPagoController extends Controller
{
    public function index(MercadoPagoClient $client, MercadoPagoHistory $history): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) request('mes')) ? (string) request('mes') : now('America/Santiago')->format('Y-m');
        $first = Carbon::createFromFormat('Y-m-d', $month.'-01', 'America/Santiago')->startOfDay();
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request('dia')) && str_starts_with((string) request('dia'), $month) ? (string) request('dia') : null;

        $data = null;
        $error = null;
        if (! $client->enabled()) {
            $error = 'Mercado Pago no está conectado (falta la credencial en el servidor).';
        } else {
            try {
                $data = $history->month($first, $day);
            } catch (Throwable $e) {
                Log::warning('Mercado Pago: no se pudo leer el historial', ['error' => $e->getMessage()]);
                $error = 'Mercado Pago no respondió. Intenta de nuevo en un minuto.';
            }
        }

        return view('admin.mercadopago.index', [
            'first' => $first,
            'day' => $day ? Carbon::parse($day, 'America/Santiago') : null,
            'prev' => $first->copy()->subMonth()->format('Y-m'),
            'next' => $first->copy()->addMonth()->format('Y-m'),
            'isCurrent' => $first->format('Y-m') === now('America/Santiago')->format('Y-m'),
            'data' => $data,
            'error' => $error,
        ]);
    }
}
