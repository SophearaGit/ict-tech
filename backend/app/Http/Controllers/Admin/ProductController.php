<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    public function index()
    {
        // The categories are looked up once for the whole list, not once per product.
        $categories = Category::all()->keyBy('slug');

        return response()->json(
            Product::with('brand')->orderBy('id')->get()->map(fn (Product $p) => $this->transform($p, $categories))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:255', $this->nameIsNew()],
            'brand_id' => ['bail', 'required', 'integer', 'exists:brands,id', $this->brandIsOn()],
            'category' => ['bail', 'required', 'string', $this->categoryIsOn()],
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

        return response()->json($this->transform($product->load('brand'), $this->categoriesOf($product)), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['bail', 'sometimes', 'string', 'max:255', $this->nameIsNew($product)],
            'brand_id' => ['bail', 'sometimes', 'integer', 'exists:brands,id', $this->brandIsOn($product)],
            'category' => ['bail', 'sometimes', 'string', $this->categoryIsOn($product)],
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

        return response()->json($this->transform($product->load('brand'), $this->categoriesOf($product)));
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

    /**
     * A product can only join a brand that is switched ON. When editing, keeping the brand it
     * already has is always fine, even if that brand is OFF: the edit window sends the brand back
     * with every save, and the owner must still be able to fix the stock or price of such a product.
     */
    protected function brandIsOn(?Product $editing = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($editing) {
            if ($editing && (int) $value === (int) $editing->brand_id) {
                return;
            }

            if (Brand::whereKey($value)->where('is_active', false)->exists()) {
                $fail('That brand is switched off.');
            }
        };
    }

    /**
     * A product can only join a category that exists and is switched ON (the code must match the table's
     * code exactly; MySQL would otherwise accept `Phones` for `phones`). When editing, keeping the category
     * it already has is always fine, even if that category is OFF: the edit window sends the category back
     * with every save, and the owner must still be able to fix the stock or price of such a product.
     */
    protected function categoryIsOn(?Product $editing = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($editing) {
            $category = Category::where('slug', $value)->first();

            if ($category === null || $category->slug !== $value) {
                $fail('The selected category is invalid.');

                return;
            }

            if (! $category->is_active && ! ($editing && $editing->category === $value)) {
                $fail('That category is switched off.');
            }
        };
    }

    /** The one category a single product points at, keyed by code (the shape `transform` reads). */
    private function categoriesOf(Product $product): Collection
    {
        return Category::where('slug', $product->category)->get()->keyBy('slug');
    }

    /**
     * Shape the product for the admin dashboard JS, which expects the same
     * field names as the original static prototype's product objects
     * (`desc` and `imageAlt`, not `description`/`image_alt`), and string ids
     * since every admin.js onclick handler passes ids through as strings.
     * `brand` is the brand's name; `brandId` is what the form's select posts back.
     * `category` is the category's code; `categoryName` its name (null for an old code that is not in
     * the table) and `categoryActive` its ON/OFF switch (true for such an old code, which is never hidden).
     *
     * @param  Collection<string, Category>  $categories  the categories, keyed by code
     */
    protected function transform(Product $product, Collection $categories): array
    {
        $category = $categories->get($product->category);

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'brand' => $product->brand->name,
            'brandId' => (int) $product->brand_id,
            'brandActive' => (bool) $product->brand->is_active,
            'type' => $product->type,
            'category' => $product->category,
            'categoryName' => $category?->name,
            'categoryActive' => $category === null || (bool) $category->is_active,
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
