<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ofertas programadas = cupones con auto_apply. No tienen código: se aplican
 * solas cuando el cliente elige una habitación en oferta dentro de la
 * vigencia. Backend compartido con Cupones, UI aparte porque el flujo mental
 * es distinto ("estas piezas en oferta esta semana").
 */
class OfferController extends Controller
{
    public function index(): View
    {
        $offers = Coupon::offers()
            ->with(['rooms:id,name', 'roomCategories:id,name'])
            ->orderByDesc('is_active')
            ->orderByDesc('ends_at')
            ->get();

        return view('admin.offers.index', ['offers' => $offers]);
    }

    public function create(): View
    {
        return view('admin.offers.form', [
            'offer' => new Coupon(['discount_type' => 'percentage', 'is_active' => true]),
            'rooms' => Room::with('category')->orderBy('name')->get(),
            'categories' => RoomCategory::orderBy('display_order')->get(),
        ]);
    }

    public function edit(Coupon $offer): View
    {
        abort_unless($offer->auto_apply, 404);

        return view('admin.offers.form', [
            'offer' => $offer->load(['rooms:id', 'roomCategories:id']),
            'rooms' => Room::with('category')->orderBy('name')->get(),
            'categories' => RoomCategory::orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Coupon);
    }

    public function update(Request $request, Coupon $offer): RedirectResponse
    {
        abort_unless($offer->auto_apply, 404);

        return $this->save($request, $offer);
    }

    private function save(Request $request, Coupon $offer): RedirectResponse
    {
        $validated = $request->validate([
            'internal_name' => ['required', 'string', 'max:100'],
            'discount_type' => ['required', 'in:percentage,precio_fijo'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'included_extra_guests' => ['nullable', 'integer', 'min:0', 'max:5'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'allowed_weekdays' => ['nullable', 'array'],
            'allowed_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'allowed_time_start' => ['nullable'],
            'allowed_time_end' => ['nullable'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:room_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return back()->withInput()->withErrors(['discount_value' => 'El porcentaje no puede ser mayor a 100.']);
        }

        if (empty($validated['room_ids']) && empty($validated['category_ids'])) {
            return back()->withInput()->withErrors(['room_ids' => 'Elige al menos una habitación o una categoría para la oferta.']);
        }

        $old = $offer->exists ? $offer->toArray() : null;

        $offer->fill([
            'code' => null,
            'auto_apply' => true,
            'internal_name' => $validated['internal_name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'included_extra_guests' => (int) ($validated['included_extra_guests'] ?? 0),
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'allowed_weekdays' => $request->filled('allowed_weekdays') ? $validated['allowed_weekdays'] : null,
            'allowed_time_start' => $validated['allowed_time_start'] ?: null,
            'allowed_time_end' => $validated['allowed_time_end'] ?: null,
            'is_stackable' => false,
            'requires_verification' => false,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $offer->rooms()->sync($validated['room_ids'] ?? []);
        $offer->roomCategories()->sync($validated['category_ids'] ?? []);

        AuditLog::record(auth()->id(), $old ? 'oferta.editar' : 'oferta.crear', 'Coupon', $offer->id, $old, $offer->fresh()->toArray());

        return redirect()->route('admin.offers.index')->with('status', 'Oferta guardada.');
    }

    public function toggle(Coupon $offer): RedirectResponse
    {
        abort_unless($offer->auto_apply, 404);

        $offer->update(['is_active' => ! $offer->is_active]);
        AuditLog::record(auth()->id(), 'oferta.toggle', 'Coupon', $offer->id, null, ['is_active' => $offer->is_active]);

        return redirect()->route('admin.offers.index');
    }
}
