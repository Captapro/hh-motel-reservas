<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'thread', 'question', 'status', 'answer', 'error', 'steps', 'input_tokens', 'output_tokens'])]
class AnalystRun extends Model
{
    protected function casts(): array
    {
        return ['steps' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['listo', 'error'], true);
    }
}
