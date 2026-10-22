<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'Please sign in.'], 401);
            }

            return redirect()->route('account', ['redirect' => '/favorites']);
        }

        $products = $request->user()->favoriteProducts()
            ->orderByDesc('favorites.created_at')
            ->get()
            ->map(fn (Product $p) => $this->transform($p));

        if ($request->wantsJson()) {
            return response()->json($products);
        }

        return view('favorites', ['products' => $products]);
    }

    public function toggle(Request $request)
    {
        if (! $request->user()) {
            return response()->json(['ok' => false, 'error' => 'Please sign in to save favorites.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        $productId = $validator->validated()['product_id'];
        $favorites = $request->user()->favoriteProducts();

        if ($favorites->where('product_id', $productId)->exists()) {
            $favorites->detach($productId);

            return response()->json(['ok' => true, 'favorited' => false]);
        }

        $favorites->attach($productId);

        return response()->json(['ok' => true, 'favorited' => true]);
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
