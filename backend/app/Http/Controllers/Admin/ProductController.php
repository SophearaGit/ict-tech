<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(
            Product::with('brand')->orderBy('id')->get()->map(fn (Product $p) => $this->transform($p))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['bail', 'required', 'integer', 'exists:brands,id'],
            'category' => ['required', Rule::in($this->categorySlugs())],
            'price' => ['required', 'numeric', 'min:0'],
            'qty' => ['required', 'integer', 'min:0'],
            'desc' => ['nullable', 'string'],
            'trending' => ['boolean'],
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'brand_id' => $validated['brand_id'],
            'category' => $validated['category'],
            'price' => $validated['price'],
            'qty' => $validated['qty'],
            'description' => $validated['desc'] ?? null,
            'trending' => $validated['trending'] ?? false,
        ]);

        return response()->json($this->transform($product->load('brand')), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'brand_id' => ['bail', 'sometimes', 'integer', 'exists:brands,id'],
            'category' => ['sometimes', Rule::in($this->categorySlugs())],
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

        return response()->json($this->transform($product->load('brand')));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['ok' => true]);
    }

    /** The real category slugs (config/shop.php is the single source of truth). */
    protected function categorySlugs(): array
    {
        return array_keys(config('shop.categories'));
    }

    /**
     * Shape the product for the admin dashboard JS, which expects the same
     * field names as the original static prototype's product objects
     * (`desc` and `imageAlt`, not `description`/`image_alt`), and string ids
     * since every admin.js onclick handler passes ids through as strings.
     * `brand` is the brand's name; `brandId` is what the form's select posts back.
     */
    protected function transform(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'brand' => $product->brand->name,
            'brandId' => (int) $product->brand_id,
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
