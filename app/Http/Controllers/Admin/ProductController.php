<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ProductImageFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', ['products' => Product::orderBy('category')->orderBy('display_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product]);
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['product' => $product]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Product);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        return $this->save($request, $product);
    }

    private function save(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0'],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock' => ['required_if:track_inventory,1', 'nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['required_if:track_inventory,1', 'nullable', 'integer', 'min:0'],
            'display_order' => ['required', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:png,webp,jpg,jpeg', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
        ], [
            'image.image' => 'La foto tiene que ser una imagen (PNG, WEBP o JPG).',
            'image.max' => 'La foto pesa más de 10 MB.',
        ]);
        unset($validated['image'], $validated['remove_image']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['track_inventory'] = $request->boolean('track_inventory');
        $validated['stock'] = $validated['track_inventory'] ? (int) ($validated['stock'] ?? 0) : 0;
        $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 5;

        $old = $product->exists ? $product->toArray() : null;
        $product->fill($validated)->save();

        // Foto para la pantalla del TV (va en su propia tabla).
        if ($request->hasFile('image')) {
            [$data, $mime] = ProductImageFile::prepare((string) file_get_contents($request->file('image')->getRealPath()));
            ProductImage::updateOrCreate(['product_id' => $product->id], ['mime' => $mime, 'data' => base64_encode($data)]);
            $product->forceFill(['image_updated_at' => now()])->save();
        } elseif ($request->boolean('remove_image')) {
            ProductImage::where('product_id', $product->id)->delete();
            $product->forceFill(['image_updated_at' => null])->save();
        }
        AuditLog::record(auth()->id(), $old ? 'producto.editar' : 'producto.crear', 'Product', $product->id, $old, $product->toArray());

        return redirect()->route('admin.products.index')->with('status', 'Producto guardado.');
    }

    public function restock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($product, $validated) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $old = $product->only(['stock']);
            $product->update(['stock' => max(0, $product->stock + $validated['delta'])]);

            AuditLog::record(
                auth()->id(),
                'producto.stock_ajustar',
                'Product',
                $product->id,
                $old + ['reason' => null],
                $product->only(['stock']) + ['reason' => $validated['reason'] ?? null, 'delta' => $validated['delta']]
            );

            return redirect()->route('admin.products.index')->with('status', "Stock de {$product->name} actualizado a {$product->stock}.");
        }, 3);
    }
}
