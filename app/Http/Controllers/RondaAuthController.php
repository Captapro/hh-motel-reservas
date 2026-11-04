<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RestrictRondaSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Ingreso a la Ronda de turno con nombre y PIN. El anfitrión que no tiene PIN
 * lo crea al entrar. La sesión queda limitada a la ronda (RestrictRondaSession)
 * y no abre ni cierra turno: eso sigue siendo el ingreso con correo y clave.
 */
class RondaAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('shift_round.index');
        }

        return view('rooms.ronda-login', ['hosts' => $this->hosts()->get(['id', 'name', 'ronda_pin'])]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['nullable', 'string'],
        ], [
            'user_id.required' => 'Elige tu nombre.',
            'pin.digits' => 'El PIN tiene 4 dígitos.',
        ]);

        $host = $this->hosts()->find($validated['user_id']);
        if (! $host) {
            return back()->withErrors(['pin' => 'Elige tu nombre.'])->withInput($request->only('user_id'));
        }

        if (! $host->ronda_pin) {
            if ($validated['pin'] !== ($validated['pin_confirmation'] ?? null)) {
                return back()->withErrors(['pin' => 'Los dos PIN no coinciden.'])->withInput($request->only('user_id'));
            }
            $host->forceFill(['ronda_pin' => $validated['pin']])->save();
            AuditLog::record($host->id, 'ronda.pin_creado', 'User', $host->id, null, null);
        } elseif (! Hash::check($validated['pin'], $host->ronda_pin)) {
            return back()->withErrors(['pin' => 'PIN incorrecto. Si no lo recuerdas, pide que lo borren en Personal.'])->withInput($request->only('user_id'));
        }

        Auth::login($host);
        $request->session()->regenerate();
        $request->session()->put(RestrictRondaSession::SESSION_KEY, true);

        return redirect()->route('shift_round.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('ronda.login');
    }

    private function hosts()
    {
        return User::where('role', 'recepcion')->where('is_active', true)->orderBy('name');
    }
}
