<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\LoginEvent;
use App\Models\Payment;
use App\Models\Room;
use App\Models\ShiftHandover;
use App\Models\StaffShiftLog;
use App\Models\User;
use App\Support\ActivityLabels;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * «Actividad y accesos» (solo soporte): quién está en turno, historial de turnos con su entrega y sus
 * cobros, entradas al sistema (con IP y dispositivo) y las acciones que registra la bitácora.
 */
class ActivityController extends Controller
{
    private const TZ = 'America/Santiago';

    private const ROLE_LABELS = ['soporte' => 'Master', 'administrador' => 'Administrador', 'recepcion' => 'Anfitrión', 'marketing' => 'Marketing'];

    public function index(Request $request): View
    {
        $to = $request->filled('to') ? Carbon::parse($request->query('to'), self::TZ)->endOfDay() : now(self::TZ)->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->query('from'), self::TZ)->startOfDay() : $to->copy()->subDays(6)->startOfDay();
        $userId = (int) $request->query('user', 0) ?: null;
        $group = (string) $request->query('group', '');
        $prefixes = ActivityLabels::GROUPS[$group][1] ?? null;

        StaffShiftLog::closeStale();

        $open = StaffShiftLog::with(['user', 'staff'])->whereNull('ended_at')->orderBy('started_at')->get();

        $shifts = StaffShiftLog::with(['user', 'staff'])
            ->whereBetween('started_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('started_at')->limit(120)->get();
        $handovers = ShiftHandover::whereIn('staff_shift_log_id', $shifts->pluck('id'))->get()->groupBy('staff_shift_log_id');
        $payments = Payment::where('status', 'aprobado')
            ->whereIn('registered_by', $shifts->pluck('user_id')->filter()->unique())
            ->whereBetween('created_at', [$shifts->min('started_at') ?? $from, $to])
            ->get(['registered_by', 'amount', 'created_at'])->groupBy('registered_by');
        $shiftRows = $shifts->map(function (StaffShiftLog $s) use ($handovers, $payments) {
            $mine = ($s->user_id ? ($payments[$s->user_id] ?? collect()) : collect())->filter(fn ($p) => $p->created_at->gte($s->started_at) && ($s->ended_at === null || $p->created_at->lte($s->ended_at)));

            return [
                'log' => $s,
                'who' => $s->who()?->name ?? '—',
                'kind' => $s->staff_id ? 'Mucama' : (self::ROLE_LABELS[$s->user?->role] ?? 'Cuenta'),
                'handovers' => $handovers[$s->id] ?? collect(),
                'payments_count' => $mine->count(),
                'payments_sum' => (int) $mine->sum('amount'),
            ];
        });

        $logins = LoginEvent::with('user')
            ->whereBetween('created_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('created_at')->limit(120)->get();
        $failedLogins = LoginEvent::whereBetween('created_at', [$from, $to])->whereIn('result', ['fallido', 'desactivada'])->count();

        $logsQuery = AuditLog::with('user')
            ->whereBetween('created_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($prefixes, fn ($q) => $q->where(function ($w) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $w->orWhere('action', 'like', $prefix.'%');
                }
            }));
        $logs = (clone $logsQuery)->latest('created_at')->limit(200)->get();
        $sensitive = AuditLog::whereBetween('created_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereIn('action', ActivityLabels::SENSITIVE)
            ->get(['user_id', 'action'])
            ->groupBy(fn ($row) => (string) $row->user_id)
            ->map(fn (Collection $rows) => $rows->countBy('action')->all());

        $bookingCodes = Booking::whereIn('id', $logs->where('entity_type', 'Booking')->pluck('entity_id')->filter(fn ($i) => ctype_digit((string) $i))->unique())->pluck('code', 'id');
        $roomNames = Room::whereIn('id', $logs->where('entity_type', 'Room')->pluck('entity_id')->filter(fn ($i) => ctype_digit((string) $i))->unique())->pluck('name', 'id');
        $users = User::orderBy('name')->get(['id', 'name', 'role']);

        return view('admin.activity.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'userId' => $userId,
            'group' => $group,
            'users' => $users,
            'userNames' => $users->pluck('name', 'id'),
            'open' => $open,
            'shiftRows' => $shiftRows,
            'logins' => $logins,
            'failedLogins' => $failedLogins,
            'logs' => $logs,
            'logsLimited' => $logs->count() >= 200,
            'sensitive' => $sensitive,
            'bookingCodes' => $bookingCodes,
            'roomNames' => $roomNames,
        ]);
    }
}
