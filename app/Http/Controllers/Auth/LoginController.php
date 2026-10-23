<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffShiftLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Datos incorrectos.'])->onlyInput('email');
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Esta cuenta está desactivada.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Mismo registro real de entrada/salida que ya tienen las mucamas
        // (StaffShiftLog) -- un login = un turno, no uno por cada vez que
        // vuelve a entrar sin haber cerrado sesión antes.
        $user = Auth::user();
        if (! $user->openShiftLog()) {
            StaffShiftLog::create(['user_id' => $user->id, 'started_at' => now()]);
        }

        return redirect()->intended(route($user->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::user()?->openShiftLog()?->update(['ended_at' => now()]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
