<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_guests_are_redirected_to_admin_login(): void
    {
        $product = $this->makeProduct();

        $this->getJson(route('admin-products.index'))->assertRedirect(route('admin-login'));
        $this->postJson(route('admin-products.store'), [])->assertRedirect(route('admin-login'));
        $this->putJson(route('admin-products.update', $product), ['qty' => 1])->assertRedirect(route('admin-login'));
    }
}
