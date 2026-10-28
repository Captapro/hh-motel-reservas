<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FurnitureCategory;
use App\Models\FurnitureItem;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FurnitureController extends Controller
{
    public function index(): View
    {
        $categories = FurnitureCategory::with(['items' => fn ($q) => $q->with('rooms')->orderBy('name')])->orderBy('name')->get();
        $items = $categories->flatMap->items;

        return view('admin.furniture.index', [
            'categories' => $categories,
            // El selector de ícono era una lista fija de 10 emojis genéricos
            // de hotel (cama, sillón, TV...) que no cubría el mobiliario real
            // que se carga acá (caballetes, cruces, guillotina...) -- cuando
            // el ícono guardado no calzaba con ninguna opción, el <select>
            // caía en silencio a la primera de la lista, mostrando "Cama"
            // sin importar cuál fuera el mueble real. Ahora es un campo de
            // texto con sugerencias: los íconos ya en uso, más algunos
            // genéricos de partida.
            'iconSuggestions' => $items->pluck('icon')->filter()->unique()
                ->merge(['🛏️', '🪑', '🪞', '🚿', '📺', '📶', '🎲', '💡', '🔊', '🧴'])
                ->unique()->values(),
            'summary' => [
                'types' => $items->count(),
                'units' => $items->sum(fn ($item) => $item->rooms->sum(fn ($room) => (int) $room->pivot->quantity)),
                'rooms' => $items->flatMap->rooms->unique('id')->count(),
                'unassigned' => $items->filter(fn ($item) => $item->rooms->isEmpty())->count(),
            ],
            'roomSummary' => Room::with(['category', 'furniture'])->get()->filter(fn ($room) => $room->furniture->isNotEmpty())->sortBy('name')->values(),
        ]);
    }

    public function category(Request $request, ?FurnitureCategory $category = null): RedirectResponse
    {
        $category ??= new FurnitureCategory;
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('furniture_categories')->ignore($category->id)]]);
        DB::transaction(function () use ($category, $data) {
            $old = $category->exists ? $category->toArray() : null;
            $category->fill($data)->save();
            AuditLog::record(auth()->id(), 'mobiliario.categoria_guardar', 'FurnitureCategory', $category->id, $old, $category->toArray());
        });

        return redirect()->route('admin.furniture.index')->with('status', 'Categoría de mobiliario guardada.');
    }

    public function item(Request $request, ?FurnitureItem $item = null): RedirectResponse
    {
        $item ??= new FurnitureItem;
        $data = $request->validate([
            'furniture_category_id' => ['required', 'integer', 'exists:furniture_categories,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('furniture_items')->where('furniture_category_id', $request->input('furniture_category_id'))->ignore($item->id)],
            'icon' => ['nullable', 'string', 'max:12'],
        ]);
        DB::transaction(function () use ($item, $data) {
            $old = $item->exists ? $item->toArray() : null;
            $item->fill($data)->save();
            AuditLog::record(auth()->id(), 'mobiliario.elemento_guardar', 'FurnitureItem', $item->id, $old, $item->toArray());
        });

        return redirect()->route('admin.furniture.index')->with('status', 'Elemento guardado. Ya puedes asignarlo a los Playrooms.');
    }

    public function destroyCategory(FurnitureCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->withErrors(['category' => 'No se puede eliminar esta categoría porque todavía tiene elementos. Elimina primero sus elementos.']);
        }
        $old = $category->toArray();
        $category->delete();
        AuditLog::record(auth()->id(), 'mobiliario.categoria_eliminar', 'FurnitureCategory', $category->id, $old, null);

        return back()->with('status', 'Categoría de mobiliario eliminada.');
    }

    public function destroyItem(FurnitureItem $item): RedirectResponse
    {
        if ($item->rooms()->exists()) {
            return back()->withErrors(['item' => 'No se puede eliminar este elemento porque está asignado a un Playroom.']);
        }
        $old = $item->toArray();
        $item->delete();
        AuditLog::record(auth()->id(), 'mobiliario.elemento_eliminar', 'FurnitureItem', $item->id, $old, null);

        return back()->with('status', 'Elemento eliminado.');
    }
}
