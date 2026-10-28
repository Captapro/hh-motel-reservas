<?php

namespace App\Http\Controllers;

use App\Models\ShiftHandover;
use App\Services\Shifts\ShiftHandoverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Entrega de turno del anfitrión. Al entrar no puede trabajar hasta recibir
 * el turno (EnsureShiftReceived); al salir, "Salir" lo trae a entregarlo.
 * El conteo es a ciegas: no ve cuánto debería haber hasta después.
 */
class ShiftHandoverController extends Controller
{
    public function __construct(private ShiftHandoverService $handovers) {}

    public function receive(): View
    {
        return $this->form('recibir');
    }

    public function deliver(): View
    {
        return $this->form('entregar');
    }

    private function form(string $kind): View
    {
        return view('shifts.handover', [
            'kind' => $kind,
            'products' => $this->handovers->countableProducts(),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, ['recibir', 'entregar'], true), 404);

        $validated = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0'],
            'counts' => ['array'],
            'counts.*' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'counted_cash.required' => 'Escribe cuánto efectivo contaste en la caja chica.',
            'counts.*.required' => 'Falta contar un producto (si no hay, pon 0).',
        ]);

        $handover = $this->handovers->record(
            $request->user(),
            $kind,
            (int) $validated['counted_cash'],
            array_map('intval', $validated['counts'] ?? []),
            $validated['notes'] ?? null,
        );

        $summary = $handover->has_differences
            ? 'Quedó registrado con diferencias y se avisó al administrador.'
            : 'Todo cuadró.';

        if ($kind === 'entregar') {
            $request->user()->openShiftLog()?->update(['ended_at' => now()]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Turno entregado. '.$summary);
        }

        return redirect()->route($request->user()->homeRoute())->with('status', 'Turno recibido. '.$summary);
    }

    public function index(): View
    {
        return view('cash.shifts', [
            'handovers' => ShiftHandover::with(['user', 'items'])->latest()->limit(80)->get(),
        ]);
    }

    public function review(Request $request, ShiftHandover $handover): RedirectResponse
    {
        $handover->update(['reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);

        return back()->with('status', 'Marcada como revisada.');
    }
}
