<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(
            Product::orderBy('id')->get()->map(fn (Product $p) => $this->transform($p))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'qty' => ['required', 'integer', 'min:0'],
            'desc' => ['nullable', 'string'],
            'trending' => ['boolean'],
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'brand' => $validated['brand'],
            'category' => $validated['category'],
            'price' => $validated['price'],
            'qty' => $validated['qty'],
            'description' => $validated['desc'] ?? null,
            'trending' => $validated['trending'] ?? false,
        ]);

        return response()->json($this->transform($product), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'brand' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'qty' => ['sometimes', 'integer', 'min:0'],
            'desc' => ['sometimes', 'nullable', 'string'],
            'trending' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('desc', $validated)) {
            $validated['description'] = $validated['desc'];
            unset($validated['desc']);
        }

        $product->update($validated);

        return response()->json($this->transform($product));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Shape the product for the admin dashboard JS, which expects the same
     * field names as the original static prototype's product objects
     * (`desc` and `imageAlt`, not `description`/`image_alt`), and string ids
     * since every admin.js onclick handler passes ids through as strings.
     */
    protected function transform(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'type' => $product->type,
            'category' => $product->category,
            'price' => (float) $product->price,
            'qty' => $product->qty,
            'desc' => $product->description,
            'specs' => $product->specs,
            'image' => $product->image,
            'imageAlt' => $product->image_alt,
            'trending' => (bool) $product->trending,
            'badge' => $product->badge,
        ];
    }
}
