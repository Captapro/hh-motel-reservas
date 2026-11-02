<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con la programación de la pantalla del TV (/pantalla).
 * Se edita en Administración → Pantalla TV.
 */
#[Fillable(['show_combos', 'show_combo_contents', 'combo_cta', 'show_reserve_qr', 'show_review_qr', 'combo_seconds', 'qr_seconds', 'messages'])]
class TvScreenSetting extends Model
{
    public const MAX_MESSAGES = 5;

    protected function casts(): array
    {
        return [
            'show_combos' => 'boolean',
            'show_combo_contents' => 'boolean',
            'show_reserve_qr' => 'boolean',
            'show_review_qr' => 'boolean',
            'combo_seconds' => 'integer',
            'qr_seconds' => 'integer',
            'messages' => 'array',
        ];
    }

    public static function current(): self
    {
        $setting = static::firstOrCreate(['id' => 1]);

        // Recién creada solo trae el id: se recarga para tener los valores por defecto de la tabla.
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }

    /** Mensajes propios encendidos y con título. */
    public function activeMessages(): array
    {
        return collect($this->messages ?? [])
            ->filter(fn ($m) => ! empty($m['active']) && filled($m['title'] ?? null))
            ->values()
            ->all();
    }
}
