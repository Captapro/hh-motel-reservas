<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OperationalSetting;
use App\Models\RoomCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => RoomCategory::orderBy('display_order')->get(),
            'settings' => OperationalSetting::current(),
        ]);
    }

    /**
     * Espejo de toggleFloor pero por categoría (mismo campo que ya
     * controla si la categoría aparece en el catálogo público -- una
     * categoría "inactiva" lo es en todos lados, no solo en el tablero).
     */
    public function toggleCategory(RoomCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return redirect()->route('admin.categories.index')->with('status', $category->is_active
            ? "{$category->name} habilitada — vuelve a ofrecerse en el tablero y el catálogo."
            : "{$category->name} deshabilitada — sus habitaciones libres ya no se ofrecen.");
    }

    /**
     * Prende/apaga la oferta de un piso, o de un piso+ala en el caso de los
     * pisos 2 y 3 (el motel prioriza el Ala Norte y abre el Ala Sur piso
     * por piso según la capacidad que necesite) -- deja de ofrecerse para
     * reservas nuevas sin tocar lo que ya está ocupado, por llegar, en aseo
     * o fuera de servicio ahí. $field llega ya validado por la ruta contra
     * esta misma lista, así que el whitelist de acá es defensa en profundidad.
     */
    public function toggleFloor(string $field): RedirectResponse
    {
        $labels = [
            'piso_1_enabled' => 'Piso 1',
            'piso_2_norte_enabled' => 'Piso 2 Norte',
            'piso_2_sur_enabled' => 'Piso 2 Sur',
            'piso_3_norte_enabled' => 'Piso 3 Norte',
            'piso_3_sur_enabled' => 'Piso 3 Sur',
        ];
        abort_unless(isset($labels[$field]), 404);

        $setting = OperationalSetting::current();
        $setting->update([$field => ! $setting->$field]);

        return redirect()->route('admin.categories.index')->with('status', $setting->$field
            ? "{$labels[$field]} habilitado — vuelve a ofrecerse en el tablero."
            : "{$labels[$field]} deshabilitado — sus habitaciones libres ya no se ofrecen.");
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new RoomCategory()]);
    }

    public function edit(RoomCategory $category): View
    {
        return view('admin.categories.form', ['category' => $category]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new RoomCategory());
    }

    public function update(Request $request, RoomCategory $category): RedirectResponse
    {
        return $this->save($request, $category);
    }

    private function save(Request $request, RoomCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'former_name' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sales_tip' => ['nullable', 'string', 'max:300'],
            'features' => ['nullable', 'string'],
            'base_capacity' => ['required', 'integer', 'min:1'],
            'max_capacity' => ['required', 'integer', 'gte:base_capacity', 'max:50'],
            'extra_guest_from' => ['required', 'integer', 'min:1'],
            'display_order' => ['required', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['features'] = $validated['features']
            ? array_values(array_filter(array_map('trim', explode("\n", $validated['features']))))
            : [];
        $validated['is_active'] = $request->boolean('is_active');

        $old = $category->exists ? $category->toArray() : null;
        $category->fill($validated)->save();
        AuditLog::record(auth()->id(), $old ? 'categoria.editar' : 'categoria.crear', 'RoomCategory', $category->id, $old, $category->toArray());

        return redirect()->route('admin.categories.index')->with('status', 'Categoría guardada.');
    }
}
