<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['segment', 'label', 'max_avg_interval_days', 'stale_after_days', 'minimum_stays', 'analysis_window_days', 'stars', 'display_order'])]
class CustomerSegmentRule extends Model
{
    protected function casts(): array
    {
        return ['max_avg_interval_days' => 'integer', 'stale_after_days' => 'integer', 'minimum_stays' => 'integer', 'analysis_window_days' => 'integer', 'stars' => 'integer', 'display_order' => 'integer'];
    }
}
