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
        // Siempre se muestra el detalle de un día: el elegido, o hoy (o el último día si es un mes pasado).
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request('dia')) && str_starts_with((string) request('dia'), $month)
            ? (string) request('dia')
            : min(now('America/Santiago')->toDateString(), $first->copy()->endOfMonth()->toDateString());

        $data = null;
        $error = null;
        if (! $client->enabled()) {
            $error = 'Mercado Pago no está conectado (falta la credencial en el servidor).';
        } else {
            try {
                // Desde una reserva se llega con el voucher: el cobro puede ser de un día
                // anterior al que se anotó, así que se abre el día real de la máquina.
                $voucher = (int) request('voucher');
                $data = $history->month($first, $voucher ? null : $day);
                if ($voucher) {
                    $charge = $data['rows']->first(fn (array $r) => $r['point'] && (int) $r['voucher'] === $voucher);
                    $day = $charge ? $charge['at']->toDateString() : $day;
                    $data['rows'] = $data['rows']->filter(fn (array $r) => $r['at']->toDateString() === $day)->values();
                }
            } catch (Throwable $e) {
                Log::warning('Mercado Pago: no se pudo leer el historial', ['error' => $e->getMessage()]);
                $error = 'Mercado Pago no respondió. Intenta de nuevo en un minuto.';
            }
        }

        return view('admin.mercadopago.index', [
            'first' => $first,
            'day' => Carbon::parse($day, 'America/Santiago'),
            'prev' => $first->copy()->subMonth()->format('Y-m'),
            'next' => $first->copy()->addMonth()->format('Y-m'),
            'isCurrent' => $first->format('Y-m') === now('America/Santiago')->format('Y-m'),
            'data' => $data,
            'error' => $error,
        ]);
    }
}
