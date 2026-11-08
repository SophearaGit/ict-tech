<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private ?Admin $admin = null;

    /** One admin per test, even when a test makes several requests. */
    private function actingAsAdmin(): static
    {
        $this->admin ??= $this->makeAdmin();

        return $this->actingAs($this->admin, 'admin');
    }

    public function test_store_creates_a_product_with_brand_and_category(): void
    {
        $samsung = $this->makeBrand('Samsung');

        $response = $this->actingAsAdmin()->postJson(route('admin-products.store'), [
            'name' => 'Galaxy S24',
            'brand_id' => $samsung->id,
            'category' => 'phones',
            'price' => 900,
            'qty' => 5,
            'desc' => 'x',
            'trending' => true,
        ])->assertCreated();

        $this->assertSame('Samsung', $response->json('brand'));
        $this->assertSame($samsung->id, $response->json('brandId'));
        $this->assertSame('phones', $response->json('category'));
        $this->assertSame($samsung->id, Product::firstOrFail()->brand_id);
    }

    public function test_store_rejects_unknown_non_numeric_and_array_brand_ids(): void
    {
        $this->makeBrand();

        foreach ([99999, 'abc', [1]] as $badBrandId) {
            $this->actingAsAdmin()->postJson(route('admin-products.store'), [
                'name' => 'Galaxy S24',
                'brand_id' => $badBrandId,
                'category' => 'phones',
                'price' => 900,
                'qty' => 5,
            ])->assertUnprocessable()->assertJsonValidationErrors(['brand_id']);
        }

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_a_brand_name_as_category_and_a_missing_category(): void
    {
        $brand = $this->makeBrand();
        $payload = ['name' => 'Galaxy S24', 'brand_id' => $brand->id, 'price' => 900, 'qty' => 5];

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $payload + ['category' => 'Apple'])
            ->assertUnprocessable()->assertJsonValidationErrors(['category']);
        $this->actingAsAdmin()->postJson(route('admin-products.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['category']);

        $this->assertSame(0, Product::count());
    }

    public function test_update_changes_brand_and_category_and_returns_the_new_brand_name(): void
    {
        $product = $this->makeProduct();
        $sony = $this->makeBrand('Sony');

        $response = $this->actingAsAdmin()->putJson(route('admin-products.update', $product), [
            'brand_id' => $sony->id,
            'category' => 'earphones',
        ])->assertOk();

        $this->assertSame('Sony', $response->json('brand'));
        $this->assertSame($sony->id, $response->json('brandId'));
        $this->assertSame('earphones', $response->json('category'));
        $this->assertSame($sony->id, $product->fresh()->brand_id);
    }

    public function test_quantity_only_update_works_for_a_product_with_an_invalid_legacy_category(): void
    {
        $product = $this->makeProduct(['category' => 'Apple']);

        $response = $this->actingAsAdmin()->putJson(route('admin-products.update', $product), ['qty' => 9])
            ->assertOk();

        $this->assertSame(9, $response->json('qty'));
        $this->assertSame('Apple', $product->fresh()->category);
    }

    public function test_update_rejects_an_invalid_category(): void
    {
        $product = $this->makeProduct();

        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), ['category' => 'Apple'])
            ->assertUnprocessable()->assertJsonValidationErrors(['category']);

        $this->assertSame('phones', $product->fresh()->category);
    }

    public function test_index_lists_brand_name_and_brand_id(): void
    {
        $acme = $this->makeBrand();
        $sony = $this->makeBrand('Sony');
        $this->makeProduct(['name' => 'First', 'brand_id' => $acme->id]);
        $this->makeProduct(['name' => 'Second', 'brand_id' => $sony->id]);

        $response = $this->actingAsAdmin()->getJson(route('admin-products.index'))->assertOk();

        $this->assertSame(['First', 'Second'], array_column($response->json(), 'name'));
        $this->assertSame(['Acme', 'Sony'], array_column($response->json(), 'brand'));
        $this->assertSame([$acme->id, $sony->id], array_column($response->json(), 'brandId'));
    }

    /** A valid "add product" request body; pass only what a test wants to change. */
    private function newProduct(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Galaxy S24',
            'brand_id' => $this->makeBrand('Samsung')->id,
            'category' => 'phones',
            'price' => 900,
            'qty' => 5,
        ];
    }

    public function test_store_rejects_a_name_that_is_already_in_the_catalog(): void
    {
        $this->makeProduct(['name' => 'Galaxy S24', 'qty' => 12]);

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertSame(1, Product::count());
    }

    public function test_store_rejects_the_same_name_in_other_letter_case_or_with_stray_spaces(): void
    {
        $this->makeProduct(['name' => 'Galaxy S24']);

        foreach (['GALAXY S24', 'galaxy s24', '  Galaxy S24  '] as $name) {
            $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct(['name' => $name]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['name']);
        }

        $this->assertSame(1, Product::count());
    }

    public function test_store_rejects_a_name_that_is_sold_out_too(): void
    {
        // A sold-out product is restocked, not added a second time.
        $this->makeProduct(['name' => 'Galaxy S24', 'qty' => 0]);

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertSame(1, Product::count());
    }

    public function test_store_still_accepts_a_different_name_under_the_same_brand(): void
    {
        $samsung = $this->makeBrand('Samsung');
        $this->makeProduct(['name' => 'Galaxy S24', 'brand_id' => $samsung->id]);

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct([
            'name' => 'Galaxy S24 Ultra',
            'brand_id' => $samsung->id,
        ]))->assertCreated();

        $this->assertSame(2, Product::count());
    }

    public function test_update_rejects_renaming_a_product_to_another_products_name(): void
    {
        $this->makeProduct(['name' => 'Galaxy S24']);
        $other = $this->makeProduct(['name' => 'Galaxy S23']);

        $this->actingAsAdmin()->putJson(route('admin-products.update', $other), ['name' => 'galaxy s24'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertSame('Galaxy S23', $other->fresh()->name);
    }

    public function test_update_allows_saving_a_product_under_its_own_name(): void
    {
        // The edit window always sends the name back along with the changed price and stock.
        $product = $this->makeProduct(['name' => 'Galaxy S24', 'qty' => 3]);

        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), ['name' => 'Galaxy S24', 'qty' => 20])
            ->assertOk();
        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), ['name' => 'GALAXY S24'])
            ->assertOk();

        $this->assertSame('GALAXY S24', $product->fresh()->name);
        $this->assertSame(20, $product->fresh()->qty);
    }

    public function test_update_does_not_lock_out_products_that_already_share_a_name(): void
    {
        // Duplicates may already exist from before this rule; fixing their stock must still work.
        $first = $this->makeProduct(['name' => 'Galaxy S24']);
        $this->makeProduct(['name' => 'Galaxy S24']);

        $this->actingAsAdmin()->putJson(route('admin-products.update', $first), ['name' => 'Galaxy S24', 'qty' => 7])
            ->assertOk();

        $this->assertSame(7, $first->fresh()->qty);
    }

    private function switchedOffBrand(string $name = 'Nokia'): Brand
    {
        $brand = $this->makeBrand($name);
        $brand->forceFill(['is_active' => false])->save();

        return $brand;
    }

    public function test_store_refuses_a_brand_that_is_switched_off(): void
    {
        $nokia = $this->switchedOffBrand();

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct(['brand_id' => $nokia->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_id'])
            ->assertJsonPath('errors.brand_id.0', 'That brand is switched off.');

        $this->assertSame(0, Product::count());
    }

    public function test_a_product_whose_brand_is_off_can_still_have_its_stock_and_price_changed(): void
    {
        // The edit window always sends the brand and name back with the new numbers.
        $nokia = $this->switchedOffBrand();
        $product = $this->makeProduct(['name' => '3310', 'brand_id' => $nokia->id, 'qty' => 3, 'price' => '50.00']);

        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), [
            'name' => '3310', 'brand_id' => $nokia->id, 'qty' => 40, 'price' => 55,
        ])->assertOk()->assertJsonPath('brandActive', false);

        $this->assertSame(40, $product->fresh()->qty);
        $this->assertSame('55.00', $product->fresh()->price);
    }

    public function test_update_refuses_moving_a_product_to_a_brand_that_is_off(): void
    {
        $product = $this->makeProduct();
        $original = $product->brand_id;
        $nokia = $this->switchedOffBrand();

        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), ['brand_id' => $nokia->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.brand_id.0', 'That brand is switched off.');

        $this->assertSame($original, $product->fresh()->brand_id);
    }

    public function test_product_json_carries_brand_active(): void
    {
        $this->makeProduct(['name' => 'First']);
        $this->makeProduct(['name' => 'Second', 'brand_id' => $this->switchedOffBrand()->id]);

        $response = $this->actingAsAdmin()->getJson(route('admin-products.index'))->assertOk();

        $this->assertSame([true, false], array_column($response->json(), 'brandActive'));
    }

    private function switchCategory(string $slug, bool $on): void
    {
        Category::where('slug', $slug)->firstOrFail()->forceFill(['is_active' => $on])->save();
    }

    public function test_store_refuses_a_category_that_is_switched_off(): void
    {
        $this->switchCategory('laptops', false);

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct(['category' => 'laptops']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category'])
            ->assertJsonPath('errors.category.0', 'That category is switched off.');

        $this->assertSame(0, Product::count());
    }

    public function test_store_accepts_a_category_added_in_the_admin(): void
    {
        Category::create(['slug' => 'gaming-mice', 'name' => 'Gaming Mice', 'icon' => '🖱️']);

        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct(['category' => 'gaming-mice']))
            ->assertCreated()->assertJsonPath('category', 'gaming-mice')->assertJsonPath('categoryName', 'Gaming Mice');
    }

    public function test_store_refuses_a_category_code_with_the_wrong_letter_case(): void
    {
        // MySQL compares text ignoring case; the code stored on the product must be the table's exact code.
        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct(['category' => 'Phones']))
            ->assertUnprocessable()->assertJsonValidationErrors(['category']);
        $this->actingAsAdmin()->postJson(route('admin-products.store'), $this->newProduct(['category' => ['phones']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['category']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_product_whose_category_is_off_can_still_have_its_stock_and_price_changed(): void
    {
        // The edit window always sends the category and name back with the new numbers.
        $product = $this->makeProduct(['name' => 'Dell XPS', 'category' => 'laptops', 'qty' => 3, 'price' => '50.00']);
        $this->switchCategory('laptops', false);

        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), [
            'name' => 'Dell XPS', 'category' => 'laptops', 'qty' => 40, 'price' => 55,
        ])->assertOk()->assertJsonPath('categoryActive', false);

        $this->assertSame(40, $product->fresh()->qty);
        $this->assertSame('55.00', $product->fresh()->price);
    }

    public function test_update_refuses_moving_a_product_to_a_category_that_is_off(): void
    {
        $product = $this->makeProduct(['category' => 'phones']);
        $this->switchCategory('laptops', false);

        $this->actingAsAdmin()->putJson(route('admin-products.update', $product), ['category' => 'laptops'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category.0', 'That category is switched off.');

        $this->assertSame('phones', $product->fresh()->category);
    }

    public function test_product_json_carries_category_name_and_active(): void
    {
        $this->makeProduct(['name' => 'On', 'category' => 'phones']);
        $this->makeProduct(['name' => 'Off', 'category' => 'laptops']);
        $this->makeProduct(['name' => 'Odd', 'category' => 'Apple']); // an old code that is not in the table
        $this->switchCategory('laptops', false);

        $response = $this->actingAsAdmin()->getJson(route('admin-products.index'))->assertOk();

        $this->assertSame(['Phones', 'Laptops', null], array_column($response->json(), 'categoryName'));
        $this->assertSame([true, false, true], array_column($response->json(), 'categoryActive'));
    }

    public function test_the_product_list_looks_the_categories_up_once_not_per_product(): void
    {
        foreach (['phones', 'laptops', 'earphones', 'Apple'] as $i => $category) {
            $this->makeProduct(['name' => "Product {$i}", 'category' => $category]);
        }
        $this->actingAsAdmin(); // create the admin before counting
        DB::enableQueryLog();

        $this->getJson(route('admin-products.index'))->assertOk();

        $categoryQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "categories"'));
        $this->assertCount(1, $categoryQueries);
    }

    public function test_guests_are_redirected_to_admin_login(): void
    {
        $product = $this->makeProduct();

        $this->getJson(route('admin-products.index'))->assertRedirect(route('admin-login'));
        $this->postJson(route('admin-products.store'), [])->assertRedirect(route('admin-login'));
        $this->putJson(route('admin-products.update', $product), ['qty' => 1])->assertRedirect(route('admin-login'));
    }
}
