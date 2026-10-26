<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => Staff::orderBy('role')->orderBy('name')->get(),
            'accounts' => User::when(! auth()->user()?->isSoporte(), fn ($q) => $q->where('role', '!=', 'soporte'))->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    /** El administrador solo da de alta Anfitriones y Mucamas; el Master puede poner cualquier rol. */
    private function roleRules(?string $current = null): array
    {
        // Al editar, un rol que ya tenía se puede conservar aunque sea otro.
        return auth()->user()?->isSoporte()
            ? ['required', 'string', 'max:50']
            : ['required', 'string', 'max:50', Rule::in(array_filter(['Mucama', 'Anfitrión', $current]))];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => $this->roleRules(),
            'pin' => ['nullable', 'digits:4'],
            'legal_hours_per_week' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $person = Staff::create($validated);
        AuditLog::record(auth()->id(), 'personal.crear', 'Staff', $person->id, null, $person->toArray());

        return redirect()->route('admin.staff.index')->with('status', 'Persona agregada.');
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => $this->roleRules($staff->role),
            'pin' => ['nullable', 'digits:4'],
            'legal_hours_per_week' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        // Campo en blanco = no tocar el PIN que ya tenía -- si querían
        // sacárselo de verdad, "resetear" es la acción explícita, no dejar
        // el campo vacío sin querer al editar otra cosa.
        if (! $validated['pin']) {
            unset($validated['pin']);
        }

        $old = $staff->toArray();
        $staff->update($validated);
        AuditLog::record(auth()->id(), 'personal.editar', 'Staff', $staff->id, $old, $staff->fresh()->toArray());

        return redirect()->route('admin.staff.index')->with('status', 'Datos actualizados.');
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        // Se borran también sus turnos programados y su registro de horas.
        $old = $staff->only(['id', 'name', 'role', 'legal_hours_per_week', 'is_active']);
        $staff->delete();
        AuditLog::record(auth()->id(), 'personal.eliminar', 'Staff', $old['id'], $old, null);

        return redirect()->route('admin.staff.index')->with('status', $old['name'].' eliminado/a del personal, junto con sus turnos.');
    }
}
