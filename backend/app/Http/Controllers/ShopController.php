<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Support\ShopCategories;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    public function __construct(private readonly ShopCategories $categories)
    {
    }

    /**
     * All products — the full /shop page with category tabs and brand filter. The brand
     * buttons are the brands that have at least one visible product, so a brand with nothing
     * to show never appears.
     */
    public function index()
    {
        // visible(): products of a switched-off brand or category are not shown, and neither is a brand
        // button whose products are all hidden.
        $products = Product::visible()->with('brand')->orderBy('id')->get()->map(fn (Product $p) => $this->transform($p));
        $brands = Brand::active()->whereHas('products', fn ($query) => $query->visible())->get();

        return view('shop', [
            'products' => $products,
            'favoriteIds' => $this->favoriteIds(),
            'category' => null,
            'categories' => $this->categories->active(),
            'brands' => $this->brandPills($brands),
        ]);
    }

    /**
     * One category only (/shop/phones, ...). The brand list is limited to brands that have visible
     * products in that category. An unknown or switched-off code is a plain 404.
     */
    public function category(string $category)
    {
        $row = $this->categories->find($category) ?? abort(404);

        $products = Product::visible()->with('brand')->where('category', $row['slug'])->orderBy('id')->get();
        $brands = Brand::active()
            ->whereHas('products', fn ($query) => $query->visible()->where('category', $row['slug']))
            ->get();

        return view('shop', [
            'products' => $products->map(fn (Product $p) => $this->transform($p))->values(),
            'favoriteIds' => $this->favoriteIds(),
            'category' => $row,
            'categories' => $this->categories->active(),
            'brands' => $this->brandPills($brands),
        ]);
    }

    /**
     * The brand filter buttons shop.js builds from `BRANDS`: id is the lowercase name (what
     * shop.js compares against `p.brand.toLowerCase()`), label is the name as written, logo is
     * the picture's address or null. Sorted ignoring case in PHP so MySQL and SQLite order them
     * the same way.
     */
    private function brandPills(iterable $brands): array
    {
        return collect($brands)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Brand $brand) => ['id' => mb_strtolower($brand->name), 'label' => $brand->name, 'logo' => $brand->logo_url])
            ->values()
            ->all();
    }

    private function favoriteIds()
    {
        return Auth::check() ? Auth::user()->favoriteProducts()->pluck('products.id') : [];
    }

    private function transform(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'brand' => $p->brand->name,
            'type' => $p->type,
            'category' => $p->category,
            'price' => (float) $p->price,
            'desc' => $p->description,
            'specs' => $p->specs,
            'image' => $p->image,
            'imageAlt' => $p->image_alt,
            'trending' => (bool) $p->trending,
            'badge' => $p->badge,
        ];
    }
}
