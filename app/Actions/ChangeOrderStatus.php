<?php

namespace App\Actions;

use App\Exceptions\OrderRejected;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ChangeOrderStatus
{
    /** Status → the statuses it may move to. delivered and cancelled are final. */
    public const TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_PAID, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
        Order::STATUS_PAID => [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
    ];

    /**
     * Move an order to a new status; cancelling gives the stock back.
     *
     * @throws OrderRejected when the order may not move to $status
     */
    public function handle(Order $order, string $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            // Re-read under a lock so a double click can't cancel (and restock) twice.
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($status, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw new OrderRejected("A {$locked->status} order can't be changed to {$status}.");
            }

            $locked->update(['status' => $status]);

            if ($status === Order::STATUS_CANCELLED) {
                foreach ($locked->items as $item) {
                    if ($item->product_id !== null) {
                        Product::whereKey($item->product_id)->increment('qty', $item->quantity);
                    }
                }
            }

            return $locked->load('items', 'user');
        });
    }
}
