<?php

namespace Tests\Concerns;

use App\Actions\PlaceOrder;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

trait MakesShopData
{
    private int $shopDataSequence = 0;

    protected function makeBrand(string $name = 'Acme'): Brand
    {
        return Brand::firstOrCreate(['name' => $name]);
    }

    protected function makeProduct(array $overrides = []): Product
    {
        // Only create the default brand when the caller did not pick one, so tests that
        // count brands are not polluted by a stray "Acme".
        if (! isset($overrides['brand_id'])) {
            $overrides['brand_id'] = $this->makeBrand()->id;
        }

        return Product::create($overrides + [
            'name' => 'Test Phone',
            'category' => 'phones',
            'price' => '100.00',
            'qty' => 5,
        ]);
    }

    protected function makeCustomer(array $overrides = []): User
    {
        $this->shopDataSequence++;

        return User::create($overrides + [
            'name' => 'Test Customer',
            'email' => "customer{$this->shopDataSequence}@example.test",
            'password' => 'S3cret-pass!',
        ]);
    }

    /** Place a COD order for $quantity of $product through the real PlaceOrder action. */
    protected function placeOrder(User $customer, Product $product, int $quantity = 2): Order
    {
        return app(PlaceOrder::class)->handle($customer, [
            'phone' => '012 345 678',
            'address' => 'Street 1, Phnom Penh',
            'payment_method' => 'cod',
            'lat' => 11.5564,
            'lng' => 104.9282,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ]);
    }

    protected function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Test Admin',
            'email' => 'owner@example.test',
            'password' => 'S3cret-pass!',
        ])->fresh();
    }
}
