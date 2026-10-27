<?php

namespace App\Actions;

use App\Exceptions\OrderRejected;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaceOrder
{
    /**
     * Save an order at the current database prices and take the stock.
     *
     * @param  array{phone: string, address: string, payment_method: string, lat?: mixed, lng?: mixed, items: list<array{product_id: int, quantity: int}>}  $data
     *
     * @throws OrderRejected when a product is gone or there is not enough stock; nothing is saved then
     */
    public function handle(User $user, array $data): Order
    {
        $wanted = [];
        foreach ($data['items'] as $line) {
            $id = (int) $line['product_id'];
            $wanted[$id] = ($wanted[$id] ?? 0) + (int) $line['quantity'];
        }
        ksort($wanted);

        return DB::transaction(function () use ($user, $data, $wanted) {
            $products = Product::whereIn('id', array_keys($wanted))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($wanted as $id => $quantity) {
                if (! $products->has($id)) {
                    throw new OrderRejected('One of the items in your cart is no longer available.');
                }
            }

            foreach ($wanted as $id => $quantity) {
                $product = $products[$id];

                if ($product->qty < 1) {
                    throw new OrderRejected("{$product->name} is out of stock.");
                }
                if ($product->qty < $quantity) {
                    throw new OrderRejected("Only {$product->qty} left of {$product->name}.");
                }
            }

            $totalCents = 0;
            foreach ($wanted as $id => $quantity) {
                $totalCents += $this->cents($products[$id]->price) * $quantity;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => Order::STATUS_PENDING,
                'payment_method' => $data['payment_method'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'total' => number_format($totalCents / 100, 2, '.', ''),
            ]);
            $order->update(['number' => sprintf('ORD-%06d', $order->id)]);

            foreach ($wanted as $id => $quantity) {
                $product = $products[$id];

                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                ]);
                $product->decrement('qty', $quantity);
            }

            return $order->load('items');
        });
    }

    private function cents(string|float|int $price): int
    {
        return (int) round((float) $price * 100);
    }
}
