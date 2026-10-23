<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\FurnitureCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(): View
    {
        // Orden alfabético puro dejaba "NEW LITE" entre MAX y PLUS — acá
        // manda el display_order de la categoría (GO, LITE, NEW LITE, PLUS,
        // MAX), igual que en el tablero, y alfabético solo como desempate
        // dentro de la misma categoría.
        $rooms = Room::with('category')->get()
            ->sortBy(fn (Room $room) => sprintf('%03d-%s', $room->category->display_order, $room->name))
            ->values();

        return view('admin.rooms.index', ['rooms' => $rooms]);
    }

    /**
     * Hoja imprimible con el QR de cada Playroom -- para no tener que entrar
     * habitación por habitación a buscarlo en el formulario de edición.
     */
    public function qrSheet(): View
    {
        $rooms = Room::with('category')->get()
            ->sortBy(fn (Room $room) => sprintf('%03d-%s', $room->category->display_order, $room->name))
            ->values();

        return view('admin.rooms.qr-sheet', ['rooms' => $rooms]);
    }

    public function create(): View
    {
        return $this->form(new Room());
    }

    public function edit(Room $room): View
    {
        return $this->form($room);
    }

    private function form(Room $room): View
    {
        $room->load('furniture');
        return view('admin.rooms.form', [
            'room' => $room,
            'categories' => RoomCategory::orderBy('display_order')->get(),
            'furnitureCategories' => FurnitureCategory::with(['items' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Room());
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        return $this->save($request, $room);
    }

    /** Una URL por línea -> array limpio, sin líneas vacías. */
    private function parseLines(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function save(Request $request, Room $room): RedirectResponse
    {
        // Solo links (alojados afuera, ej. GHL) -- no se sube ningun
        // archivo al servidor, asi que nada se pierde en un redespliegue.
        $request->merge([
            'photos' => $this->parseLines($request->input('photos_text')),
            'videos' => $this->parseLines($request->input('videos_text')),
        ]);

        $validated = $request->validate([
            'room_category_id' => ['required', 'exists:room_categories,id'],
            'name' => ['required', 'string', 'max:100', 'unique:rooms,name,'.$room->id],
            'buffer_minutes' => ['required', 'integer', 'min:0'],
            'operational_status' => ['required', 'in:activa,mantencion,inactiva,aseo'],
            'operational_note' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['max:500', function (string $attribute, mixed $value, \Closure $fail) {
                $isLocalPath = is_string($value) && preg_match('#^/images/[A-Za-z0-9._/-]+$#', $value) && ! str_contains($value, '..');
                if (! $isLocalPath && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('Cada línea de fotos debe ser un link válido (https://...) o una ruta /images/...');
                }
            }],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['url', 'max:500'],
            'furniture_present' => ['sometimes', 'in:1'],
            'furniture' => ['sometimes', 'array', 'max:500'],
            'furniture.*.id' => ['required', 'integer', 'distinct', 'exists:furniture_items,id'],
            'furniture.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'furniture.*.condition' => ['required', 'in:operativo,reparacion,fuera_de_uso'],
            'furniture.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'videos.*.url' => 'Cada línea de videos debe ser un link válido (https://...).',
        ]);

        $updateFurniture = isset($validated['furniture_present']);
        $items = collect($validated['furniture'] ?? [])->filter(fn ($item) => $item['quantity'] > 0)
            ->mapWithKeys(fn ($item) => [$item['id'] => [
                'quantity' => $item['quantity'], 'condition' => $item['condition'], 'notes' => $item['notes'] ?? null,
            ]])->all();
        unset($validated['furniture'], $validated['furniture_present']);
        DB::transaction(function () use ($room, $validated, $items, $updateFurniture) {
            if ($room->exists) {
                Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
                $room->refresh();
            }
            $old = $room->exists ? $room->load('furniture')->toArray() : null;
            $room->fill($validated)->save();
            if ($updateFurniture) {
                $room->furniture()->sync($items);
            }
            AuditLog::record(auth()->id(), $old ? 'habitacion.editar' : 'habitacion.crear', 'Room', $room->id, $old, $room->load('furniture')->toArray());
        });

        return redirect()->route('admin.rooms.index')->with('status', 'Habitación guardada.');
    }
}
