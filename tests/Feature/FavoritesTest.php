<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private function setBrandActive(Brand $brand, bool $active): void
    {
        $brand->forceFill(['is_active' => $active])->save();
    }

    public function test_favorites_of_an_off_brand_are_hidden_and_come_back_when_it_is_on(): void
    {
        $customer = $this->makeCustomer();
        $brand = $this->makeBrand('Nokia');
        $product = $this->makeProduct(['brand_id' => $brand->id]);
        $customer->favoriteProducts()->attach($product->id);

        $this->setBrandActive($brand, false);
        $this->actingAs($customer)->getJson(route('favorites'))->assertOk()->assertExactJson([]);
        $this->assertSame(1, $customer->favoriteProducts()->count(), 'the favorite stays saved while the brand is off');

        $this->setBrandActive($brand, true);
        $this->actingAs($customer)->getJson(route('favorites'))->assertOk()->assertJsonPath('0.id', $product->id);
    }

    public function test_a_product_of_an_off_brand_cannot_be_favorited(): void
    {
        $customer = $this->makeCustomer();
        $brand = $this->makeBrand('Nokia');
        $product = $this->makeProduct(['brand_id' => $brand->id]);
        $this->setBrandActive($brand, false);

        $this->actingAs($customer)->postJson(route('favorites.toggle'), ['product_id' => $product->id])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'error' => 'This product is no longer available.']);

        $this->assertSame(0, $customer->favoriteProducts()->count());
    }

    public function test_favorites_json_carries_the_brand_name(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $customer->favoriteProducts()->attach($product->id);

        $response = $this->actingAs($customer)->getJson(route('favorites'))->assertOk();

        $this->assertSame('Acme', $response->json('0.brand'));
    }
}
