<?php

namespace App\Services\Pricing;

use App\Models\CalendarOverride;
use App\Models\RateRule;
use App\Models\RateRuleWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Determina qué tarifa (HH, HOT...) aplica a un rango horario completo.
 *
 * Las ventanas semanales de una tarifa se guardan por día (ver
 * rate_rule_windows) para poder representar tanto un horario que se reinicia
 * cada noche (HH) como un bloque continuo de varios días (HOT). Esta clase
 * "camina" el rango solicitado ventana por ventana y solo acepta la tarifa
 * si cubre el rango completo sin huecos.
 */
class RateRuleResolver
{
    /**
     * Lo que se lee de la base se recuerda por día mientras viva esta instancia
     * (una solicitud): el catálogo consulta los 96 horarios de un día seguidos,
     * y sin esto cada uno repetía las mismas consultas de tarifas y ventanas.
     *
     * @var array<string, ?CalendarOverride>
     */
    private array $overrides = [];

    /** @var array<string, Collection<int, RateRule>> */
    private array $candidates = [];

    private function overrideFor(string $date): ?CalendarOverride
    {
        if (! array_key_exists($date, $this->overrides)) {
            $this->overrides[$date] = CalendarOverride::with('forcedRateRule.windows')->where('date', $date)->first();
        }

        return $this->overrides[$date];
    }

    /** @return Collection<int, RateRule> */
    private function candidatesFor(string $date): Collection
    {
        return $this->candidates[$date] ??= RateRule::with('windows')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date))
            ->orderByDesc('priority')
            ->get();
    }

    public function resolve(Carbon $startsAt, Carbon $endsAt): ?RateRule
    {
        $override = $this->overrideFor($startsAt->toDateString());
        if ($override) {
            $rule = $override->forcedRateRule;
            if ($rule && $this->rangeFullyCoveredByRule($rule, $startsAt, $endsAt)) {
                return $rule;
            }
        }

        $candidates = $this->candidatesFor($startsAt->toDateString());

        foreach ($candidates as $rule) {
            if ($this->rangeFullyCoveredByRule($rule, $startsAt, $endsAt)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Tarifa vigente en este instante y hasta cuándo llega esa ventana — para
     * el banner del tablero. Devuelve null si "ahora" cae en un hueco entre
     * tarifas (ej. HH martes 04:00, antes de que abra a las 10:30).
     *
     * @return array{rate_rule: RateRule, closes_at: Carbon}|null
     */
    public function currentStatus(Carbon $now): ?array
    {
        $override = $this->overrideFor($now->toDateString());
        if ($override && $override->forcedRateRule) {
            $window = $this->findWindowCovering($override->forcedRateRule->windows, $now);
            if ($window) {
                return ['rate_rule' => $override->forcedRateRule, 'closes_at' => $this->closingTimeFor($override->forcedRateRule, $now)];
            }
        }

        $candidates = $this->candidatesFor($now->toDateString());

        foreach ($candidates as $rule) {
            $window = $this->findWindowCovering($rule->windows, $now);
            if ($window) {
                return ['rate_rule' => $rule, 'closes_at' => $this->closingTimeFor($rule, $now)];
            }
        }

        return null;
    }

    /** Próxima apertura, para cuando "ahora" cae en un hueco entre tarifas. */
    public function nextOpening(Carbon $now): ?Carbon
    {
        $best = null;

        foreach (RateRule::where('is_active', true)->with('windows')->get() as $rule) {
            foreach ($rule->windows as $window) {
                $daysAhead = ((int) $window->weekday - $now->dayOfWeek + 7) % 7;
                $candidate = $now->copy()->addDays($daysAhead)->setTimeFromTimeString($window->start_time);

                if ($candidate->lte($now)) {
                    $candidate->addWeek();
                }

                if (! $best || $candidate->lt($best)) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    private function closingTimeFor(RateRule $rule, Carbon $now): Carbon
    {
        $cursor = $now->copy();
        $guard = 0;

        while ($guard < 10) {
            $guard++;
            $window = $this->findWindowCovering($rule->windows, $cursor);
            if (! $window) {
                break;
            }
            $cursor = $this->windowCoverageEnd($window, $cursor);
        }

        return $cursor;
    }

    private function rangeFullyCoveredByRule(RateRule $rule, Carbon $startsAt, Carbon $endsAt): bool
    {
        $windows = $rule->windows;
        $cursor = $startsAt->copy();
        $guard = 0;

        while ($cursor->lt($endsAt) && $guard < 10) {
            $guard++;
            $window = $this->findWindowCovering($windows, $cursor);

            if (! $window) {
                return false;
            }

            $cursor = $this->windowCoverageEnd($window, $cursor);
        }

        return $cursor->gte($endsAt);
    }

    private function findWindowCovering(Collection $windows, Carbon $point): ?RateRuleWindow
    {
        $weekday = $point->dayOfWeek; // Carbon: 0 = domingo ... 6 = sábado
        $time = $point->format('H:i:s');
        $prevWeekday = ($weekday + 6) % 7;

        foreach ($windows as $window) {
            $windowWeekday = (int) $window->weekday;

            if ($windowWeekday === $weekday) {
                if (! $window->wraps_midnight) {
                    if ($time >= $window->start_time && $time < $window->end_time) {
                        return $window;
                    }
                } elseif ($time >= $window->start_time) {
                    return $window;
                }
            }

            if ($windowWeekday === $prevWeekday && $window->wraps_midnight && $time < $window->end_time) {
                return $window;
            }
        }

        return null;
    }

    private function windowCoverageEnd(RateRuleWindow $window, Carbon $cursor): Carbon
    {
        $sameDay = (int) $window->weekday === $cursor->dayOfWeek;

        if ($sameDay) {
            $end = $cursor->copy();
            if ($window->wraps_midnight) {
                $end->addDay();
            }

            return $end->setTimeFromTimeString($window->end_time);
        }

        // Estamos en la porción "envuelta" del día siguiente.
        return $cursor->copy()->setTimeFromTimeString($window->end_time);
    }
}
