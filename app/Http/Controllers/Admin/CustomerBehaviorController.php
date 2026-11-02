<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\HistoricalSale;
use App\Services\Customers\UnifiedCustomerData;
use App\Services\Historical\HistoricalCustomerAnalysis;
use App\Services\Integrations\CustomerActivityTags;
use App\Support\Rut;
use App\Support\TimeFormat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Estudio del comportamiento de los clientes dentro de Analytics: quiénes son, qué prefieren,
 * cada cuánto vuelven y a quién conviene cuidar. Planillas y sistema son un solo historial
 * (UnifiedCustomerData, personas por RUT); lo que la planilla no registra (canal, anticipación,
 * pagos, cupones) sale solo del sistema.
 */
class CustomerBehaviorController extends Controller
{
    private const TZ = 'America/Santiago';

    private const EXCLUDED = ['CANCELADA', 'EXPIRADA', 'NO_SHOW'];

    public function index(Request $request): View
    {
        [$start, $end, $isCustom] = $this->range($request);
        ['stays' => $stays, 'visits' => $visits, 'cutoff' => $cutoff] = app(UnifiedCustomerData::class)->load();
        $in = fn (array $r) => $r['at']->between($start, $end);

        // ===== Personas (planilla + sistema, por RUT) =====
        $byPerson = $visits->groupBy('person')->map(fn (Collection $rows) => $rows->sortBy(fn ($v) => $v['at']->getTimestamp())->values());
        $days = $byPerson->map(fn (Collection $rows) => $rows->map(fn ($v) => $v['at']->toDateString())->unique()->values());
        $visitsIn = $visits->filter($in);
        $people = $visitsIn->pluck('person')->unique()->values();
        $first = $days->map(fn (Collection $d) => $d->first());
        $startDay = $start->toDateString();
        $endDay = $end->toDateString();
        $new = $people->filter(fn ($p) => $first[$p] >= $startDay)->count();
        $repeaters = $people->filter(fn ($p) => $first[$p] < $startDay || $days[$p]->filter(fn ($d) => $d >= $startDay && $d <= $endDay)->count() >= 2)->count();

        // Días entre una visita y la siguiente de la misma persona, cuando la vuelta cae en el rango.
        $gaps = collect();
        foreach ($days as $list) {
            for ($i = 1; $i < $list->count(); $i++) {
                if ($list[$i] >= $startDay && $list[$i] <= $endDay) {
                    $gaps->push((int) Carbon::parse($list[$i - 1])->diffInDays(Carbon::parse($list[$i])));
                }
            }
        }

        $staysIn = $stays->filter($in);
        $revenue = (int) $staysIn->sum('amount');
        $risk = $this->atRisk();

        // ===== Solo existe en el sistema (canal, anticipación, pagos, cupones, extras) =====
        $system = Booking::with(['room.category', 'addons', 'payments.paymentMethod', 'customer', 'couponRedemption'])
            ->whereNotIn('booking_status', self::EXCLUDED)
            ->whereBetween('starts_at', [$start->copy(), $end->copy()])
            ->when($cutoff, fn ($q) => $q->where('starts_at', '>', $cutoff->copy()))
            ->get();
        $value = fn (Booking $b) => (int) ($b->price_final + $b->addons->sum('amount'));

        return view('admin.analytics.customers', [
            'from' => $startDay,
            'to' => $endDay,
            'isCustom' => $isCustom,
            'rangeLabel' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'cutoff' => $cutoff,
            'kpis' => [
                'people' => $people->count(),
                'new' => $new,
                'repeaters' => $repeaters,
                'repeat_rate' => $people->count() ? (int) round($repeaters / $people->count() * 100) : 0,
                'stays' => $staysIn->count(),
                'visits_per_person' => $people->count() ? round($visitsIn->pluck('stay')->count() / $people->count(), 1) : 0,
                'ticket' => $staysIn->count() ? (int) round($revenue / $staysIn->count()) : 0,
                'median_gap' => $gaps->isEmpty() ? null : (int) $gaps->sort()->values()->get(intdiv($gaps->count(), 2)),
                'at_risk' => $risk->count(),
            ],
            'monthly' => $this->monthly($visits, $first, $start, $end),
            'gapBuckets' => $this->gapBuckets($gaps),
            'retention' => $this->retention($days, $first, $startDay, $endDay),
            'frequency' => $this->frequency($days, $people, $endDay),
            'categories' => $this->count($staysIn, fn ($s) => $s['category']),
            'weekdays' => $this->weekdays($staysIn),
            'dayparts' => $this->dayparts($staysIn->where('has_time', true)),
            'party' => $this->count($staysIn->whereNotNull('guests'), fn ($s) => $s['guests'] >= 3 ? '3 o más personas' : $s['guests'].' '.($s['guests'] === 1 ? 'persona' : 'personas')),
            'durations' => $this->count($staysIn->whereNotNull('minutes'), fn ($s) => TimeFormat::minutes((int) (round($s['minutes'] / 60) * 60) ?: 60), fn ($s) => $s['minutes']),
            'top' => $this->topPeople($visitsIn),
            'segments' => $this->segmentCounts($system->pluck('customer_id')->unique()->values(), $system->groupBy('customer_id'), $value),
            'channel' => $this->tally($system, fn (Booking $b) => $b->origin ? $b->originLabel() : ($b->created_by ? 'Gestionada por el equipo (sin detalle)' : 'Reserva en línea'), $value),
            'lead' => $this->lead($system),
            'payments' => $system->flatMap->payments->where('status', 'aprobado')->groupBy(fn ($p) => $p->paymentMethod?->name ?? 'Sin método')->map(fn ($rows) => (int) $rows->sum('amount'))->sortDesc(),
            'extras' => [
                'with_extras' => $system->filter(fn (Booking $b) => $b->addons->isNotEmpty())->count(),
                'bookings' => $system->count(),
                'extra_revenue' => (int) $system->sum(fn (Booking $b) => $b->addons->sum('amount')),
            ],
            'coupons' => ['used' => $system->filter(fn (Booking $b) => $b->coupon_id !== null || (float) $b->discount_amount > 0)->count(), 'bookings' => $system->count()],
            'risk' => $risk->take(10),
            'activity' => app(CustomerActivityTags::class)->report(),
        ]);
    }

    /**
     * Mes a mes: personas distintas, cuántas venían por primera vez y cuántas ya habían venido
     * (tasa de recurrencia). El primer mes cargado no tiene meses anteriores para comparar.
     */
    private function monthly(Collection $visits, Collection $first, Carbon $start, Carbon $end): Collection
    {
        $oldestMonth = $first->min() ? substr($first->min(), 0, 7) : null;

        return $visits->filter(fn ($v) => $v['at']->between($start, $end))
            ->groupBy(fn ($v) => $v['at']->format('Y-m'))->sortKeys()
            ->map(function (Collection $rows, string $ym) use ($first, $oldestMonth) {
                $persons = $rows->pluck('person')->unique();
                $new = $persons->filter(fn ($p) => substr($first[$p], 0, 7) === $ym)->count();

                return [
                    'month' => $ym,
                    'label' => Carbon::parse($ym.'-01')->locale('es')->isoFormat('MMM YY'),
                    'people' => $persons->count(),
                    'new' => $new,
                    'returning' => $persons->count() - $new,
                    'rate' => $persons->count() ? (int) round(($persons->count() - $new) / $persons->count() * 100) : 0,
                    'comparable' => $ym !== $oldestMonth,
                ];
            })->values();
    }

    /** Cuánto tardan en volver: cada vuelta del rango, según los días desde la visita anterior. */
    private function gapBuckets(Collection $gaps): Collection
    {
        $defs = [['Hasta 7 días', 0, 7], ['8 a 15 días', 8, 15], ['16 a 30 días', 16, 30], ['31 a 60 días', 31, 60], ['61 a 90 días', 61, 90], ['Más de 90 días', 91, PHP_INT_MAX]];

        return collect($defs)->map(fn ($d) => ['label' => $d[0], 'count' => $gaps->filter(fn ($g) => $g >= $d[1] && $g <= $d[2])->count()]);
    }

    /**
     * De las personas que vinieron por primera vez en el rango, cuántas volvieron dentro de
     * 15, 30, 60 y 90 días. Solo cuenta a quienes ya tuvieron ese plazo completo para volver.
     */
    private function retention(Collection $days, Collection $first, string $startDay, string $endDay): Collection
    {
        $today = now(self::TZ)->toDateString();
        $newcomers = $first->filter(fn ($d) => $d >= $startDay && $d <= $endDay);

        return collect([15, 30, 60, 90])->map(function (int $n) use ($newcomers, $days, $today) {
            $eligible = $newcomers->filter(fn ($d) => Carbon::parse($d)->addDays($n)->toDateString() <= $today);
            $back = $eligible->filter(function ($d, $p) use ($days, $n) {
                $limit = Carbon::parse($d)->addDays($n)->toDateString();

                return $days[$p]->contains(fn ($day) => $day > $d && $day <= $limit);
            });

            return ['days' => $n, 'eligible' => $eligible->count(), 'back' => $back->count(), 'pct' => $eligible->count() ? (int) round($back->count() / $eligible->count() * 100) : 0];
        });
    }

    /** Cuántas veces vino cada persona del rango, en toda su historia hasta el fin del rango. */
    private function frequency(Collection $days, Collection $people, string $endDay): Collection
    {
        $defs = [['1 vez', 1, 1], ['2 veces', 2, 2], ['3 a 5 veces', 3, 5], ['6 a 10 veces', 6, 10], ['Más de 10', 11, PHP_INT_MAX]];
        $counts = $people->map(fn ($p) => $days[$p]->filter(fn ($d) => $d <= $endDay)->count());

        return collect($defs)->map(fn ($d) => ['label' => $d[0], 'count' => $counts->filter(fn ($c) => $c >= $d[1] && $c <= $d[2])->count()]);
    }

    /** Estadías por una clave, de más a menos (o por $sortBy si se da). */
    private function count(Collection $stays, callable $key, ?callable $sortBy = null): Collection
    {
        return $stays->groupBy($key)->map(fn (Collection $rows, $label) => [
            'label' => (string) $label,
            'count' => $rows->count(),
            'sort' => $sortBy ? $rows->min($sortBy) : -$rows->count(),
        ])->sortBy('sort')->values();
    }

    private function weekdays(Collection $stays): Collection
    {
        $names = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $counts = $stays->countBy(fn ($s) => $s['at']->dayOfWeekIso);

        return collect(range(1, 7))->map(fn ($d) => ['label' => $names[$d - 1], 'count' => (int) ($counts[$d] ?? 0)]);
    }

    private function dayparts(Collection $stays): Collection
    {
        $bucket = fn ($s) => match (true) {
            $s['at']->hour < 6 => 'Madrugada (00–06)',
            $s['at']->hour < 12 => 'Mañana (06–12)',
            $s['at']->hour < 18 => 'Tarde (12–18)',
            default => 'Noche (18–24)',
        };
        $counts = $stays->countBy($bucket);

        return collect(['Mañana (06–12)', 'Tarde (12–18)', 'Noche (18–24)', 'Madrugada (00–06)'])->map(fn ($l) => ['label' => $l, 'count' => (int) ($counts[$l] ?? 0)]);
    }

    /** Las personas que más vinieron en el rango, con lo que pagaron como titulares. */
    private function topPeople(Collection $visitsIn): Collection
    {
        $rows = $visitsIn->groupBy('person')->map(function (Collection $rows, $person) {
            $named = $rows->filter(fn ($v) => filled($v['name']))->sortBy(fn ($v) => $v['at']->getTimestamp());

            return [
                'person' => $person,
                'name' => $named->last()['name'] ?? 'Sin nombre',
                'customer_id' => $rows->pluck('customer_id')->filter()->last(),
                'visits' => $rows->pluck('stay')->unique()->count(),
                'total' => (int) $rows->sum('amount'),
                'last' => $rows->max('at'),
                'favorite' => $rows->countBy('category')->sortDesc()->keys()->first(),
            ];
        })->sortBy([['visits', 'desc'], ['total', 'desc']])->take(15)->values();

        // Si la persona de la planilla ya es cliente del sistema (mismo RUT), se enlaza a su ficha.
        $byRut = Customer::whereIn('rut', $rows->pluck('person')->filter(fn ($p) => ! str_starts_with($p, 'c')))->pluck('id', 'rut');

        return $rows->map(fn ($r) => $r + ['link' => $r['customer_id'] ?? $byRut[$r['person']] ?? null]);
    }

    /**
     * Suma a las fechas de cada cliente (customer_id => fechas) las visitas que tiene en la
     * planilla histórica por su RUT, sin repetir un día que ya está en el sistema.
     */
    private function withPlanilla(Collection $history): Collection
    {
        $ruts = Customer::whereIn('id', $history->keys())->whereNotNull('rut')->pluck('rut', 'id')
            ->map(fn ($rut) => Rut::normalize((string) $rut))->filter();
        $planilla = app(HistoricalCustomerAnalysis::class)->visitDates($ruts->values());
        if ($planilla->isEmpty()) {
            return $history;
        }

        return $history->map(function (Collection $dates, $id) use ($ruts, $planilla) {
            $old = $planilla[$ruts[$id] ?? ''] ?? collect();

            return $old->isEmpty() ? $dates : $dates->concat($old)
                ->unique(fn (Carbon $d) => $d->toDateString())->sortBy(fn (Carbon $d) => $d->timestamp)->values();
        });
    }

    /** @return array{0: Carbon, 1: Carbon, 2: bool} */
    private function range(Request $request): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            return [Carbon::parse($request->query('from'), self::TZ)->startOfDay(), Carbon::parse($request->query('to'), self::TZ)->endOfDay(), true];
        }
        $now = now(self::TZ);

        // Por defecto, todo lo cargado: desde lo más antiguo (planilla histórica o primera reserva) hasta hoy.
        $oldest = collect([
            HistoricalSale::min('sold_on'),
            Booking::whereNotIn('booking_status', self::EXCLUDED)->min('starts_at'),
        ])->filter()->map(fn ($d) => Carbon::parse($d, self::TZ))->min();

        return [($oldest ?? $now->copy()->subDays(89))->copy()->startOfDay(), $now->copy()->endOfDay(), false];
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

    /**
     * Clientes que venían seguido y llevan más tiempo del habitual sin volver: a ellos
     * conviene escribirles antes de perderlos. (No incluye los que ya están en lista negra.)
     */
    private function atRisk(): Collection
    {
        $stays = $this->withPlanilla(Booking::whereNotIn('booking_status', self::EXCLUDED)->where('starts_at', '<=', now())
            ->orderBy('starts_at')->get(['customer_id', 'starts_at'])->groupBy('customer_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($b) => $b->starts_at->timezone(self::TZ))->values()));
        $customers = Customer::whereIn('id', $stays->keys())->get()->keyBy('id');

        return $stays->map(function (Collection $dates, $id) use ($customers) {
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
