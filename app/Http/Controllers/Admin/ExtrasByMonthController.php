<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Analyst\HistoricalDataService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * «Extras por mes»: cómo va creciendo la venta de extras (consumos y extras core) mes a mes,
 * con lo de las planillas y, aparte, lo que ya vende el sistema. Solo cifras.
 */
class ExtrasByMonthController extends Controller
{
    private const TZ = 'America/Santiago';

    public function index(HistoricalDataService $historical): View
    {
        // Planillas y, después de su último mes, lo que vende el sistema: así la tabla sigue
        // sumando meses sola (antes se quedaba en octubre, 12-nov).
        $months = $historical->extrasByMonth();
        $lastPlanilla = $months->last()['month'] ?? null;
        $months = $months->concat($this->systemMonths()
            ->filter(fn (array $m) => $lastPlanilla === null || $m['month']->gt($lastPlanilla)))->values();

        // Cada mes frente al anterior: en pesos y por estadía (con meses distintos, lo segundo es lo justo).
        $perStay = fn (array $m) => $m['stays'] ? (int) round($m['extras'] / $m['stays']) : 0;
        $rows = $months->map(function (array $m, int $i) use ($months, $perStay) {
            $prev = $i > 0 ? $months[$i - 1] : null;
            $change = fn (int $now, int $before) => $before > 0 ? (int) round(($now - $before) / $before * 100) : null;

            return $m + [
                'per_stay' => $perStay($m),
                'per_day' => $m['days'] ? (int) round($m['extras'] / $m['days']) : 0,
                'share' => $m['total'] ? round($m['extras'] / $m['total'] * 100, 1) : 0.0,
                'core_per_stay' => $m['stays'] ? (int) round($m['core'] / $m['stays']) : 0,
                // Un mes incompleto no se compara en pesos con uno completo: se compara por estadía.
                'change_extras' => $prev && ! $m['partial'] ? $change($m['extras'], $prev['extras']) : null,
                'change_per_stay' => $prev ? $change($perStay($m), $perStay($prev)) : null,
                'change_core' => $prev && ! $m['partial'] ? $change($m['core'], $prev['core']) : null,
            ];
        });

        return view('admin.analytics.extras', [
            'rows' => $rows,
            'system' => $this->system()->filter(fn (array $m) => $lastPlanilla !== null && $m['month'] <= $lastPlanilla->format('Y-m'))->values(),
        ]);
    }

    /**
     * Meses del sistema con la misma forma que los de la planilla: estadías pagadas completas,
     * extras (consumos) y extras core (hora extra, persona adicional, servicios) ya pagados.
     */
    private function systemMonths(): Collection
    {
        $today = now(self::TZ);

        return Booking::with(['addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->where('starts_at', '<=', now())
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale())
            ->groupBy(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->format('Y-m'))
            ->sortKeys()
            ->map(function (Collection $g, string $ym) use ($today) {
                $first = Carbon::parse($ym.'-01', self::TZ);
                $lastDay = Carbon::parse($g->max(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->toDateString()));
                $core = (int) $g->sum(fn (Booking $b) => $b->paidCoreExtrasAmount());
                $extras = (int) $g->sum(fn (Booking $b) => $b->paidAddonsAmount()) - $core;
                $lodging = (int) $g->sum('price_final');

                return [
                    'month' => $first,
                    'stays' => $g->count(),
                    'days' => $g->map(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->toDateString())->unique()->count(),
                    'lodging' => $lodging, 'extras' => $extras, 'core' => $core, 'total' => $lodging + $extras + $core,
                    'partial' => $first->isSameMonth($today),
                    'last_day' => $lastDay,
                    'source' => 'sistema',
                ];
            })->values();
    }

    /** Lo que vende el sistema por mes (reservas con su pago completo), aparte de las planillas. */
    private function system(): Collection
    {
        return Booking::with(['addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])->get()
            ->filter(fn (Booking $b) => $b->isPaidSale())
            ->groupBy(fn (Booking $b) => $b->starts_at->copy()->timezone(self::TZ)->format('Y-m'))
            ->map(fn (Collection $rows, string $ym) => [
                'month' => $ym,
                'stays' => $rows->count(),
                'extras' => (int) $rows->sum(fn (Booking $b) => $b->paidAddonsAmount()),
            ])
            ->filter(fn (array $m) => $m['extras'] > 0 || $m['stays'] > 0)
            ->sortKeys()->values();
    }
}
