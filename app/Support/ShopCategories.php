<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one list of categories every customer-facing view reads (the shop menu, the category cards and
 * tabs, the category pages). Rows are `['slug', 'label', 'icon', 'desc']`; the name is called `label`
 * because that is the key the views already used when the list lived in config/shop.php.
 * Nothing is cached between calls, so a switch in the admin shows on the next page load.
 */
class ShopCategories
{
    /** Categories that are switched ON, in the order they were created. */
    public function active(): Collection
    {
        $descriptions = $this->descriptions();

        return Category::active()->orderBy('id')->get()
            ->map(fn (Category $category) => $this->row($category, $descriptions[$category->slug] ?? ''))
            ->values();
    }

    /** One category that is switched ON, or null for an OFF or unknown code. The code must match exactly. */
    public function find(string $slug): ?array
    {
        $category = Category::active()->where('slug', $slug)->first();

        // MySQL compares text ignoring case; an address is only valid when it is written exactly.
        if ($category === null || $category->slug !== $slug) {
            return null;
        }

        return $this->row($category, $this->descriptions($slug)[$slug] ?? '');
    }

    /**
     * Code => icon for EVERY category, ON or OFF, so a cart saved before a category was switched off
     * still shows the right icon.
     *
     * @return array<string, string>
     */
    public function icons(): array
    {
        return Category::orderBy('id')->pluck('icon', 'slug')->all();
    }

    private function row(Category $category, string $description): array
    {
        return ['slug' => $category->slug, 'label' => $category->name, 'icon' => $category->icon, 'desc' => $description];
    }

    /**
     * Category code => "Anker · Apple · Samsung": the names of the ON brands that have a product in it,
     * alphabetical ignoring case, at most four. One query for all categories (or for just `$only`).
     * The caller only describes categories that are ON, so the category switch needs no check here.
     *
     * @return array<string, string>
     */
    private function descriptions(?string $only = null): array
    {
        $pairs = DB::table('products')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->where('brands.is_active', true)
            ->when($only !== null, fn ($query) => $query->where('products.category', $only))
            ->distinct()
            ->get(['products.category as category', 'brands.name as brand']);

        return $pairs->groupBy('category')
            ->map(function (Collection $rows) {
                $names = $rows->pluck('brand')->all();
                sort($names, SORT_NATURAL | SORT_FLAG_CASE);

                return implode(' · ', array_slice($names, 0, 4));
            })
            ->all();
    }
}
