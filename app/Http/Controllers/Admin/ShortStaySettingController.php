<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RateRule;
use App\Models\ShortStaySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShortStaySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.short-stay.edit', ['setting' => ShortStaySetting::current(), 'calculator' => $this->calculatorPrices()]);
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
            'tiers' => ['nullable', 'array', 'size:3'],
            'tiers.*.up_to' => ['required_with:tiers', 'integer', 'min:5', 'max:720', 'distinct'],
            'tiers.*.percent' => ['required_with:tiers', 'integer', 'min:0', 'max:100'],
        ], [
            'max_minutes.gte' => 'El máximo no puede ser menor que el mínimo.',
            'tiers.*.up_to.distinct' => 'Cada tramo debe tener un tiempo distinto.',
        ]);

        if (isset($validated['tiers'])) {
            $validated['tiers'] = collect($validated['tiers'])
                ->map(fn (array $tier) => ['up_to' => (int) $tier['up_to'], 'percent' => (int) $tier['percent']])
                ->sortBy('up_to')->values()->all();
        }

        $setting = ShortStaySetting::current();
        $old = $setting->toArray();
        $setting->update($validated);
        AuditLog::record(auth()->id(), 'estadia_corta.reglas', 'ShortStaySetting', $setting->id, $old, $setting->fresh()->toArray());

        return redirect()->route('admin.short_stay.edit')->with('status', 'Reglas de estadía corta guardadas.');
    }

    /** Duración mínima y precio de cada categoría, por tarifa, para la calculadora. */
    private function calculatorPrices(): array
    {
        return RateRule::where('is_active', true)->with('prices.roomCategory')->orderByDesc('priority')->get()
            ->map(fn (RateRule $rule) => [
                'name' => $rule->name,
                'categories' => $rule->prices->filter(fn ($price) => $price->roomCategory?->is_active)
                    ->groupBy('room_category_id')
                    ->map(fn ($prices) => $prices->sortBy('duration_minutes')->first())
                    ->sortBy(fn ($price) => $price->roomCategory->display_order)
                    ->map(fn ($price) => ['name' => $price->roomCategory->name, 'duration' => (int) $price->duration_minutes, 'price' => (int) $price->price])
                    ->values()->all(),
            ])
            ->filter(fn (array $rule) => $rule['categories'] !== [])->values()->all();
    }
}
