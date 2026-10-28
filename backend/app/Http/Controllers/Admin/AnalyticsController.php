<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    private const DAYS = 14;

    /** The shop runs on Phnom Penh time; the app (and the database) stay on UTC. */
    private const SHOP_TIMEZONE = 'Asia/Phnom_Penh';

    public function summary(): JsonResponse
    {
        $firstDay = now(self::SHOP_TIMEZONE)->startOfDay()->subDays(self::DAYS - 1);

        $labels = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $labels[] = $firstDay->copy()->addDays($i)->toDateString();
        }
        $orderCount = array_fill_keys($labels, 0);
        $cents = array_fill_keys($labels, 0);

        Order::where('status', '!=', Order::STATUS_CANCELLED)
            ->where('created_at', '>=', $firstDay->copy()->setTimezone(config('app.timezone')))
            ->get(['created_at', 'total'])
            ->each(function (Order $order) use (&$orderCount, &$cents) {
                $day = $order->created_at->copy()->setTimezone(self::SHOP_TIMEZONE)->toDateString();

                if (isset($orderCount[$day])) {
                    $orderCount[$day]++;
                    $cents[$day] += (int) round((float) $order->total * 100);
                }
            });

        return response()->json([
            'labels' => $labels,
            'orders' => array_values($orderCount),
            'revenue' => array_map(fn (int $c) => $c / 100, array_values($cents)),
        ]);
    }
}
