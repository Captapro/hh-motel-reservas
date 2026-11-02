<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TvScreenSetting;
use App\Services\Tv\TvScreenSlides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Programación de la pantalla del TV: qué diapositivas salen, en qué orden, cuánto duran y mensajes propios. */
class TvScreenSettingController extends Controller
{
    public function edit(TvScreenSlides $slides): View
    {
        $setting = TvScreenSetting::current();

        return view('admin.tv-screen.edit', ['setting' => $setting, 'playlist' => $slides->playlist($setting)]);
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
            'playlist' => ['nullable', 'array'],
            'playlist.*.key' => ['required', 'string', 'max:40'],
            'playlist.*.on' => ['nullable', 'boolean'],
        ], [
            'combo_seconds.min' => 'Cada combo tiene que durar al menos 4 segundos.',
            'qr_seconds.min' => 'Cada QR tiene que durar al menos 4 segundos.',
        ]);

        $setting = TvScreenSetting::current();
        $old = $setting->toArray();
        $setting->update([
            'show_combo_contents' => $request->boolean('show_combo_contents'),
            'combo_cta' => trim((string) ($validated['combo_cta'] ?? '')),
            'combo_seconds' => $validated['combo_seconds'],
            'qr_seconds' => $validated['qr_seconds'],
            // Se guardan las 5 filas en su lugar (aunque estén vacías) para que
            // 'message:{n}' de la lista siga apuntando al mismo mensaje.
            'messages' => collect(range(0, TvScreenSetting::MAX_MESSAGES - 1))
                ->map(fn ($i) => ['title' => trim((string) ($validated['messages'][$i]['title'] ?? '')), 'text' => trim((string) ($validated['messages'][$i]['text'] ?? ''))])
                ->all(),
            // Orden y encendido de cada diapositiva, tal como viene la lista.
            'playlist' => collect($validated['playlist'] ?? [])
                ->map(fn ($row) => ['key' => $row['key'], 'on' => ! empty($row['on'])])
                ->unique('key')
                ->values()
                ->all(),
        ]);
        AuditLog::record(auth()->id(), 'pantalla_tv.programacion', 'TvScreenSetting', $setting->id, $old, $setting->fresh()->toArray());

        return redirect()->route('admin.tv_screen.edit')->with('status', 'Pantalla guardada. El TV la toma en su próxima recarga (máximo 2 minutos).');
    }
}
