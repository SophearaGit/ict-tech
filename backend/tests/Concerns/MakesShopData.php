<?php

namespace Tests\Concerns;

use App\Models\Admin;
use App\Models\Product;
use App\Models\User;

trait MakesShopData
{
    private int $shopDataSequence = 0;

    protected function makeProduct(array $overrides = []): Product
    {
        return Product::create($overrides + [
            'name' => 'Test Phone',
            'brand' => 'Acme',
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

    protected function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Test Admin',
            'email' => 'owner@example.test',
            'password' => 'S3cret-pass!',
        ])->fresh();
    }
}
