<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ValidatesUniqueNames;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The admin API behind the Categories page, built like the Brands one. A category's code (`slug`) is
 * made from its name once, when it is added, and never changes: products point at the code, so a
 * rename or an icon change cannot break a product or an address.
 */
class CategoryController extends Controller
{
    use ValidatesUniqueNames;

    private const DEFAULT_ICON = '📦';

    public function index(): JsonResponse
    {
        // One grouped count for the whole list (products point at a category by its code).
        $counts = Product::query()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        return response()->json(
            Category::orderBy('id')->get()->map(fn (Category $category) => $this->transform($category, (int) ($counts[$category->slug] ?? 0)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => $this->nameRules(),
            'icon' => $this->iconRules(),
        ], $this->iconMessages());

        $category = new Category(['name' => $validated['name'], 'icon' => $validated['icon'] ?? self::DEFAULT_ICON]);
        $category->slug = Category::uniqueSlugFor($validated['name']);
        $category->save();

        // refresh(): a new row gets its column defaults (is_active = true) from the database, not from this object.
        return response()->json($this->transform($category->refresh(), 0), 201);
    }

    /**
     * Rename, change the icon and/or switch ON/OFF. Each field is validated only when it is sent, so the
     * switch never has to re-send the name. The code is never touched.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', ...$this->nameRules($category)],
            'icon' => ['sometimes', ...$this->iconRules()],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->iconMessages());

        if (array_intersect(['name', 'icon', 'is_active'], array_keys($validated)) === []) {
            throw ValidationException::withMessages(['name' => 'Send a name, an icon or an on/off setting.']);
        }

        if (array_key_exists('name', $validated)) {
            $category->name = $validated['name'];
        }
        if (array_key_exists('icon', $validated)) {
            $category->icon = $validated['icon'] ?? self::DEFAULT_ICON;
        }
        if (array_key_exists('is_active', $validated)) {
            $category->is_active = $validated['is_active'];
        }
        $category->save();

        return response()->json($this->transform($category, $this->productCount($category)));
    }

    public function destroy(Category $category): JsonResponse
    {
        $count = $this->productCount($category);

        if ($count > 0) {
            return response()->json([
                'ok' => false,
                'error' => "{$category->name} still has {$count} ".Str::plural('product', $count).'. Move or delete them first.',
            ], 422);
        }

        $category->delete();

        return response()->json(['ok' => true]);
    }

    /** Rules for a new or renamed category name (the shared rule, see ValidatesUniqueNames). */
    private function nameRules(?Category $ignoring = null): array
    {
        return $this->uniqueNameRules(Category::class, $ignoring, 'That category already exists.');
    }

    /**
     * An icon is optional (empty becomes the default box), at most 16 characters, with no letters,
     * digits, `<`, `>`, `&` or quotes, so a typed word or markup is refused and only emoji-like symbols pass.
     */
    private function iconRules(): array
    {
        return ['nullable', 'bail', 'string', 'max:16', 'regex:/^[^\p{L}\p{N}<>&"\'`]+$/u'];
    }

    private function iconMessages(): array
    {
        $refused = 'Use a single emoji, for example 📱.';

        return ['icon.string' => $refused, 'icon.max' => $refused, 'icon.regex' => $refused];
    }

    private function productCount(Category $category): int
    {
        return Product::where('category', $category->slug)->count();
    }

    private function transform(Category $category, int $productCount): array
    {
        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'icon' => $category->icon,
            'isActive' => (bool) $category->is_active,
            'productCount' => $productCount,
        ];
    }
}
