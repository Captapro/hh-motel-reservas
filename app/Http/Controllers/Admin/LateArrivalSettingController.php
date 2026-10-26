<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LateArrivalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LateArrivalSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.late-arrival.edit', ['setting' => LateArrivalSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'mode' => ['required', Rule::in(array_keys(LateArrivalSetting::MODES))],
        ]);

        $setting = LateArrivalSetting::current();
        $old = $setting->toArray();
        $setting->update($validated);
        AuditLog::record(auth()->id(), 'atrasos.reglas', 'LateArrivalSetting', $setting->id, $old, $setting->fresh()->toArray());

        return redirect()->route('admin.late_arrival.edit')->with('status', 'Regla de atrasos guardada.');
    }
}
