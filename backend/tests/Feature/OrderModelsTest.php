<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class OrderModelsTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private function makeOrderFor(?int $userId, Product $product): Order
    {
        $order = Order::create([
            'user_id' => $userId,
            'status' => Order::STATUS_PENDING,
            'payment_method' => 'cod',
            'phone' => '012345678',
            'address' => 'Street 1, Phnom Penh',
            'total' => '200.00',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'unit_price' => '100.00',
            'quantity' => 2,
        ]);

        return $order;
    }

    public function test_order_item_keeps_its_snapshot_when_the_product_is_deleted(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrderFor($this->makeCustomer()->id, $product);

        $product->delete();

        $item = $order->items()->first();
        $this->assertSame('Test Phone', $item->name);
        $this->assertSame('100.00', $item->unit_price);
        $this->assertNull($item->product_id);
    }

    public function test_order_survives_deleting_the_customer(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrderFor($customer->id, $this->makeProduct());

        $customer->delete();

        $order = $order->fresh();
        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
    }

    public function test_deleting_an_order_deletes_its_items(): void
    {
        $order = $this->makeOrderFor($this->makeCustomer()->id, $this->makeProduct());
        $this->assertSame(1, OrderItem::count());

        $order->delete();

        $this->assertSame(0, OrderItem::count());
    }
}
