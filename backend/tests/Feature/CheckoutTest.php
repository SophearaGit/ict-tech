<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private function payload(array $items, array $overrides = []): array
    {
        return $overrides + [
            'phone' => '012 345 678',
            'address' => 'Street 1, Phnom Penh',
            'payment_method' => 'cod',
            'lat' => 11.5564,
            'lng' => 104.9282,
            'items' => $items,
        ];
    }

    private function checkout(array $payload)
    {
        return $this->actingAs($this->makeCustomer())->postJson('/checkout', $payload);
    }

    public function test_order_uses_database_prices_and_ignores_browser_prices(): void
    {
        $product = $this->makeProduct(['price' => '100.00']);

        $response = $this->checkout($this->payload([
            ['product_id' => $product->id, 'quantity' => 2, 'price' => 1, 'unit_price' => 1],
        ]));

        $response->assertOk()->assertJson(['ok' => true]);
        $response->assertJsonStructure(['order' => [
            'number', 'status', 'payment_method', 'total', 'created_at',
            'items' => [['name', 'brand', 'unit_price', 'quantity']],
        ]]);
        $this->assertEquals(200.0, $response->json('order.total'));
        $this->assertSame('100.00', OrderItem::first()->unit_price);
    }

    public function test_checkout_decrements_stock(): void
    {
        $product = $this->makeProduct(['qty' => 5]);

        $this->checkout($this->payload([['product_id' => $product->id, 'quantity' => 2]]))->assertOk();

        $this->assertSame(3, $product->fresh()->qty);
    }

    public function test_short_stock_refuses_everything(): void
    {
        $a = $this->makeProduct(['qty' => 5]);
        $b = $this->makeProduct(['name' => 'Test Earbuds', 'qty' => 1]);

        $response = $this->checkout($this->payload([
            ['product_id' => $a->id, 'quantity' => 1],
            ['product_id' => $b->id, 'quantity' => 2],
        ]));

        $response->assertStatus(422)->assertExactJson(['ok' => false, 'error' => 'Only 1 left of Test Earbuds.']);
        $this->assertSame(0, Order::count());
        $this->assertSame(5, $a->fresh()->qty);
    }

    public function test_out_of_stock_product_is_refused(): void
    {
        $product = $this->makeProduct(['qty' => 0]);

        $this->checkout($this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'error' => 'Test Phone is out of stock.']);
    }

    public function test_duplicate_lines_are_merged_before_the_stock_check(): void
    {
        $product = $this->makeProduct(['qty' => 4]);
        $lines = [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3],
        ];
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->postJson('/checkout', $this->payload($lines))->assertStatus(422);

        $product->update(['qty' => 5]);
        $this->actingAs($customer)->postJson('/checkout', $this->payload($lines))->assertOk();

        $this->assertSame(1, OrderItem::count());
        $this->assertSame(5, OrderItem::first()->quantity);
    }

    public function test_unknown_product_is_refused(): void
    {
        $this->checkout($this->payload([['product_id' => 999999, 'quantity' => 1]]))
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'error' => 'One of the items in your cart is no longer available.']);
    }

    public function test_logged_out_checkout_is_401(): void
    {
        $product = $this->makeProduct();

        $this->postJson('/checkout', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertStatus(401)
            ->assertExactJson(['ok' => false, 'error' => 'Please sign in.']);
    }

    public function test_first_order_number_is_ORD_000001(): void
    {
        $product = $this->makeProduct();

        $response = $this->checkout($this->payload([['product_id' => $product->id, 'quantity' => 1]]));

        $this->assertSame('ORD-000001', $response->json('order.number'));
    }

    public function test_snapshot_survives_price_change_and_product_deletion(): void
    {
        $product = $this->makeProduct(['price' => '100.00']);
        $this->checkout($this->payload([['product_id' => $product->id, 'quantity' => 1]]))->assertOk();

        $product->update(['price' => '999.00']);
        $product->delete();

        $item = OrderItem::first();
        $this->assertSame('Test Phone', $item->name);
        $this->assertSame('100.00', $item->unit_price);
    }

    #[DataProvider('oddInput')]
    public function test_odd_input_is_a_422_not_a_500(array $overrides, mixed $quantity = 1): void
    {
        $product = $this->makeProduct();
        $payload = $overrides + $this->payload([['product_id' => $product->id, 'quantity' => $quantity]]);

        $response = $this->checkout($payload);

        $response->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertIsString($response->json('error'));
        $this->assertSame(0, Order::count());
    }

    public static function oddInput(): array
    {
        return [
            'bad phone' => [['phone' => '12ab']],
            'empty cart' => [['items' => []]],
            'quantity zero' => [[], 0],
            'quantity too high' => [[], 100],
            'quantity fractional' => [[], 1.5],
            'quantity not a number' => [[], 'abc'],
            'whitespace address' => [['address' => '   ']],
            'unknown payment method' => [['payment_method' => 'paypal']],
        ];
    }

    public function test_checkout_is_rate_limited(): void
    {
        $customer = $this->makeCustomer();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($customer)->postJson('/checkout', [])->assertStatus(422);
        }

        $this->actingAs($customer)->postJson('/checkout', [])->assertStatus(429);
    }
}
