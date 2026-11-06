<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::withCount('products')->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Brand $brand) => $this->transform($brand));

        return response()->json($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => $this->nameRules()]);

        // refresh(): a new row gets its column defaults (is_active = true) from the database, not from this object.
        $brand = Brand::create($validated)->refresh();

        return response()->json($this->transform($brand->loadCount('products')), 201);
    }

    /**
     * Rename and/or switch ON/OFF. Each field is validated only when it is sent, so the switch
     * never has to re-send the name (and a rename never touches the switch).
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', ...$this->nameRules($brand)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! array_key_exists('name', $validated) && ! array_key_exists('is_active', $validated)) {
            throw ValidationException::withMessages(['name' => 'Send a name or an on/off setting.']);
        }

        if (array_key_exists('name', $validated)) {
            $brand->name = $validated['name'];
        }
        if (array_key_exists('is_active', $validated)) {
            $brand->is_active = $validated['is_active'];
        }
        $brand->save();

        return response()->json($this->transform($brand->loadCount('products')));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $count = $brand->products()->count();

        if ($count > 0) {
            return response()->json([
                'ok' => false,
                'error' => "{$brand->name} still has {$count} ".Str::plural('product', $count).'. Move or delete them first.',
            ], 422);
        }

        $brand->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Rules for a new or renamed brand name. Uniqueness ignores case (and the brand being
     * renamed) and is checked here with lower(name) so it behaves the same on MySQL and on
     * the SQLite test database. Rename rules apply to the NEW name only: a legacy name the
     * migration copied unchanged is never re-validated.
     */
    private function nameRules(?Brand $ignoring = null): array
    {
        return [
            'bail',
            'required',
            'string',
            'max:50',
            'regex:/^[\p{L}\p{N} .\-]+$/u',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoring) {
                $taken = Brand::whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                    ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->id))
                    ->exists();

                if ($taken) {
                    $fail('That brand already exists.');
                }
            },
        ];
    }

    private function transform(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'productCount' => $brand->products_count,
            'isActive' => (bool) $brand->is_active,
            'logoUrl' => $brand->logo_url,
        ];
    }
}
