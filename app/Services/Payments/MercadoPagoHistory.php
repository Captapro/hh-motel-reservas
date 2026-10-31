<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Historial de pagos leído directo de la cuenta de Mercado Pago del motel,
 * cruzado con lo que tiene registrado el sistema: qué pagos de reservas
 * faltan acá, cuáles están acá y no allá, y cuánto cobró la máquina (Point)
 * cada día contra lo que recepción anotó como tarjeta.
 */
class MercadoPagoHistory
{
    private const PAGE = 100;

    private const MAX_PAGES = 20;

    private const STATUS = [
        'approved' => 'Aprobado', 'rejected' => 'Rechazado', 'refunded' => 'Devuelto', 'charged_back' => 'Contracargo',
        'cancelled' => 'Cancelado', 'pending' => 'Pendiente', 'in_process' => 'En revisión', 'in_mediation' => 'En disputa',
    ];

    private const TYPE = [
        'credit_card' => 'Crédito', 'debit_card' => 'Débito', 'prepaid_card' => 'Prepago',
        'account_money' => 'Saldo MP', 'bank_transfer' => 'Transferencia', 'ticket' => 'Efectivo',
    ];

    public function __construct(private readonly MercadoPagoClient $client) {}

    /**
     * @return array{rows: Collection, totals: array, missing_in_mp: Collection, days: Collection}
     */
    public function month(Carbon $first, ?string $day = null): array
    {
        $tz = 'America/Santiago';
        $from = $first->copy()->timezone($tz)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        // Dos minutos de caché: abrir y recargar la página no castiga la API de Mercado Pago.
        $raw = Cache::remember('mp-history:'.$from->format('Y-m'), 120, fn () => $this->fetch($from, $to));

        $codes = collect($raw)->pluck('external_reference')->filter()->unique();
        $bookings = Booking::whereIn('code', $codes)->get(['id', 'code'])->keyBy('code');
        $local = Payment::where('external_id', 'like', 'MP-%')->get(['id', 'booking_id', 'external_id', 'amount', 'status', 'created_at']);
        $localByMp = $local->keyBy(fn (Payment $p) => explode('-', $p->external_id)[1] ?? '');

        $rows = collect($raw)->map(function (array $mp) use ($tz, $bookings, $localByMp) {
            $id = (string) ($mp['id'] ?? '');
            $status = (string) ($mp['status'] ?? '');
            $code = (string) ($mp['external_reference'] ?? '');
            $amount = (int) round((float) ($mp['transaction_amount'] ?? 0));
            $fee = (int) round(collect($mp['fee_details'] ?? [])->sum('amount'));
            $isPoint = str_contains(strtoupper((string) ($mp['point_of_interaction']['type'] ?? '')), 'POINT');
            $booking = $code !== '' ? $bookings->get($code) : null;
            // Código de reserva del sistema aunque la reserva ya no exista (p. ej. una de prueba borrada).
            $isBookingCode = (bool) preg_match('/^HH-\d{8}-\d+$/', $code);
            $inSystem = $localByMp->has($id);

            return [
                'id' => $id,
                'at' => Carbon::parse($mp['date_approved'] ?? $mp['date_created'] ?? 'now')->timezone($tz),
                'status' => $status,
                'status_label' => self::STATUS[$status] ?? $status,
                'amount' => $amount,
                'fee' => $fee,
                'net' => (int) round((float) ($mp['transaction_details']['net_received_amount'] ?? ($amount - $fee))),
                'type' => self::TYPE[$mp['payment_type_id'] ?? ''] ?? (string) ($mp['payment_type_id'] ?? '—'),
                // La máquina también manda una referencia ("Venta presencial"): solo es reserva online si el código es de una reserva.
                'channel' => $booking || $isBookingCode ? 'Reserva online' : ($isPoint ? 'Máquina (Point)' : (! empty($mp['pos_id']) ? 'QR' : 'Otro')),
                'point' => $isPoint,
                'code' => $code,
                'booking' => $booking,
                'in_system' => $inSystem,
                'deleted_booking' => $isBookingCode && ! $booking,
                // Pago aprobado de una reserva que el sistema no tiene: hay que revisarlo.
                'missing' => $status === 'approved' && $booking && ! $inSystem,
            ];
        })->sortByDesc('at')->values();

        $approved = $rows->where('status', 'approved');
        $mpIds = $rows->pluck('id')->all();
        $missingInMp = $local
            ->filter(fn (Payment $p) => $p->status === 'aprobado' && $p->created_at->between($from, $to) && ! str_contains($p->external_id, '-RESTO-'))
            ->reject(fn (Payment $p) => in_array(explode('-', $p->external_id)[1] ?? '', $mpIds, true))
            ->load('booking:id,code')
            ->values();

        return [
            // El detalle es de un día; los totales siempre son del mes.
            'rows' => $day ? $rows->filter(fn ($r) => $r['at']->toDateString() === $day)->values() : $rows,
            'totals' => [
                'gross' => $approved->sum('amount'),
                'fees' => $approved->sum('fee'),
                'net' => $approved->sum('net'),
                'count' => $approved->count(),
                'online' => $approved->where('channel', 'Reserva online')->sum('amount'),
                'point' => $approved->where('point', true)->sum('amount'),
                'missing' => $rows->where('missing', true)->count(),
            ],
            'missing_in_mp' => $missingInMp,
            'days' => $this->days($rows, $from, $to, $tz),
        ];
    }

    /**
     * Resumen de cada día del mes con movimientos: lo cobrado y, para cuadrar
     * la caja, lo que cobró la máquina contra lo que recepción anotó como
     * débito o crédito.
     */
    private function days(Collection $rows, Carbon $from, Carbon $to, string $tz): Collection
    {
        $cards = Payment::where('status', 'aprobado')->whereBetween('created_at', [$from, $to])
            ->whereHas('paymentMethod', fn ($q) => $q->whereIn('code', ['debito', 'credito']))
            ->get(['amount', 'created_at'])
            ->groupBy(fn (Payment $p) => $p->created_at->timezone($tz)->toDateString())
            ->map(fn ($g) => (int) $g->sum('amount'));
        $byDay = $rows->groupBy(fn ($r) => $r['at']->toDateString());

        return $byDay->keys()->merge($cards->keys())->unique()->sortDesc()->values()
            ->map(function (string $day) use ($byDay, $cards, $tz) {
                $approved = $byDay->get($day, collect())->where('status', 'approved');

                return [
                    'day' => Carbon::parse($day, $tz),
                    'count' => $byDay->get($day, collect())->count(),
                    'gross' => (int) $approved->sum('amount'),
                    'net' => (int) $approved->sum('net'),
                    'machine' => (int) $approved->where('point', true)->sum('amount'),
                    'system' => $cards->get($day, 0),
                ];
            });
    }

    /** @return array<int, array<string, mixed>> */
    private function fetch(Carbon $from, Carbon $to): array
    {
        $all = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->client->searchPayments([
                'range' => 'date_created',
                'begin_date' => $from->format('Y-m-d\TH:i:s.vP'),
                'end_date' => $to->format('Y-m-d\TH:i:s.vP'),
                'sort' => 'date_created', 'criteria' => 'desc',
                'limit' => self::PAGE, 'offset' => $page * self::PAGE,
            ]);
            $all = array_merge($all, $batch);
            if (count($batch) < self::PAGE) {
                break;
            }
        }

        return $all;
    }
}
