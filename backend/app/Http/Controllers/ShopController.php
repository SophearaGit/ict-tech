<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    /**
     * All products — the full /shop page with category tabs and brand filter. The brand
     * buttons are the brands that have at least one product, so a brand with nothing
     * to show never appears.
     */
    public function index()
    {
        $products = Product::with('brand')->orderBy('id')->get()->map(fn (Product $p) => $this->transform($p));

        return view('shop', [
            'products' => $products,
            'favoriteIds' => $this->favoriteIds(),
            'category' => null,
            'brands' => $this->brandPills(Brand::whereHas('products')->get()),
        ]);
    }

    /**
     * One category only (/shop/phones, ...). The brand list is limited to brands that have
     * products in that category. Unknown slugs 404 via the route's whereIn constraint.
     */
    public function category(string $category)
    {
        $products = Product::with('brand')->where('category', $category)->orderBy('id')->get();
        $brands = Brand::whereHas('products', fn ($query) => $query->where('category', $category))->get();

        return view('shop', [
            'products' => $products->map(fn (Product $p) => $this->transform($p))->values(),
            'favoriteIds' => $this->favoriteIds(),
            'category' => ['slug' => $category] + config("shop.categories.{$category}"),
            'brands' => $this->brandPills($brands),
        ]);
    }

    /**
     * The brand filter buttons shop.js builds from `BRANDS`: id is the lowercase name (what
     * shop.js compares against `p.brand.toLowerCase()`), label is the name as written.
     * Sorted ignoring case in PHP so MySQL and SQLite order them the same way.
     */
    private function brandPills(iterable $brands): array
    {
        return collect($brands)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Brand $brand) => ['id' => mb_strtolower($brand->name), 'label' => $brand->name])
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
