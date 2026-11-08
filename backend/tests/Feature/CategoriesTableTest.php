<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * The categories table, its four starting rows, and the second half of the one rule that decides which
 * products a customer may see (the first half, the brand being ON, is tested in BrandStatusAndLogoColumnsTest).
 * DatabaseMigrations (not RefreshDatabase) because one test runs the migration's down().
 */
class CategoriesTableTest extends TestCase
{
    use DatabaseMigrations, MakesShopData;

    private function switchOff(string $slug): void
    {
        Category::where('slug', $slug)->firstOrFail()->forceFill(['is_active' => false])->save();
    }

    public function test_the_four_current_categories_are_seeded_in_order_and_on(): void
    {
        $rows = Category::orderBy('id')->get(['slug', 'name', 'icon', 'is_active']);

        $this->assertSame([
            ['slug' => 'phones', 'name' => 'Phones', 'icon' => '📱', 'is_active' => true],
            ['slug' => 'earphones', 'name' => 'Earphones', 'icon' => '🎧', 'is_active' => true],
            ['slug' => 'powerbanks', 'name' => 'Power Banks', 'icon' => '🔋', 'is_active' => true],
            ['slug' => 'laptops', 'name' => 'Laptops', 'icon' => '💻', 'is_active' => true],
        ], $rows->map->only(['slug', 'name', 'icon', 'is_active'])->all());
    }

    public function test_a_new_category_is_on(): void
    {
        $category = Category::create(['slug' => 'x', 'name' => 'X', 'icon' => '📦'])->fresh();

        $this->assertTrue($category->is_active);
    }

    public function test_active_scope_selects_only_categories_that_are_on(): void
    {
        $this->switchOff('laptops');

        $this->assertSame(['phones', 'earphones', 'powerbanks'], Category::active()->orderBy('id')->pluck('slug')->all());
    }

    public function test_visible_hides_products_of_an_off_category_but_not_of_an_unknown_one(): void
    {
        $this->switchOff('phones');
        $this->makeProduct(['name' => 'iPhone', 'category' => 'phones']);
        $this->makeProduct(['name' => 'AirPods', 'category' => 'earphones']);
        $this->makeProduct(['name' => 'Old Thing', 'category' => 'Apple']); // a legacy code that is not in the table

        $this->assertEqualsCanonicalizing(['AirPods', 'Old Thing'], Product::visible()->pluck('name')->all());
    }

    public function test_visible_still_hides_products_of_an_off_brand(): void
    {
        $off = $this->makeBrand('Nokia');
        $off->forceFill(['is_active' => false])->save();
        $this->makeProduct(['name' => '3310', 'category' => 'phones', 'brand_id' => $off->id]);
        $this->makeProduct(['name' => 'iPhone', 'category' => 'phones']);

        $this->assertSame(['iPhone'], Product::visible()->pluck('name')->all());
    }

    public function test_down_drops_the_table(): void
    {
        // Through the real rollback command, which records it: DatabaseMigrations rolls everything back
        // again when the test ends, and a hand-called down() would make that second rollback fail.
        $this->assertTrue(Schema::hasTable('categories'));

        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $this->assertFalse(Schema::hasTable('categories'));
    }
}
