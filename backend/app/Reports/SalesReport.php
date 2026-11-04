<?php

namespace App\Reports;

use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Sales for one day, one week (Monday to Sunday) or one calendar month, in shop time.
 *
 * A sale is an order that is not cancelled, counted on the day it was placed. The orders are
 * loaded once and summed in PHP in whole cents (as the dashboard chart does), so the JSON
 * summary and the CSV download are built from exactly the same data. No HTTP in here.
 */
class SalesReport
{
    /** @var Collection<int, Order> */
    private Collection $orders;

    private function __construct(
        private readonly string $period,
        private readonly CarbonImmutable $date,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {
        $this->orders = $this->loadOrders();
    }

    /** Inputs are already validated; a null date means today in shop time. */
    public static function for(string $period, ?string $date): self
    {
        $timezone = config('shop.timezone');
        $anchor = $date === null
            ? now($timezone)->toImmutable()->startOfDay()
            : CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        $monday = $anchor->startOfWeek(Carbon::MONDAY);

        [$from, $to] = match ($period) {
            'day' => [$anchor, $anchor],
            'week' => [$monday, $monday->addDays(6)],
            'month' => [$anchor->startOfMonth(), $anchor->endOfMonth()->startOfDay()],
            default => throw new InvalidArgumentException("Unknown sales report period [{$period}]."),
        };

        return new self($period, $anchor, $from, $to);
    }

    /** First day of the period, at the start of that day in shop time. */
    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    /** Last day of the period (inclusive), at the start of that day in shop time. */
    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    /** The JSON the Sales Report page shows. */
    public function summary(): array
    {
        $timezone = config('shop.timezone');

        $days = [];
        for ($day = $this->from; $day <= $this->to; $day = $day->addDay()) {
            $days[$day->toDateString()] = ['orders' => 0, 'items' => 0, 'cents' => 0];
        }

        $totalItems = 0;
        $totalCents = 0;
        $products = [];
        $orderRows = [];

        foreach ($this->orders as $order) {
            $local = $order->created_at->copy()->setTimezone($timezone);
            $orderCents = $this->cents($order->total);
            $orderItems = (int) $order->items->sum('quantity');

            $days[$local->toDateString()]['orders']++;
            $days[$local->toDateString()]['items'] += $orderItems;
            $days[$local->toDateString()]['cents'] += $orderCents;
            $totalItems += $orderItems;
            $totalCents += $orderCents;

            foreach ($order->items as $item) {
                $key = $item->name."\0".$item->brand;
                $products[$key] ??= ['name' => $item->name, 'brand' => $item->brand, 'quantity' => 0, 'cents' => 0];
                $products[$key]['quantity'] += $item->quantity;
                $products[$key]['cents'] += $this->cents($item->unit_price) * $item->quantity;
            }

            if ($this->period === 'day') {
                $orderRows[] = [
                    'number' => $order->number,
                    'date' => $local->toDateString(),
                    'time' => $local->format('H:i'),
                    'status' => $order->status,
                    'payment_method' => $order->payment_method,
                    'items' => $orderItems,
                    'total' => $this->money($orderCents),
                ];
            }
        }

        $orderCount = $this->orders->count();

        // Revenue first, then quantity (both high to low), then name (A to Z).
        usort($products, fn (array $a, array $b) => [$b['cents'], $b['quantity'], $a['name']]
            <=> [$a['cents'], $a['quantity'], $b['name']]);

        return [
            'period' => $this->period,
            'date' => $this->date->toDateString(),
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'totals' => [
                'orders' => $orderCount,
                'items' => $totalItems,
                'revenue' => $this->money($totalCents),
                'averageOrder' => $this->money($orderCount > 0 ? (int) round($totalCents / $orderCount) : 0),
            ],
            'days' => array_map(fn (string $date, array $day) => [
                'date' => $date,
                'orders' => $day['orders'],
                'items' => $day['items'],
                'revenue' => $this->money($day['cents']),
            ], array_keys($days), array_values($days)),
            'orders' => $orderRows,
            'products' => array_map(fn (array $p) => [
                'name' => $p['name'],
                'brand' => $p['brand'],
                'quantity' => $p['quantity'],
                'revenue' => $this->money($p['cents']),
            ], $products),
        ];
    }

    /**
     * The period's non-cancelled orders, oldest first. The bounds are shop-time midnights
     * converted to UTC before they are bound (a Carbon in another timezone would be written
     * in that timezone), and the range is half-open so a midnight order lands in one day only.
     *
     * @return Collection<int, Order>
     */
    private function loadOrders(): Collection
    {
        $appTimezone = config('app.timezone');

        return Order::with('items')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->where('created_at', '>=', $this->from->setTimezone($appTimezone))
            ->where('created_at', '<', $this->to->addDay()->setTimezone($appTimezone))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function cents(string|float|int $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): float
    {
        return $cents / 100;
    }
}
