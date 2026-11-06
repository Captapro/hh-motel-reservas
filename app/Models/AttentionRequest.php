<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'name', 'reason', 'attended_at', 'attended_by'])]
class AttentionRequest extends Model
{
    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('attended_at');
    }

    /** Abre el chat con ese número en WhatsApp Web, donde recepción atiende la cuenta de HH. */
    public function whatsappUrl(): string
    {
        return 'https://web.whatsapp.com/send?phone='.preg_replace('/\D/', '', $this->phone);
    }
}
