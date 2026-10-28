<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->makeAdmin(), 'admin');
    }

    public function test_index_lists_newest_first_with_items_and_customer(): void
    {
        $customer = $this->makeCustomer(['name' => 'Sok Dara', 'email' => 'dara@example.test']);
        $product = $this->makeProduct(['qty' => 20]);
        $first = $this->placeOrder($customer, $product);
        $second = $this->placeOrder($customer, $product);

        $response = $this->actingAsAdmin()->getJson(route('admin-orders.index'))->assertOk();

        $this->assertSame([$second->number, $first->number], array_column($response->json(), 'number'));
        $newest = $response->json('0');
        $this->assertSame($second->id, $newest['id']);
        $this->assertSame('012 345 678', $newest['phone']);
        $this->assertSame('Street 1, Phnom Penh', $newest['address']);
        $this->assertSame(['name' => 'Sok Dara', 'email' => 'dara@example.test'], $newest['customer']);
        $this->assertSame('Test Phone', $newest['items'][0]['name']);
        $this->assertSame(2, $newest['items'][0]['quantity']);
        $this->assertEquals(11.5564, $newest['lat']);
        $this->assertEquals(104.9282, $newest['lng']);
    }

    public function test_index_still_works_after_the_product_and_the_customer_were_deleted(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->placeOrder($customer, $product);

        $product->delete();
        $customer->delete();

        $response = $this->actingAsAdmin()->getJson(route('admin-orders.index'))->assertOk();

        $this->assertNull($response->json('0.customer'));
        $this->assertSame('Test Phone', $response->json('0.items.0.name'));
    }

    public function test_logged_out_and_customer_sessions_are_redirected_to_admin_login(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->placeOrder($customer, $this->makeProduct());

        $this->getJson(route('admin-orders.index'))->assertRedirect(route('admin-login'));
        $this->putJson(route('admin-orders.update', $order), ['status' => 'paid'])->assertRedirect(route('admin-login'));

        $this->actingAs($customer)->getJson(route('admin-orders.index'))->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->putJson(route('admin-orders.update', $order), ['status' => 'paid'])
            ->assertRedirect(route('admin-login'));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_succeed(string $from, string $to): void
    {
        $order = $this->placeOrder($this->makeCustomer(), $this->makeProduct());
        $order->update(['status' => $from]);

        $response = $this->actingAsAdmin()->putJson(route('admin-orders.update', $order), ['status' => $to]);

        $response->assertOk()->assertJson(['ok' => true, 'order' => ['id' => $order->id, 'status' => $to]]);
        $this->assertSame($to, $order->fresh()->status);
    }

    public static function allowedTransitions(): array
    {
        return [
            'pending to paid' => ['pending', 'paid'],
            'pending to delivered' => ['pending', 'delivered'],
            'pending to cancelled' => ['pending', 'cancelled'],
            'paid to delivered' => ['paid', 'delivered'],
            'paid to cancelled' => ['paid', 'cancelled'],
        ];
    }

    #[DataProvider('disallowedTransitions')]
    public function test_disallowed_transitions_are_422(string $from, string $to): void
    {
        $order = $this->placeOrder($this->makeCustomer(), $this->makeProduct());
        $order->update(['status' => $from]);

        $response = $this->actingAsAdmin()->putJson(route('admin-orders.update', $order), ['status' => $to]);

        $response->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertIsString($response->json('error'));
        $this->assertSame($from, $order->fresh()->status);
    }

    public static function disallowedTransitions(): array
    {
        return [
            'paid to pending' => ['paid', 'pending'],
            'delivered to cancelled' => ['delivered', 'cancelled'],
            'cancelled to paid' => ['cancelled', 'paid'],
            'delivered to paid' => ['delivered', 'paid'],
        ];
    }

    public function test_unknown_status_is_422_and_unknown_order_is_404(): void
    {
        $order = $this->placeOrder($this->makeCustomer(), $this->makeProduct());
        $this->actingAsAdmin();

        foreach (['refunded', ''] as $status) {
            $this->putJson(route('admin-orders.update', $order), ['status' => $status])
                ->assertStatus(422)
                ->assertJson(['ok' => false]);
        }
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);

        $this->putJson('/admin/orders/9999', ['status' => 'paid'])->assertNotFound();
    }

    public function test_cancelling_restores_stock_exactly_once(): void
    {
        $product = $this->makeProduct(['qty' => 5]);
        $order = $this->placeOrder($this->makeCustomer(), $product, 2);
        $this->assertSame(3, $product->fresh()->qty);
        $this->actingAsAdmin();

        $this->putJson(route('admin-orders.update', $order), ['status' => 'cancelled'])->assertOk();
        $this->assertSame(5, $product->fresh()->qty);

        $this->putJson(route('admin-orders.update', $order), ['status' => 'cancelled'])->assertStatus(422);
        $this->assertSame(5, $product->fresh()->qty);
    }

    public function test_cancelling_with_a_deleted_product_does_not_fail(): void
    {
        $product = $this->makeProduct();
        $order = $this->placeOrder($this->makeCustomer(), $product);
        $product->delete();

        $this->actingAsAdmin()->putJson(route('admin-orders.update', $order), ['status' => 'cancelled'])
            ->assertOk()
            ->assertJson(['ok' => true, 'order' => ['status' => 'cancelled']]);
    }
}
