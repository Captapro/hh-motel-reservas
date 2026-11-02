<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TvScreenSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Programación de la pantalla del TV: qué se muestra, cuánto dura y mensajes propios. */
class TvScreenSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.tv-screen.edit', ['setting' => TvScreenSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'combo_cta' => ['nullable', 'string', 'max:120'],
            'combo_seconds' => ['required', 'integer', 'min:4', 'max:120'],
            'qr_seconds' => ['required', 'integer', 'min:4', 'max:120'],
            'messages' => ['nullable', 'array', 'max:'.TvScreenSetting::MAX_MESSAGES],
            'messages.*.title' => ['nullable', 'string', 'max:60'],
            'messages.*.text' => ['nullable', 'string', 'max:200'],
            'messages.*.active' => ['nullable', 'boolean'],
        ], [
            'combo_seconds.min' => 'Cada combo tiene que durar al menos 4 segundos.',
            'qr_seconds.min' => 'Cada QR tiene que durar al menos 4 segundos.',
        ]);

        $setting = TvScreenSetting::current();
        $old = $setting->toArray();
        $setting->update([
            'show_combos' => $request->boolean('show_combos'),
            'show_combo_contents' => $request->boolean('show_combo_contents'),
            'combo_cta' => trim((string) ($validated['combo_cta'] ?? '')),
            'show_reserve_qr' => $request->boolean('show_reserve_qr'),
            'show_review_qr' => $request->boolean('show_review_qr'),
            'combo_seconds' => $validated['combo_seconds'],
            'qr_seconds' => $validated['qr_seconds'],
            // Las filas vacías no se guardan.
            'messages' => collect($validated['messages'] ?? [])
                ->filter(fn ($m) => filled($m['title'] ?? null) || filled($m['text'] ?? null))
                ->map(fn ($m) => ['title' => trim((string) ($m['title'] ?? '')), 'text' => trim((string) ($m['text'] ?? '')), 'active' => ! empty($m['active'])])
                ->values()
                ->all(),
        ]);
        AuditLog::record(auth()->id(), 'pantalla_tv.programacion', 'TvScreenSetting', $setting->id, $old, $setting->fresh()->toArray());

        return redirect()->route('admin.tv_screen.edit')->with('status', 'Pantalla guardada. El TV la toma en su próxima recarga (máximo 2 minutos).');
    }
}
