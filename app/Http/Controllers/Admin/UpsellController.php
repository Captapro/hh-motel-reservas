<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Combo;
use App\Models\RoomCategory;
use App\Models\UpsellOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpsellController extends Controller
{
    public function index(): View
    {
        return view('admin.upsells.index', [
            'offers' => UpsellOffer::with(['fromCategory', 'toCategory', 'combo'])->orderBy('display_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.upsells.form', $this->formData(new UpsellOffer(['type' => 'category_upgrade', 'is_active' => true])));
    }

    public function edit(UpsellOffer $upsell): View
    {
        return view('admin.upsells.form', $this->formData($upsell));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new UpsellOffer);
    }

    public function update(Request $request, UpsellOffer $upsell): RedirectResponse
    {
        return $this->save($request, $upsell);
    }

    public function toggle(UpsellOffer $upsell): RedirectResponse
    {
        $upsell->update(['is_active' => ! $upsell->is_active]);
        AuditLog::record(auth()->id(), 'upsell.toggle', 'UpsellOffer', $upsell->id, null, ['is_active' => $upsell->is_active]);

        return redirect()->route('admin.upsells.index');
    }

    public function destroy(UpsellOffer $upsell): RedirectResponse
    {
        $old = $upsell->toArray();
        $upsell->delete();
        AuditLog::record(auth()->id(), 'upsell.eliminar', 'UpsellOffer', $upsell->id, $old, null);

        return redirect()->route('admin.upsells.index')->with('status', 'Upsell eliminado.');
    }

    private function formData(UpsellOffer $offer): array
    {
        return [
            'offer' => $offer,
            'categories' => RoomCategory::orderBy('display_order')->get(),
            'combos' => Combo::where('is_active', true)->orderBy('display_order')->get(),
        ];
    }

    private function save(Request $request, UpsellOffer $offer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:category_upgrade,time_extension,combo'],
            'price' => ['nullable', 'integer', 'min:0'],
            'from_room_category_id' => ['nullable', 'exists:room_categories,id'],
            'to_room_category_id' => ['nullable', 'exists:room_categories,id'],
            'extra_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'combo_id' => ['nullable', 'exists:combos,id'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $errors = [];
        if ($validated['type'] === 'category_upgrade') {
            if (empty($validated['from_room_category_id']) || empty($validated['to_room_category_id'])) {
                $errors['from_room_category_id'] = 'Elige la categoría de origen y la de destino.';
            } elseif ($validated['from_room_category_id'] === $validated['to_room_category_id']) {
                $errors['to_room_category_id'] = 'La categoría destino tiene que ser distinta a la de origen.';
            }
            if (($validated['price'] ?? 0) < 1) {
                $errors['price'] = 'Poné el precio fijo del upgrade.';
            }
        }
        if ($validated['type'] === 'time_extension') {
            if (empty($validated['extra_minutes'])) {
                $errors['extra_minutes'] = 'Indicá cuántos minutos suma.';
            }
            if (($validated['price'] ?? 0) < 1) {
                $errors['price'] = 'Poné el precio fijo de la extensión.';
            }
        }
        if ($validated['type'] === 'combo' && empty($validated['combo_id'])) {
            $errors['combo_id'] = 'Elige el combo.';
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        $old = $offer->exists ? $offer->toArray() : null;

        $offer->fill([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'price' => $validated['type'] === 'combo' ? 0 : (int) ($validated['price'] ?? 0),
            'from_room_category_id' => $validated['type'] === 'category_upgrade' ? $validated['from_room_category_id'] : null,
            'to_room_category_id' => $validated['type'] === 'category_upgrade' ? $validated['to_room_category_id'] : null,
            'extra_minutes' => $validated['type'] === 'time_extension' ? $validated['extra_minutes'] : null,
            'combo_id' => $validated['type'] === 'combo' ? $validated['combo_id'] : null,
            'display_order' => (int) ($validated['display_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        AuditLog::record(auth()->id(), $old ? 'upsell.editar' : 'upsell.crear', 'UpsellOffer', $offer->id, $old, $offer->fresh()->toArray());

        return redirect()->route('admin.upsells.index')->with('status', 'Upsell guardado.');
    }
}
