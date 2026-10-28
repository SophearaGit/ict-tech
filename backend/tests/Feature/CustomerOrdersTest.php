<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class CustomerOrdersTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    public function test_customer_sees_only_their_own_orders_newest_first(): void
    {
        $me = $this->makeCustomer();
        $someoneElse = $this->makeCustomer();
        $product = $this->makeProduct(['qty' => 20]);
        $first = $this->placeOrder($me, $product);
        $this->placeOrder($someoneElse, $product);
        $second = $this->placeOrder($me, $product);

        $response = $this->actingAs($me)->getJson(route('orders'))->assertOk();

        $this->assertSame([$second->number, $first->number], array_column($response->json(), 'number'));
        $response->assertJsonStructure([[
            'number', 'status', 'payment_method', 'total', 'created_at',
            'items' => [['name', 'brand', 'unit_price', 'quantity']],
        ]]);
    }

    public function test_logged_out_request_is_401_json(): void
    {
        $this->getJson(route('orders'))
            ->assertStatus(401)
            ->assertExactJson(['ok' => false, 'error' => 'Please sign in.']);
    }

    public function test_list_still_works_after_a_product_was_deleted(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->placeOrder($customer, $product);

        $product->delete();

        $response = $this->actingAs($customer)->getJson(route('orders'))->assertOk();

        $this->assertSame('Test Phone', $response->json('0.items.0.name'));
    }
}
