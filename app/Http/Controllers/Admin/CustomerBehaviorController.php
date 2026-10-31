<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\Analyst\HistoricalDataService;
use App\Services\Integrations\CustomerActivityTags;
use App\Support\TimeFormat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Estudio del comportamiento de los clientes dentro de Analytics: quiénes son, qué prefieren,
 * cómo reservan, cada cuánto vuelven y a quién conviene cuidar. Se calcula en PHP sobre las
 * estadías no canceladas, con las fechas en hora de Santiago.
 */
class CustomerBehaviorController extends Controller
{
    private const TZ = 'America/Santiago';

    private const EXCLUDED = ['CANCELADA', 'EXPIRADA', 'NO_SHOW'];

    public function index(Request $request): View
    {
        [$start, $end, $isCustom] = $this->range($request);

        $inRange = Booking::with(['room.category', 'addons', 'payments.paymentMethod', 'customer', 'couponRedemption'])
            ->whereNotIn('booking_status', self::EXCLUDED)
            ->whereBetween('starts_at', [$start->copy(), $end->copy()])
            ->get();

        $customerIds = $inRange->pluck('customer_id')->unique()->values();

        // Todo el historial (no solo el período) de los clientes que vinieron: sirve para saber
        // si eran nuevos o ya habían venido, y cada cuánto vuelven.
        $history = Booking::whereIn('customer_id', $customerIds)
            ->whereNotIn('booking_status', self::EXCLUDED)
            ->where('starts_at', '<=', now())
            ->orderBy('starts_at')
            ->get(['customer_id', 'starts_at'])
            ->groupBy('customer_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($b) => $b->starts_at->timezone(self::TZ))->values());

        $value = fn (Booking $b) => (int) ($b->price_final + $b->addons->sum('amount'));
        $byCustomer = $inRange->groupBy('customer_id');

        $newCount = $customerIds->filter(fn ($id) => ($history[$id] ?? collect())->filter(fn ($d) => $d->lt($start))->isEmpty() && ($byCustomer[$id]->count() === 1))->count();
        $repeaters = $customerIds->filter(fn ($id) => $byCustomer[$id]->count() >= 2 || ($history[$id] ?? collect())->filter(fn ($d) => $d->lt($start))->isNotEmpty())->count();
        $active = $customerIds->count();
        $revenue = (int) $inRange->sum($value);

        // Días entre visitas consecutivas de cada cliente (todo su historial).
        $gaps = collect();
        foreach ($history as $dates) {
            for ($i = 1; $i < $dates->count(); $i++) {
                $gaps->push((int) $dates[$i - 1]->diffInDays($dates[$i]));
            }
        }

        $segments = $this->segmentCounts($customerIds, $byCustomer, $value);
        $risk = $this->atRisk();

        return view('admin.analytics.customers', [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'isCustom' => $isCustom,
            'rangeLabel' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'kpis' => [
                'active' => $active,
                'new' => $newCount,
                'repeaters' => $repeaters,
                'repeat_rate' => $active ? (int) round($repeaters / $active * 100) : 0,
                'visits_per_customer' => $active ? round($inRange->count() / $active, 1) : 0,
                'spend_per_customer' => $active ? (int) round($revenue / $active) : 0,
                'median_gap' => $gaps->isEmpty() ? null : (int) $gaps->sort()->values()->get(intdiv($gaps->count(), 2)),
                'at_risk' => $risk->count(),
            ],
            'historical' => app(HistoricalDataService::class)->behavior(),
            'segments' => $segments,
            'monthly' => $this->newVsReturning(),
            'categories' => $this->tally($inRange, fn (Booking $b) => $b->room?->category?->name ?? 'Sin categoría', $value),
            'durations' => $this->tally($inRange, fn (Booking $b) => TimeFormat::minutes($b->duration_minutes), $value, fn ($k, $rows) => $rows->first()->duration_minutes),
            'weekdays' => $this->weekdays($inRange),
            'dayparts' => $this->dayparts($inRange),
            'party' => $this->tally($inRange, fn (Booking $b) => $b->guests_count >= 3 ? '3 o más personas' : $b->guests_count.' '.($b->guests_count === 1 ? 'persona' : 'personas'), $value),
            'channel' => $this->tally($inRange, fn (Booking $b) => $b->origin ? $b->originLabel() : ($b->created_by ? 'Gestionada por el equipo (sin detalle)' : 'Reserva en línea'), $value),
            'lead' => $this->lead($inRange),
            'payments' => $inRange->flatMap->payments->where('status', 'aprobado')->groupBy(fn ($p) => $p->paymentMethod?->name ?? 'Sin método')->map(fn ($rows) => (int) $rows->sum('amount'))->sortDesc(),
            'extras' => [
                'with_extras' => $inRange->filter(fn (Booking $b) => $b->addons->isNotEmpty())->count(),
                'bookings' => $inRange->count(),
                'extra_revenue' => (int) $inRange->sum(fn (Booking $b) => $b->addons->sum('amount')),
            ],
            'coupons' => ['used' => $inRange->filter(fn (Booking $b) => $b->coupon_id !== null || (float) $b->discount_amount > 0)->count(), 'bookings' => $inRange->count()],
            'gapBuckets' => $this->bucketGaps($gaps),
            'top' => $this->topCustomers($byCustomer, $value, $history),
            'risk' => $risk->take(10),
            'activity' => app(CustomerActivityTags::class)->report(),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: bool} */
    private function range(Request $request): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            return [Carbon::parse($request->query('from'), self::TZ)->startOfDay(), Carbon::parse($request->query('to'), self::TZ)->endOfDay(), true];
        }
        $now = now(self::TZ);

        // Para estudiar clientes un mes es muy poco: por defecto, los últimos 90 días.
        return [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), false];
    }

    /** Segmento de cada cliente según las reglas ya configuradas en Clientes. */
    private function segmentCounts(Collection $ids, Collection $byCustomer, callable $value): Collection
    {
        $rows = [];
        Customer::whereIn('id', $ids)->get()->each(function (Customer $c) use (&$rows, $byCustomer, $value) {
            $seg = $c->segment();
            $key = $seg['type'];
            $rows[$key] ??= ['label' => $seg['label'], 'count' => 0, 'revenue' => 0];
            $rows[$key]['count']++;
            $rows[$key]['revenue'] += (int) ($byCustomer[$c->id] ?? collect())->sum($value);
        });

        return collect($rows)->sortByDesc('count');
    }

    /** Clientes nuevos vs que ya habían venido, en cada uno de los últimos 6 meses. */
    private function newVsReturning(): Collection
    {
        $months = collect(range(5, 0))->map(fn ($i) => now(self::TZ)->startOfMonth()->subMonthsNoOverflow($i));

        $stays = Booking::whereNotIn('booking_status', self::EXCLUDED)->where('starts_at', '<=', now())
            ->orderBy('starts_at')->get(['customer_id', 'starts_at']);
        $firstVisit = $stays->groupBy('customer_id')->map(fn ($rows) => $rows->first()->starts_at->timezone(self::TZ));

        return $months->map(function (Carbon $m) use ($stays, $firstVisit) {
            $inMonth = $stays->filter(fn ($b) => $b->starts_at->timezone(self::TZ)->isSameMonth($m));
            $ids = $inMonth->pluck('customer_id')->unique();
            $new = $ids->filter(fn ($id) => $firstVisit[$id]->isSameMonth($m))->count();

            return ['label' => $m->locale('es')->isoFormat('MMM YY'), 'new' => $new, 'returning' => $ids->count() - $new];
        });
    }

    /**
     * Agrupa las estadías por una clave y cuenta reservas, clientes distintos y venta.
     *
     * @param  callable|null  $sortBy  fn($key, $rows) para ordenar por algo distinto de la cantidad
     */
    private function tally(Collection $bookings, callable $key, callable $value, ?callable $sortBy = null): Collection
    {
        return $bookings->groupBy($key)->map(fn (Collection $rows, $label) => [
            'label' => (string) $label,
            'count' => $rows->count(),
            'customers' => $rows->pluck('customer_id')->unique()->count(),
            'revenue' => (int) $rows->sum($value),
            'sort' => $sortBy ? $sortBy($label, $rows) : -$rows->count(),
        ])->sortBy('sort')->values();
    }

    private function weekdays(Collection $bookings): Collection
    {
        $names = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $counts = $bookings->countBy(fn (Booking $b) => $b->starts_at->timezone(self::TZ)->dayOfWeekIso);

        return collect(range(1, 7))->map(fn ($d) => ['label' => $names[$d - 1], 'count' => (int) ($counts[$d] ?? 0)]);
    }

    private function dayparts(Collection $bookings): Collection
    {
        $bucket = function (Booking $b) {
            $h = $b->starts_at->timezone(self::TZ)->hour;

            return match (true) {
                $h < 6 => 'Madrugada (00–06)',
                $h < 12 => 'Mañana (06–12)',
                $h < 18 => 'Tarde (12–18)',
                default => 'Noche (18–24)',
            };
        };
        $counts = $bookings->countBy($bucket);

        return collect(['Mañana (06–12)', 'Tarde (12–18)', 'Noche (18–24)', 'Madrugada (00–06)'])->map(fn ($l) => ['label' => $l, 'count' => (int) ($counts[$l] ?? 0)]);
    }

    /** Con cuánta anticipación reservan (de la creación de la reserva al inicio de la estadía). */
    private function lead(Collection $bookings): Collection
    {
        $bucket = function (Booking $b) {
            $hours = $b->created_at->diffInMinutes($b->starts_at, false) / 60;

            return match (true) {
                $hours < 1 => 'Llegan sin reservar (menos de 1 h)',
                $hours < 6 => 'En el día (1 a 6 h antes)',
                $hours < 24 => 'Mismo día o noche anterior (6 a 24 h)',
                $hours < 24 * 7 => 'Con días de anticipación (1 a 7 días)',
                default => 'Con más de una semana',
            };
        };
        $counts = $bookings->countBy($bucket);

        return collect(['Llegan sin reservar (menos de 1 h)', 'En el día (1 a 6 h antes)', 'Mismo día o noche anterior (6 a 24 h)', 'Con días de anticipación (1 a 7 días)', 'Con más de una semana'])
            ->map(fn ($l) => ['label' => $l, 'count' => (int) ($counts[$l] ?? 0)]);
    }

    private function bucketGaps(Collection $gaps): Collection
    {
        $defs = [['Hasta 7 días', 0, 7], ['8 a 15 días', 8, 15], ['16 a 30 días', 16, 30], ['31 a 60 días', 31, 60], ['Más de 60 días', 61, PHP_INT_MAX]];

        return collect($defs)->map(fn ($d) => ['label' => $d[0], 'count' => $gaps->filter(fn ($g) => $g >= $d[1] && $g <= $d[2])->count()]);
    }

    private function topCustomers(Collection $byCustomer, callable $value, Collection $history): Collection
    {
        return $byCustomer->map(function (Collection $rows, $id) use ($value, $history) {
            $customer = $rows->first()->customer;
            $favorite = $rows->groupBy(fn (Booking $b) => $b->room?->category?->name ?? '—')->map->count()->sortDesc()->keys()->first();

            return [
                'id' => $id, 'name' => $customer?->name ?? 'Sin nombre', 'visits' => $rows->count(), 'total' => (int) $rows->sum($value),
                'avg' => (int) round($rows->sum($value) / $rows->count()), 'last' => $rows->max('starts_at')->timezone(self::TZ), 'favorite' => $favorite,
                'lifetime' => ($history[$id] ?? collect())->count(),
            ];
        })->sortByDesc('total')->take(10)->values();
    }

    /**
     * Clientes que venían seguido y llevan más tiempo del habitual sin volver: a ellos
     * conviene escribirles antes de perderlos. (No incluye los que ya están en lista negra.)
     */
    private function atRisk(): Collection
    {
        $stays = Booking::whereNotIn('booking_status', self::EXCLUDED)->where('starts_at', '<=', now())
            ->orderBy('starts_at')->get(['customer_id', 'starts_at'])->groupBy('customer_id');
        $customers = Customer::whereIn('id', $stays->keys())->get()->keyBy('id');

        return $stays->map(function (Collection $rows, $id) use ($customers) {
            $dates = $rows->map(fn ($b) => $b->starts_at->timezone(self::TZ))->values();
            if ($dates->count() < 2 || ! isset($customers[$id]) || ($customers[$id]->blacklist_status ?? null) === 'blocked') {
                return null;
            }
            $avg = max(1, (int) round($dates->first()->diffInDays($dates->last()) / ($dates->count() - 1)));
            $since = (int) $dates->last()->diffInDays(now(self::TZ));
            $limit = max(30, (int) round($avg * 1.5));

            return ($since > $limit && $since <= 180)
                ? ['customer' => $customers[$id], 'visits' => $dates->count(), 'avg' => $avg, 'since' => $since, 'last' => $dates->last()]
                : null;
        })->filter()->sortByDesc('visits')->values();
    }
}
