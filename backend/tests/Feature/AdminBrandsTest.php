<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AdminBrandsTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private ?Admin $admin = null;

    /** One admin per test, even when a test makes several requests. */
    private function actingAsAdmin(): static
    {
        $this->admin ??= $this->makeAdmin();

        return $this->actingAs($this->admin, 'admin');
    }

    public function test_index_lists_brands_with_product_counts_ordered_by_name(): void
    {
        $sony = $this->makeBrand('Sony');
        $apple = $this->makeBrand('apple');
        $dell = $this->makeBrand('Dell');
        $this->makeProduct(['brand_id' => $apple->id]);
        $this->makeProduct(['brand_id' => $apple->id]);
        $this->makeProduct(['brand_id' => $dell->id]);

        $response = $this->actingAsAdmin()->getJson(route('admin-brands.index'))->assertOk();

        $this->assertSame(['apple', 'Dell', 'Sony'], array_column($response->json(), 'name'));
        $this->assertSame([2, 1, 0], array_column($response->json(), 'productCount'));
        $this->assertSame([$apple->id, $dell->id, $sony->id], array_column($response->json(), 'id'));
    }

    public function test_store_creates_a_brand(): void
    {
        $response = $this->actingAsAdmin()->postJson(route('admin-brands.store'), ['name' => 'Huawei'])
            ->assertCreated();

        $this->assertSame('Huawei', $response->json('name'));
        $this->assertSame(0, $response->json('productCount'));
        $this->assertSame(Brand::firstOrFail()->id, $response->json('id'));
    }

    public function test_store_rejects_duplicates_ignoring_case_and_padding(): void
    {
        $this->makeBrand('Apple');

        foreach (['apple', '  APPLE '] as $duplicate) {
            $this->actingAsAdmin()->postJson(route('admin-brands.store'), ['name' => $duplicate])
                ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        }

        $this->assertSame(1, Brand::count());
    }

    public function test_store_trims_the_stored_name(): void
    {
        $this->actingAsAdmin()->postJson(route('admin-brands.store'), ['name' => '  Oppo '])->assertCreated();

        $this->assertSame('Oppo', Brand::firstOrFail()->name);
    }

    public function test_store_rejects_bad_names(): void
    {
        foreach (['', str_repeat('a', 51), '<b>x', "O'Neil", 'a_b'] as $badName) {
            $this->actingAsAdmin()->postJson(route('admin-brands.store'), ['name' => $badName])
                ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        }

        $this->assertSame(0, Brand::count());
    }

    public function test_rename_allows_a_case_only_change_of_the_same_brand(): void
    {
        $apple = $this->makeBrand('apple');
        $sony = $this->makeBrand('Sony');

        $this->actingAsAdmin()->putJson(route('admin-brands.update', $apple), ['name' => 'Apple'])
            ->assertOk()->assertJsonPath('name', 'Apple');
        $this->assertSame('Apple', $apple->fresh()->name);

        $this->actingAsAdmin()->putJson(route('admin-brands.update', $sony), ['name' => 'Apple'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->assertSame('Sony', $sony->fresh()->name);
    }

    public function test_a_legacy_brand_with_disallowed_characters_can_be_renamed_to_a_valid_name(): void
    {
        $legacy = Brand::create(['name' => 'Black & Decker']);

        $this->actingAsAdmin()->putJson(route('admin-brands.update', $legacy), ['name' => 'Black and Decker'])
            ->assertOk()->assertJsonPath('name', 'Black and Decker');
    }

    public function test_destroy_removes_an_empty_brand(): void
    {
        $brand = $this->makeBrand('Nokia');

        $this->actingAsAdmin()->deleteJson(route('admin-brands.destroy', $brand))
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, Brand::count());
    }

    public function test_destroy_is_blocked_while_the_brand_has_products(): void
    {
        $acme = $this->makeBrand();
        $this->makeProduct();
        $this->makeProduct();
        $sony = $this->makeBrand('Sony');
        $this->makeProduct(['brand_id' => $sony->id]);

        $this->actingAsAdmin()->deleteJson(route('admin-brands.destroy', $acme))
            ->assertUnprocessable()
            ->assertJson(['ok' => false, 'error' => 'Acme still has 2 products. Move or delete them first.']);
        $this->actingAsAdmin()->deleteJson(route('admin-brands.destroy', $sony))
            ->assertUnprocessable()
            ->assertJson(['ok' => false, 'error' => 'Sony still has 1 product. Move or delete them first.']);

        $this->assertSame(2, Brand::count());
    }

    public function test_guests_and_customers_are_redirected_to_admin_login(): void
    {
        $brand = $this->makeBrand('Apple');
        $customer = $this->makeCustomer();

        $this->getJson(route('admin-brands.index'))->assertRedirect(route('admin-login'));
        $this->postJson(route('admin-brands.store'), ['name' => 'X'])->assertRedirect(route('admin-login'));
        $this->putJson(route('admin-brands.update', $brand), ['name' => 'Y'])->assertRedirect(route('admin-login'));
        $this->deleteJson(route('admin-brands.destroy', $brand))->assertRedirect(route('admin-login'));

        $this->actingAs($customer)->getJson(route('admin-brands.index'))->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->postJson(route('admin-brands.store'), ['name' => 'X'])
            ->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->putJson(route('admin-brands.update', $brand), ['name' => 'Y'])
            ->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->deleteJson(route('admin-brands.destroy', $brand))
            ->assertRedirect(route('admin-login'));

        $this->assertSame(['Apple'], Brand::pluck('name')->all());
    }
}
