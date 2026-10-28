<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'price', 'is_active'])]
class CoreExtra extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Nombre comparable: sin mayúsculas ni espacios de más. */
    public static function key(?string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $name)));
    }
}
