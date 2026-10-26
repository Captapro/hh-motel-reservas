<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ShortStaySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShortStaySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.short-stay.edit', ['setting' => ShortStaySetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reception_enabled' => ['required', 'boolean'],
            'catalog_enabled' => ['required', 'boolean'],
            'min_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'max_minutes' => ['required', 'integer', 'gte:min_minutes', 'max:720'],
            'pricing_mode' => ['required', Rule::in(array_keys(ShortStaySetting::MODES))],
            'percent' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'max_minutes.gte' => 'El máximo no puede ser menor que el mínimo.',
        ]);

        $setting = ShortStaySetting::current();
        $old = $setting->toArray();
        $setting->update($validated);
        AuditLog::record(auth()->id(), 'estadia_corta.reglas', 'ShortStaySetting', $setting->id, $old, $setting->fresh()->toArray());

        return redirect()->route('admin.short_stay.edit')->with('status', 'Reglas de estadía corta guardadas.');
    }
}
