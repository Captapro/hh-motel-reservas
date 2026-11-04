<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ShiftNote;
use App\Models\StaffNotice;
use App\Services\Booking\RoomBoardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** App de administración: el día de un vistazo desde el celular y avisos al personal. */
class AdminAppController extends Controller
{
    private const TZ = 'America/Santiago';

    public function index(RoomBoardService $board): View
    {
        $today = now(self::TZ)->startOfDay();
        $tonight = $today->copy()->endOfDay();
        $groups = $board->byCondition();

        return view('admin-app.index', [
            'sales' => [
                'Hoy' => $this->sales($today, $tonight),
                'Ayer' => $this->sales($today->copy()->subDay(), $today->copy()->subDay()->endOfDay()),
                'Esta semana' => $this->sales($today->copy()->startOfWeek(), $tonight),
                'Este mes' => $this->sales($today->copy()->startOfMonth(), $tonight),
            ],
            'pulse' => $this->pulseMark(),
            'cashToday' => (int) Payment::where('status', 'aprobado')->whereBetween('created_at', [$today, $tonight])->sum('amount'),
            'occupied' => $groups['occupied'],
            'aseo' => $groups['aseo'],
            'out' => $groups['out'],
            'freeCount' => $groups['free']->count(),
            'upcoming' => Booking::with(['room', 'customer', 'payments'])
                ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW', 'FINALIZADA'])
                ->whereNull('checked_in_at')
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')->get(),
            'notes' => ShiftNote::today(),
            'notices' => StaffNotice::with('reads')->where('audience', '!=', 'administracion')->latest()->take(5)->get(),
        ]);
    }

    /** Última reserva y último pago: la app los compara para sonar cuando entra algo nuevo. */
    public function pulse(Request $request): JsonResponse
    {
        $after = $request->integer('desde');

        return response()->json($this->pulseMark() + [
            'new' => ! $request->has('desde') ? [] : Booking::with('room')->where('id', '>', $after)
                ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
                ->orderBy('id')->take(5)->get()
                ->map(fn (Booking $b) => [
                    'origin' => $b->originLabel(),
                    'room' => $b->room?->name,
                    'at' => $b->starts_at->timezone(self::TZ)->format('H:i'),
                    'amount' => (int) $b->price_final,
                ])->values(),
        ]);
    }

    private function pulseMark(): array
    {
        return [
            'booking' => (int) Booking::max('id'),
            'payment' => (int) Payment::where('status', 'aprobado')->max('id'),
        ];
    }

    public function notify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(array_keys(StaffNotice::AUDIENCES))],
            'body' => ['required', 'string', 'max:500'],
            'urgent' => ['nullable', 'boolean'],
        ], ['body.required' => 'Escribe el aviso.']);

        $notice = StaffNotice::create($validated + ['user_id' => $request->user()->id, 'urgent' => $request->boolean('urgent')]);
        AuditLog::record($request->user()->id, 'aviso.enviar', 'StaffNotice', $notice->id, null, $notice->only(['audience', 'urgent']));

        return back()->with('status', 'Aviso enviado.');
    }

    /** Mismo criterio que Ventas del día: estadías del período con su pago completo. */
    private function sales(Carbon $from, Carbon $to): array
    {
        $sold = Booking::with(['addons', 'payments'])
            ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA'])
            ->whereBetween('starts_at', [$from, $to])
            ->get()
            ->filter(fn (Booking $b) => $b->isPaidSale());

        return ['count' => $sold->count(), 'total' => (int) $sold->sum(fn (Booking $b) => $b->price_final + $b->paidAddonsAmount())];
    }
}
