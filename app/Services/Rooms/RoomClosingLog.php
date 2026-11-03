<?php

namespace App\Services\Rooms;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\OperationalSetting;
use App\Models\Room;
use App\Models\RoomClosingSnapshot;
use App\Models\User;
use App\Services\Pricing\RateRuleResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cierre de Playrooms: cómo quedó cada uno al terminar el día operativo (lista,
 * en aseo, ocupada, fuera de servicio) y a qué hora se fue habilitando al día
 * siguiente, con quién lo hizo. El día operativo corta a las 03:00 (la hora en
 * que cierra de lunes a jueves); lo de la madrugada cuenta para el día anterior.
 *
 * app:reservas-expirar toma la foto justo después del corte. Los días sin foto
 * (antes de que existiera, o si el servidor estaba caído) se reconstruyen del
 * registro de auditoría: el estado al corte es el "antes" del primer cambio
 * posterior, o el estado actual si no cambió desde entonces.
 */
class RoomClosingLog
{
    public const TZ = 'America/Santiago';

    public const CUT_TIME = '03:00';

    /** Después de esto la foto en vivo ya no representa el cierre: se reconstruye. */
    private const LIVE_SNAPSHOT_MINUTES = 30;

    public const STATUS_ACTIONS = ['habitacion.estado_operativo', 'habitacion.aseo_listo', 'habitacion.enviar_aseo'];

    public const TOGGLE_ACTIONS = ['operacion.piso', 'operacion.categoria'];

    public const STATUS_LABELS = [
        'activa' => 'Lista',
        'aseo' => 'En aseo',
        'ocupada' => 'Marcada ocupada',
        'mantencion' => 'Mantención',
        'inactiva' => 'Inactiva',
    ];

    public function __construct(private readonly RateRuleResolver $rates) {}

    /** Corte (03:00 del día siguiente) con el que cierra el día operativo $date. */
    public static function cutFor(Carbon $date): Carbon
    {
        return Carbon::parse($date->toDateString().' '.self::CUT_TIME, self::TZ)->addDay()->timezone(config('app.timezone'));
    }

    /** Último día operativo ya cerrado. */
    public static function lastClosedDate(?Carbon $now = null): Carbon
    {
        $now = ($now ?? now())->copy()->timezone(self::TZ);
        $cut = $now->copy()->setTimeFromTimeString(self::CUT_TIME);
        if ($now->lt($cut)) {
            $cut->subDay();
        }

        return $cut->subDay()->startOfDay();
    }

    /** Toma la foto del último cierre si todavía no está y recién pasó el corte. */
    public function takeDueSnapshot(): int
    {
        $date = self::lastClosedDate();
        $cut = self::cutFor($date);
        if (now()->gt($cut->copy()->addMinutes(self::LIVE_SNAPSHOT_MINUTES))
            || RoomClosingSnapshot::where('operating_date', $date->toDateString())->exists()) {
            return 0;
        }

        $setting = OperationalSetting::current();
        $occupied = Booking::whereNotNull('checked_in_at')->whereNull('checked_out_at')->pluck('room_id')->flip();
        $taken = 0;
        foreach (Room::with('category')->get() as $room) {
            $snapshot = RoomClosingSnapshot::firstOrCreate(
                ['operating_date' => $date->toDateString(), 'room_id' => $room->id],
                [
                    'operational_status' => $room->operational_status,
                    'operational_note' => $room->operational_note,
                    'occupied' => $occupied->has($room->id),
                    'offered' => $room->isFloorWingEnabled($setting) && ($room->category?->is_active ?? true),
                    'taken_at' => now(),
                ]
            );
            $taken += $snapshot->wasRecentlyCreated ? 1 : 0;
        }

        return $taken;
    }

    /**
     * Un día: cada Playroom con su estado al cierre y su primera habilitación
     * del día siguiente, más los pisos/categorías que se prendieron o apagaron.
     */
    public function day(Carbon $date): array
    {
        $date = $date->copy()->timezone(self::TZ)->startOfDay();
        $cut = self::cutFor($date);
        $nextCut = self::cutFor($date->copy()->addDay());
        $rooms = Room::with('category')->orderBy('name')->get();
        $states = $this->statesAt($cut, $rooms, $date);
        $statusLogs = $this->statusLogs($cut, $nextCut);
        $users = $this->userNames($statusLogs);

        $rows = $rooms->map(function (Room $room) use ($states, $statusLogs, $users) {
            $state = $states[$room->id];
            $opening = $this->firstOpening($statusLogs->where('entity_id', (string) $room->id));

            return [
                'room' => $room,
                'state' => $state,
                'ready' => $state['status'] === 'activa' && ! $state['occupied'],
                'opened_at' => $opening?->created_at->copy()->timezone(self::TZ),
                'opened_by' => $opening ? ($users[$opening->user_id] ?? null) : null,
                'cleaned_by' => $opening?->new_value['cleaned_by'] ?? null,
                'on_check_in' => (bool) ($opening?->new_value['al_ingresar'] ?? false),
            ];
        });

        $toggles = AuditLog::whereIn('action', self::TOGGLE_ACTIONS)
            ->where('created_at', '>=', $cut)->where('created_at', '<', $nextCut)
            ->orderBy('created_at')->get();
        $users += $this->userNames($toggles);

        return [
            'date' => $date,
            'cut' => $cut,
            'motel_opens_at' => $this->motelOpening($cut),
            'source' => $states->first()['source'] ?? null,
            'rows' => $rows,
            'summary' => $this->summarize($rows),
            'toggles' => $toggles->map(fn (AuditLog $log) => [
                'at' => $log->created_at->copy()->timezone(self::TZ),
                'label' => $log->new_value['label'] ?? '—',
                'enabled' => (bool) ($log->new_value['enabled'] ?? false),
                'by' => $users[$log->user_id] ?? null,
            ]),
        ];
    }

    /**
     * Resumen por día para el historial (del más reciente al más antiguo).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function history(Carbon $until, int $days = 30): Collection
    {
        $until = $until->copy()->timezone(self::TZ)->startOfDay();
        $first = $this->firstTrackedDate();
        if (! $first) {
            return collect();
        }

        $rooms = Room::with('category')->get();
        $from = $until->copy()->subDays($days - 1)->max($first);
        $logs = $this->statusLogs(self::cutFor($from), self::cutFor($until->copy()->addDay()));

        $out = collect();
        for ($d = $until->copy(); $d->gte($from); $d->subDay()) {
            $cut = self::cutFor($d);
            $nextCut = self::cutFor($d->copy()->addDay());
            $states = $this->statesAt($cut, $rooms, $d);
            $dayLogs = $logs->filter(fn (AuditLog $l) => $l->created_at->gte($cut) && $l->created_at->lt($nextCut));
            $rows = $rooms->map(fn (Room $room) => [
                'state' => $states[$room->id],
                'ready' => $states[$room->id]['status'] === 'activa' && ! $states[$room->id]['occupied'],
                'opened_at' => $this->firstOpening($dayLogs->where('entity_id', (string) $room->id))?->created_at->copy()->timezone(self::TZ),
            ]);

            $out->push(['date' => $d->copy(), 'motel_opens_at' => $this->motelOpening($cut), 'source' => $states->first()['source'] ?? null] + $this->summarize($rows));
        }

        return $out;
    }

    /** Primer día con registro de cambios de estado (antes no se puede reconstruir). */
    public function firstTrackedDate(): ?Carbon
    {
        $snapshot = RoomClosingSnapshot::min('operating_date');
        $log = AuditLog::whereIn('action', self::STATUS_ACTIONS)->min('created_at');
        $dates = collect([
            $snapshot ? Carbon::parse($snapshot, self::TZ)->startOfDay() : null,
            $log ? Carbon::parse($log)->timezone(self::TZ)->startOfDay() : null,
        ])->filter();

        return $dates->isEmpty() ? null : $dates->min();
    }

    /**
     * Estado de cada Playroom en el corte: la foto si existe; si no, reconstruido.
     *
     * @return Collection<int, array{status: ?string, note: ?string, occupied: bool, offered: ?bool, source: string}>
     */
    private function statesAt(Carbon $cut, Collection $rooms, Carbon $date): Collection
    {
        $snapshots = RoomClosingSnapshot::where('operating_date', $date->toDateString())->get()->keyBy('room_id');
        $missing = $rooms->reject(fn (Room $r) => $snapshots->has($r->id));
        $after = $missing->isEmpty() ? collect() : AuditLog::whereIn('action', self::STATUS_ACTIONS)->where('entity_type', 'Room')
            ->whereIn('entity_id', $missing->pluck('id')->map(fn ($id) => (string) $id))
            ->where('created_at', '>=', $cut)->orderBy('created_at')->orderBy('id')->get()
            ->groupBy('entity_id')->map->first();
        $occupied = $missing->isEmpty() ? collect() : Booking::whereIn('room_id', $missing->pluck('id'))
            ->whereNotNull('checked_in_at')->where('checked_in_at', '<=', $cut)
            ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhere('checked_out_at', '>', $cut))
            ->pluck('room_id')->flip();

        return $rooms->mapWithKeys(function (Room $room) use ($snapshots, $after, $occupied) {
            if ($s = $snapshots->get($room->id)) {
                return [$room->id => ['status' => $s->operational_status, 'note' => $s->operational_note, 'occupied' => $s->occupied, 'offered' => $s->offered, 'source' => 'foto']];
            }
            $next = $after->get((string) $room->id);

            return [$room->id => [
                'status' => $next ? ($next->old_value['operational_status'] ?? null) : $room->operational_status,
                'note' => $next ? ($next->old_value['operational_note'] ?? null) : $room->operational_note,
                'occupied' => $occupied->has($room->id),
                'offered' => null,
                'source' => 'registro',
            ]];
        });
    }

    private function statusLogs(Carbon $from, Carbon $to): Collection
    {
        return AuditLog::whereIn('action', self::STATUS_ACTIONS)->where('entity_type', 'Room')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->orderBy('created_at')->orderBy('id')->get();
    }

    /** Primer paso a "lista" (desde otro estado) dentro de los registros dados. */
    private function firstOpening(Collection $logs): ?AuditLog
    {
        return $logs->first(fn (AuditLog $l) => ($l->new_value['operational_status'] ?? null) === 'activa'
            && ($l->old_value['operational_status'] ?? null) !== 'activa');
    }

    private function summarize(Collection $rows): array
    {
        $opened = $rows->pluck('opened_at')->filter()->sort()->values();
        $notReady = $rows->reject(fn ($r) => $r['ready'] || $r['state']['occupied']);

        return [
            'ready' => $rows->where('ready', true)->count(),
            'occupied' => $rows->filter(fn ($r) => $r['state']['occupied'])->count(),
            'aseo' => $notReady->filter(fn ($r) => $r['state']['status'] === 'aseo')->count(),
            'out' => $notReady->filter(fn ($r) => in_array($r['state']['status'], ['mantencion', 'inactiva', 'ocupada'], true))->count(),
            'unknown' => $notReady->filter(fn ($r) => $r['state']['status'] === null)->count(),
            'pending_left' => $notReady->filter(fn ($r) => $r['state']['status'] === 'aseo' && ! $r['opened_at'])->count(),
            'opened' => $opened->count(),
            'first_opened' => $opened->first(),
            'last_opened' => $opened->last(),
        ];
    }

    /** Hora en que abrió el motel después del corte (null si a esa hora ya estaba abierto). */
    private function motelOpening(Carbon $cut): ?Carbon
    {
        $at = $cut->copy()->timezone(self::TZ);
        if ($this->rates->currentStatus($at)) {
            return null;
        }

        return $this->rates->nextOpening($at)?->timezone(self::TZ);
    }

    private function userNames(Collection $logs): array
    {
        $ids = $logs->pluck('user_id')->filter()->unique();

        return $ids->isEmpty() ? [] : User::whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
