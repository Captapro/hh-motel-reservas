<?php

namespace App\Console\Commands;

use App\Models\InspectionPhoto;
use App\Models\RoomInspection;
use App\Services\Booking\InspectionPhotoStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoverFotosInspeccion extends Command
{
    protected $signature = 'app:fotos-inspeccion-a-base {--simular : Solo cuenta, no mueve nada}';

    protected $description = 'Pasa las fotos de inspección que siguen en el disco del servidor a la base de datos (las de más de 30 días se descartan)';

    public function handle(InspectionPhotoStore $store): int
    {
        $disk = Storage::disk('public');
        $limit = now()->subDays(InspectionPhoto::RETENTION_DAYS);
        $moved = 0;
        $dropped = 0;
        $missing = 0;

        RoomInspection::query()->whereNotNull('photos')->orderBy('id')->each(function (RoomInspection $inspection) use ($disk, $limit, $store, &$moved, &$dropped, &$missing) {
            $refs = [];
            foreach ((array) $inspection->photos as $ref) {
                if (str_starts_with((string) $ref, InspectionPhoto::PREFIX)) {
                    $refs[] = $ref;

                    continue;
                }
                if (! $disk->exists($ref)) {
                    $missing++;

                    continue;
                }
                if ($inspection->created_at->lt($limit)) {
                    $dropped++;

                    continue;
                }
                $moved++;
                $refs[] = $this->option('simular') ? $ref : $store->store($inspection, $disk->get($ref), $inspection->created_at);
            }
            if (! $this->option('simular')) {
                $inspection->update(['photos' => $refs]);
            }
        });

        $this->info(($this->option('simular') ? 'Se moverían' : 'Movidas').": {$moved}. De más de ".InspectionPhoto::RETENTION_DAYS." días (descartadas): {$dropped}. No encontradas en el disco: {$missing}.");
        $this->line('Los archivos del disco no se borran: se van junto con el disco al quitarlo en Render.');

        return self::SUCCESS;
    }
}
