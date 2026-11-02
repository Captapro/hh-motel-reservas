<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Response;

/** Foto pública de un producto (la usa la pantalla del TV). */
class ProductImageController extends Controller
{
    public function show(Product $product): Response
    {
        $image = ProductImage::where('product_id', $product->id)->firstOrFail();

        // La URL lleva ?v= con la fecha de la foto: se puede guardar en caché sin miedo.
        return response(base64_decode($image->data), 200, [
            'Content-Type' => $image->mime,
            'Cache-Control' => 'public, max-age=2592000, immutable',
        ]);
    }
}
