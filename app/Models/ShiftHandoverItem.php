<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['shift_handover_id', 'product_id', 'product_name', 'expected_stock', 'counted_stock'])]
class ShiftHandoverItem extends Model
{
    public $timestamps = false;

    public function difference(): int
    {
        return $this->counted_stock - $this->expected_stock;
    }
}
