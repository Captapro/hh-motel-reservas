<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CoreExtra;
use App\Models\HistoricalSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CoreExtraController extends Controller
{
    public function index(): View
    {
        $items = CoreExtra::orderBy('name')->get();
        $known = $items->map(fn (CoreExtra $i) => CoreExtra::key($i->name))->all();

        // Lo que ya aparece como "extra core" en las planillas cargadas, para armar el listado.
        $found = HistoricalSale::where('sale_type', 'EXTRA CORE')->get(['detail', 'amount_main', 'amount_extras'])
            ->groupBy(fn (HistoricalSale $s) => CoreExtra::key($s->detail))
            ->map(fn (Collection $g, string $key) => [
                'name' => $key,
                'lines' => $g->count(),
                'total' => (int) $g->sum(fn (HistoricalSale $s) => $s->total()),
                'listed' => $key !== '' && in_array($key, $known, true),
            ])->sortByDesc('total')->values();

        return view('admin.core_extras.index', ['items' => $items, 'found' => $found]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if (CoreExtra::all()->contains(fn (CoreExtra $i) => CoreExtra::key($i->name) === CoreExtra::key($data['name']))) {
            return back()->withInput()->withErrors(['name' => 'Ese extra core ya está en el listado.']);
        }

        $item = CoreExtra::create(['name' => trim($data['name']), 'price' => $data['price'] ?? null, 'is_active' => true]);
        AuditLog::record(auth()->id(), 'extra_core.crear', 'CoreExtra', $item->id, null, $item->toArray());

        return redirect()->route('admin.core_extras.index')->with('status', "«{$item->name}» agregado al listado.");
    }

    public function update(Request $request, CoreExtra $coreExtra): RedirectResponse
    {
        $data = $this->validated($request);
        if (CoreExtra::where('id', '!=', $coreExtra->id)->get()->contains(fn (CoreExtra $i) => CoreExtra::key($i->name) === CoreExtra::key($data['name']))) {
            return back()->withInput()->withErrors(['name' => 'Ya hay otro extra core con ese nombre.']);
        }

        $old = $coreExtra->toArray();
        $coreExtra->update(['name' => trim($data['name']), 'price' => $data['price'] ?? null, 'is_active' => $request->boolean('is_active')]);
        AuditLog::record(auth()->id(), 'extra_core.modificar', 'CoreExtra', $coreExtra->id, $old, $coreExtra->fresh()->toArray());

        return redirect()->route('admin.core_extras.index')->with('status', 'Extra core actualizado.');
    }

    public function destroy(CoreExtra $coreExtra): RedirectResponse
    {
        AuditLog::record(auth()->id(), 'extra_core.quitar', 'CoreExtra', $coreExtra->id, $coreExtra->toArray(), null);
        $coreExtra->delete();

        return redirect()->route('admin.core_extras.index')->with('status', 'Extra core quitado del listado.');
    }

    /** @return array{name: string, price: ?int} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], ['name.required' => 'Escribe el nombre del extra core.']);
    }
}
