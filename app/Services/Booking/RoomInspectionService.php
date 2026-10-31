<?php

namespace App\Services\Booking;

use App\Models\Room;
use App\Models\RoomInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Arma y guarda una RoomInspection a partir de un request ya validado --
 * compartido entre el flujo de anfitrión (RoomInspectionController, login
 * normal) y el de mucama (RoomQrController, sesión por PIN desde el QR de
 * la puerta) para no duplicar el cálculo de needs_maintenance ni el guardado
 * de fotos en los dos lugares.
 */
class RoomInspectionService
{
    public function submit(Request $request, Room $room, array $validated, string $inspectedBy): RoomInspection
    {
        $itemKeys = array_keys(RoomInspection::ITEMS);

        // Cada ítem del checklist fijo tiene que venir marcado -- si falta
        // alguno (formulario incompleto), se cuenta como "falla" para no
        // dejar pasar una inspección a medias como si estuviera todo bien.
        $checklist = collect($itemKeys)->mapWithKeys(
            fn (string $key) => [$key => $validated['checklist'][$key] ?? 'falla']
        )->all();
        $checklist['furniture'] = collect($room->furniture)->mapWithKeys(fn ($item) => [
            (string) $item->id => $validated['checklist']['furniture'][$item->id] ?? 'falla',
        ])->all();

        $needsMaintenance = in_array('falla', $checklist, true) || in_array('falla', $checklist['furniture'], true);

        $data = [
            'room_id' => $room->id,
            'inspected_by' => $inspectedBy,
            'checklist' => $checklist,
            'needs_maintenance' => $needsMaintenance,
            'notes' => $validated['notes'] ?? null,
        ];
        if (Schema::hasColumn('room_inspections', 'shift')) {
            $data['shift'] = $validated['shift'] ?? null;
        }
        if (Schema::hasColumn('room_inspections', 'defects')) {
            $data['defects'] = $validated['defects'] ?? null;
        }
        $inspection = RoomInspection::create($data);

        // Fotos comprimidas en la base de datos (no en el disco del servidor).
        $uploads = $request->file('photos', []);
        if ($uploads && Schema::hasColumn('room_inspections', 'photos')) {
            $store = app(InspectionPhotoStore::class);
            $inspection->update(['photos' => collect($uploads)->map(fn ($photo) => $store->store($inspection, (string) file_get_contents($photo->getRealPath())))->values()->all()]);
        }

        return $inspection;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function validationRules(): array
    {
        $itemKeys = array_keys(RoomInspection::ITEMS);

        // "checklist.*" no alcanza -- también matchea "checklist.furniture"
        // (que es un array de ítems, no "ok"/"falla") y esa fila siempre
        // fallaba la regla in:ok,falla. Los ítems fijos y el mobiliario se
        // validan por separado.
        return [
            'checklist' => ['required', 'array'],
            ...collect($itemKeys)->mapWithKeys(fn (string $key) => ["checklist.{$key}" => ['required', 'in:ok,falla']])->all(),
            'checklist.furniture' => ['nullable', 'array'],
            'checklist.furniture.*' => ['required', 'in:ok,falla'],
            'notes' => ['nullable', 'string', 'max:500'],
            'defects' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
