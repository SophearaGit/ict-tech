<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\ShopCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * ShopCategories is the one list every customer-facing view reads: ON categories in creation order,
 * each with a name, an icon, a code and a description made from the brands that really have products in it.
 */
class ShopCategoriesSupportTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private function switchOff(string $slug): void
    {
        Category::where('slug', $slug)->firstOrFail()->forceFill(['is_active' => false])->save();
    }

    private function inCategory(string $category, string ...$brands): void
    {
        foreach ($brands as $brand) {
            $this->makeProduct(['name' => "{$brand} in {$category}", 'category' => $category, 'brand_id' => $this->makeBrand($brand)->id]);
        }
    }

    private function description(string $slug): string
    {
        return app(ShopCategories::class)->find($slug)['desc'];
    }

    public function test_active_lists_on_categories_in_creation_order_with_label_icon_and_slug(): void
    {
        Category::create(['slug' => 'gaming-mice', 'name' => 'Gaming Mice', 'icon' => '🖱️']);

        $list = app(ShopCategories::class)->active();

        $this->assertSame(['phones', 'earphones', 'powerbanks', 'laptops', 'gaming-mice'], $list->pluck('slug')->all());
        $this->assertSame(
            ['slug' => 'powerbanks', 'label' => 'Power Banks', 'icon' => '🔋', 'desc' => ''],
            $list->firstWhere('slug', 'powerbanks'),
        );
        $this->assertSame('🖱️', $list->firstWhere('slug', 'gaming-mice')['icon']);
    }

    public function test_an_off_category_is_not_listed_and_find_returns_null_for_it_and_for_an_unknown_code(): void
    {
        $this->switchOff('laptops');
        $categories = app(ShopCategories::class);

        $this->assertSame(['phones', 'earphones', 'powerbanks'], $categories->active()->pluck('slug')->all());
        $this->assertNull($categories->find('laptops'));
        $this->assertNull($categories->find('nope'));
        $this->assertSame('Phones', $categories->find('phones')['label']);
    }

    public function test_icons_include_off_categories(): void
    {
        $this->switchOff('laptops');

        $icons = app(ShopCategories::class)->icons();

        $this->assertSame('💻', $icons['laptops']); // an old cart must still find its icon
        $this->assertSame(['phones', 'earphones', 'powerbanks', 'laptops'], array_keys($icons));
        $this->assertSame('📱', $icons['phones']);
    }

    public function test_description_is_the_on_brands_with_products_in_that_category(): void
    {
        $this->inCategory('phones', 'Samsung', 'Apple', 'Anker');
        $this->inCategory('phones', 'Nokia');
        $this->makeBrand('Nokia')->forceFill(['is_active' => false])->save();
        $this->inCategory('laptops', 'Dell');

        $this->assertSame('Anker · Apple · Samsung', $this->description('phones'));
        $this->assertSame('Dell', $this->description('laptops'));
    }

    public function test_description_shows_at_most_four_brands(): void
    {
        $this->inCategory('phones', 'Echo', 'Alpha', 'delta', 'Charlie', 'Bravo');

        $this->assertSame('Alpha · Bravo · Charlie · delta', $this->description('phones'));
    }

    public function test_description_is_empty_when_no_visible_product_has_that_category(): void
    {
        $this->inCategory('phones', 'Apple');

        $this->assertSame('', $this->description('earphones'));
    }
}
