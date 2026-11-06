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

    private const MATCH_MINUTES = 20;

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
        $bookings = Booking::with('room:id,name')->whereIn('code', $codes)->get(['id', 'code', 'room_id'])->keyBy('code');
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
                'rooms' => $booking?->room?->name,
                'in_system' => $inSystem,
                'auth' => (string) ($mp['authorization_code'] ?? ''),
                'voucher' => null,
                'correlative' => null,
                'deleted_booking' => $isBookingCode && ! $booking,
                // Pago aprobado de una reserva que el sistema no tiene: hay que revisarlo.
                'missing' => $status === 'approved' && $booking && ! $inSystem,
            ];
        })->sortByDesc('at')->values();
        $rows = $this->matchMachine($this->numberMachine($rows), $from, $to);

        // Canal "Otro": movimientos de la cuenta sin máquina ni reserva (no son del
        // motel y no llegan a su saldo). Se muestran aparte, fuera de los totales.
        $allApproved = $rows->where('status', 'approved');
        $approved = $allApproved->where('channel', '!=', 'Otro');
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
                'other' => $allApproved->where('channel', 'Otro')->sum('amount'),
                'other_count' => $allApproved->where('channel', 'Otro')->count(),
            ],
            'missing_in_mp' => $missingInMp,
            'days' => $this->days($rows, $from, $to, $tz),
        ];
    }

    /**
     * Numera los cobros aprobados de la máquina del mes en orden (01, 02, 03…),
     * igual que el voucher interno que lleva recepción.
     */
    private function numberMachine(Collection $rows): Collection
    {
        $order = $rows->filter(fn ($r) => $r['point'] && $r['status'] === 'approved')
            ->sortBy(fn ($r) => $r['at']->getTimestamp().'-'.$r['id'])->pluck('id')->values()->flip();

        return $rows->map(fn ($r) => $order->has($r['id'])
            ? ['correlative' => str_pad((string) ($order[$r['id']] + 1), 2, '0', STR_PAD_LEFT)] + $r
            : $r);
    }

    /**
     * Cada cobro de la máquina se cruza con lo que registró recepción.
     * Primero por N° de voucher (el correlativo del mes, 01, 02…): si los
     * pagos con ese voucher suman lo mismo que el cobro, calzan, aunque se
     * hayan anotado tarde o sea un solo cobro para dos piezas. Si no, por
     * mismo monto y a menos de MATCH_MINUTES, el más cercano. Cada pago se
     * usa una sola vez.
     */
    private function matchMachine(Collection $rows, Carbon $from, Carbon $to): Collection
    {
        $cards = Payment::with('booking:id,code,room_id', 'booking.room:id,name')->where('status', 'aprobado')
            ->whereHas('paymentMethod', fn ($q) => $q->whereIn('code', ['debito', 'credito']))
            ->whereBetween('created_at', [$from->copy()->subHour(), $to->copy()->addHour()])
            ->get(['id', 'booking_id', 'voucher_number', 'amount', 'created_at']);
        $byVoucher = $cards->filter(fn (Payment $p) => ctype_digit(ltrim((string) $p->voucher_number)) && (int) $p->voucher_number > 0
                && $p->created_at->between($from, $to))
            ->groupBy(fn (Payment $p) => (int) $p->voucher_number);
        $used = [];
        $matched = function (array $r, Collection $payments) use (&$used) {
            foreach ($payments as $p) {
                $used[$p->id] = true;
            }
            $bookings = $payments->pluck('booking')->filter()->unique('id');

            return ['in_system' => true, 'booking' => $bookings->first(), 'voucher' => $payments->first()->voucher_number,
                'rooms' => $bookings->map(fn ($b) => $b->room?->name)->filter()->implode(' + ') ?: null] + $r;
        };

        $rows = $rows->map(function (array $r) use ($byVoucher, $matched) {
            if (! $r['point'] || $r['status'] !== 'approved' || ! $r['correlative']) {
                return $r;
            }
            $group = $byVoucher->get((int) $r['correlative']);

            return $group && (int) $group->sum('amount') === $r['amount'] ? $matched($r, $group) : $r;
        });

        return $rows->map(function (array $r) use ($cards, &$used, $matched) {
            if (! $r['point'] || $r['status'] !== 'approved' || $r['in_system']) {
                return $r;
            }
            $match = $cards
                ->filter(fn (Payment $p) => ! isset($used[$p->id]) && (int) $p->amount === $r['amount']
                    && abs($p->created_at->diffInMinutes($r['at'], false)) <= self::MATCH_MINUTES)
                ->sortBy(fn (Payment $p) => abs($p->created_at->diffInSeconds($r['at'], false)))
                ->first();

            return $match ? $matched($r, collect([$match])) : $r;
        });
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

        return self::markCompensated($byDay->keys()->merge($cards->keys())->unique()->sortDesc()->values()
            ->map(function (string $day) use ($byDay, $cards, $tz) {
                $approved = $byDay->get($day, collect())->where('status', 'approved')->where('channel', '!=', 'Otro');

                return [
                    'day' => Carbon::parse($day, $tz),
                    'count' => $byDay->get($day, collect())->count(),
                    'gross' => (int) $approved->sum('amount'),
                    'net' => (int) $approved->sum('net'),
                    'machine' => (int) $approved->where('point', true)->sum('amount'),
                    'system' => $cards->get($day, 0),
                ];
            }));
    }

    /**
     * Un cobro hecho antes de medianoche y anotado después deja la misma diferencia
     * con signo contrario en dos días seguidos: se marca como compensada, no como faltante.
     */
    public static function markCompensated(Collection $days): Collection
    {
        $diffs = $days->mapWithKeys(fn (array $d) => [$d['day']->toDateString() => $d['machine'] - $d['system']]);

        return $days->map(function (array $d) use ($diffs) {
            $diff = $diffs->get($d['day']->toDateString());
            $next = $d['day']->copy()->addDay();
            $previous = $d['day']->copy()->subDay();
            $partner = match (true) {
                $diff > 0 && $diffs->get($next->toDateString()) === -$diff => $next,
                $diff < 0 && $diffs->get($previous->toDateString()) === -$diff => $previous,
                default => null,
            };

            return $d + ['compensated_with' => $partner];
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
