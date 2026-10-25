<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /** Roles que quien está logueado puede asignar: solo soporte puede crear otro soporte. */
    private function assignableRoles(): array
    {
        return array_merge(['administrador', 'recepcion', 'marketing'], auth()->user()?->isSoporte() ? ['soporte'] : []);
    }

    /** Las cuentas de soporte solo las administra otro soporte; para un administrador no existen. */
    private function guardSoporte(User $user): void
    {
        abort_if($user->role === 'soporte' && ! auth()->user()?->isSoporte(), 404);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ]);

        $password = Str::password(12);

        $user = User::create([
            ...$validated,
            'is_active' => true,
            'password' => $password,
        ]);

        AuditLog::record(auth()->id(), 'cuenta.crear', 'User', $user->id, null, $user->only(['name', 'email', 'role']));

        return redirect()->route('admin.staff.index')
            ->with('status', "Cuenta creada para {$user->email} — contraseña temporal: {$password} (avísale que la cambie apenas entre).");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardSoporte($user);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $old = $user->only(['name', 'email', 'role', 'is_active']);
        $newPassword = $validated['password'] ?? null;
        unset($validated['password']);
        $user->update($validated);
        if ($newPassword) {
            $user->update(['password' => $newPassword]);
        }

        AuditLog::record(auth()->id(), 'cuenta.editar', 'User', $user->id, $old, $user->only(['name', 'email', 'role', 'is_active']));

        return redirect()->route('admin.staff.index')->with('status', 'Cuenta actualizada.'.($newPassword ? ' Contraseña reseteada a: '.$newPassword : ''));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->guardSoporte($user);
        if ($user->id === auth()->id()) {
            return back()->withErrors(['account' => 'No puedes eliminar tu propia cuenta mientras estás dentro. Entra con otro administrador para hacerlo.']);
        }
        // Pagos, reservas y registros de auditoría que esta cuenta hizo se
        // conservan; solo pierden el nombre de quién los registró.
        $old = $user->only(['id', 'name', 'email', 'role', 'is_active']);
        $user->delete();
        AuditLog::record(auth()->id(), 'cuenta.eliminar', 'User', $old['id'], $old, null);

        return redirect()->route('admin.staff.index')->with('status', 'Cuenta de '.$old['name'].' eliminada.');
    }
}
