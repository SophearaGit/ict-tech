<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['ok' => false, 'error' => 'Please sign in.'], 401);
        }

        $orders = $request->user()->orders()
            ->with('items')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json($orders->map(fn (Order $order) => $order->toSummary())->all());
    }
}
