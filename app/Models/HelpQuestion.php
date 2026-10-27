<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role', 'page', 'question', 'answer', 'status', 'input_tokens', 'output_tokens'])]
class HelpQuestion extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
