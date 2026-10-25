<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComboController extends Controller
{
    public function index(): View
    {
        return view('admin.combos.index', ['combos' => Combo::with('items.product')->orderBy('display_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.combos.form', ['combo' => new Combo]);
    }

    public function edit(Combo $combo): View
    {
        return view('admin.combos.form', [
            'combo' => $combo,
            'items' => $combo->items()->with('product')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $combo = $this->save($request, new Combo);

        return redirect()->route('admin.combos.edit', $combo)->with('status', 'Combo creado — ahora agrégale productos.');
    }

    public function update(Request $request, Combo $combo): RedirectResponse
    {
        $this->save($request, $combo);

        return redirect()->route('admin.combos.edit', $combo)->with('status', 'Combo actualizado.');
    }

    private function save(Request $request, Combo $combo): Combo
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'display_order' => ['required', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $old = $combo->exists ? $combo->toArray() : null;
        $combo->fill($validated)->save();
        AuditLog::record(auth()->id(), $old ? 'combo.editar' : 'combo.crear', 'Combo', $combo->id, $old, $combo->toArray());

        return $combo;
    }

    public function storeItem(Request $request, Combo $combo): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $item = ComboItem::updateOrCreate(
            ['combo_id' => $combo->id, 'product_id' => $validated['product_id']],
            ['quantity' => $validated['quantity']]
        );

        AuditLog::record(auth()->id(), 'combo.item_agregar', 'ComboItem', $item->id, null, $item->toArray());

        return redirect()->route('admin.combos.edit', $combo)->with('status', 'Producto agregado al combo.');
    }

    public function destroyItem(Combo $combo, ComboItem $item): RedirectResponse
    {
        AuditLog::record(auth()->id(), 'combo.item_eliminar', 'ComboItem', $item->id, $item->toArray(), null);
        $item->delete();

        return redirect()->route('admin.combos.edit', $combo)->with('status', 'Producto quitado del combo.');
    }

    public function destroy(Combo $combo): RedirectResponse
    {
        if ($combo->bookingAddons()->exists()) {
            return back()->withErrors(['combo' => 'Este combo ya fue vendido en reservas. Déjalo inactivo para conservar el historial.']);
        }
        $old = $combo->toArray();
        $combo->items()->delete();
        $combo->delete();
        AuditLog::record(auth()->id(), 'combo.eliminar', 'Combo', $combo->id, $old, null);

        return redirect()->route('admin.combos.index')->with('status', 'Combo eliminado.');
    }
}
