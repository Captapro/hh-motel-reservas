<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Foto de un producto (base64), en tabla aparte para no cargarla en cada lista. */
#[Fillable(['product_id', 'mime', 'data'])]
class ProductImage extends Model {}
