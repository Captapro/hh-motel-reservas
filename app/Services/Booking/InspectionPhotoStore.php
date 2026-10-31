<?php

namespace App\Services\Booking;

use App\Models\InspectionPhoto;
use App\Models\RoomInspection;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda las fotos de inspección comprimidas en la base de datos (máx. 1600 px,
 * JPEG) y borra las que pasaron RETENTION_DAYS días. Así no se usa el disco del
 * servidor, que es lo que obligaba a cortar el sistema en cada publicación.
 */
class InspectionPhotoStore
{
    private const MAX_SIDE = 1600;

    private const QUALITY = 75;

    /** Guarda el contenido de una imagen y devuelve la referencia "db:{id}". */
    public function store(RoomInspection $inspection, string $contents, ?\DateTimeInterface $takenAt = null): string
    {
        [$data, $mime] = $this->compress($contents);
        $photo = InspectionPhoto::create([
            'room_inspection_id' => $inspection->id,
            'mime' => $mime,
            'data' => base64_encode($data),
            'size' => strlen($data),
            'created_at' => $takenAt ?? now(),
        ]);

        return InspectionPhoto::PREFIX.$photo->id;
    }

    /** Borra las fotos vencidas y las quita de la lista de su inspección. Devuelve cuántas borró. */
    public function prune(): int
    {
        if (! Schema::hasTable('inspection_photos')) {
            return 0;
        }
        $old = InspectionPhoto::where('created_at', '<', now()->subDays(InspectionPhoto::RETENTION_DAYS))->get(['id', 'room_inspection_id']);
        if ($old->isEmpty()) {
            return 0;
        }

        foreach ($old->groupBy('room_inspection_id') as $inspectionId => $photos) {
            $gone = $photos->map(fn ($p) => InspectionPhoto::PREFIX.$p->id)->all();
            $inspection = RoomInspection::find($inspectionId);
            if ($inspection) {
                $inspection->update(['photos' => array_values(array_diff((array) $inspection->photos, $gone))]);
            }
        }
        InspectionPhoto::whereIn('id', $old->pluck('id'))->delete();

        return $old->count();
    }

    /** @return array{0: string, 1: string} contenido y tipo; si GD no puede leerla, se guarda tal cual. */
    private function compress(string $contents): array
    {
        $info = @getimagesizefromstring($contents);
        $image = $info ? @imagecreatefromstring($contents) : false;
        if (! $image) {
            return [$contents, $info['mime'] ?? 'image/jpeg'];
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        $jpeg = (string) ob_get_clean();

        return [$jpeg, 'image/jpeg'];
    }
}
