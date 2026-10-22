<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    /** Brand pills on the full /shop page (category pages derive theirs from the DB). */
    private const ALL_BRANDS = [
        ['id' => 'apple',   'label' => 'Apple'],
        ['id' => 'samsung', 'label' => 'Samsung'],
        ['id' => 'xiaomi',  'label' => 'Xiaomi'],
        ['id' => 'sony',    'label' => 'Sony'],
        ['id' => 'anker',   'label' => 'Anker'],
        ['id' => 'jtrust',  'label' => 'Jtrust'],
        ['id' => 'asus',    'label' => 'Asus'],
        ['id' => 'dell',    'label' => 'Dell'],
    ];

    /** All products — the full /shop page with category tabs and brand filter. */
    public function index()
    {
        $products = Product::orderBy('id')->get()->map(fn (Product $p) => $this->transform($p));

        return view('shop', [
            'products' => $products,
            'favoriteIds' => $this->favoriteIds(),
            'category' => null,
            'brands' => self::ALL_BRANDS,
        ]);
    }

    /**
     * One category only (/shop/phones, ...). The brand list is built from the
     * products actually in that category, so it never offers a brand with
     * nothing to show. Unknown slugs 404 via the route's whereIn constraint.
     */
    public function category(string $category)
    {
        $products = Product::where('category', $category)->orderBy('id')->get();

        $brands = $products
            ->groupBy(fn (Product $p) => mb_strtolower($p->brand))
            ->map(fn ($group, $id) => [
                'id' => $id,
                'label' => $group->first()->brand,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('shop', [
            'products' => $products->map(fn (Product $p) => $this->transform($p))->values(),
            'favoriteIds' => $this->favoriteIds(),
            'category' => ['slug' => $category] + config("shop.categories.{$category}"),
            'brands' => $brands,
        ]);
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
            'brand' => $p->brand,
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
