<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Closure;
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
            'name' => ['bail', 'required', 'string', 'max:255', $this->nameIsNew()],
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
            'name' => ['bail', 'sometimes', 'string', 'max:255', $this->nameIsNew($product)],
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

    /**
     * No two products may share a name, whatever their brand, stock or letter case ("galaxy s24"
     * is "Galaxy S24"). Checked with lower(trim(name)) so it behaves the same on MySQL and on the
     * SQLite test database. When editing, a product that keeps the name it already has is never
     * a duplicate: that keeps the edit form working, and it keeps products that already shared a
     * name before this rule editable. Only a NEW name is checked.
     */
    protected function nameIsNew(?Product $editing = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($editing) {
            $name = mb_strtolower(trim((string) $value));

            if ($editing && mb_strtolower(trim($editing->name)) === $name) {
                return;
            }

            if (Product::whereRaw('lower(trim(name)) = ?', [$name])->exists()) {
                $fail('A product with this name already exists. Edit that product to change its stock instead.');
            }
        };
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
