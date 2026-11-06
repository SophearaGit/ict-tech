<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    public function test_favorites_json_carries_the_brand_name(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $customer->favoriteProducts()->attach($product->id);

        $response = $this->actingAs($customer)->getJson(route('favorites'))->assertOk();

        $this->assertSame('Acme', $response->json('0.brand'));
    }
}
