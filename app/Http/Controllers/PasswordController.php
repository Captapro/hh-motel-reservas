<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Cambiar mi contraseña" — cualquier cuenta del panel (también el anfitrión,
 * que no ve Personal) cambia la suya pidiendo la actual.
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La nueva contraseña no coincide en los dos campos.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ]);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);
        AuditLog::record($user->id, 'cuenta.cambiar_clave', 'User', $user->id, null, null);

        return redirect()->route('account.password')->with('status', 'Listo, tu contraseña quedó cambiada.');
    }
}
