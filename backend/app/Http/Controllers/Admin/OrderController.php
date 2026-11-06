<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeOrderStatus;
use App\Exceptions\OrderRejected;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::with(['items', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json($orders->map(fn (Order $order) => $order->toAdminArray())->all());
    }

    public function update(Request $request, Order $order, ChangeOrderStatus $changeOrderStatus): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:paid,delivered,cancelled'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        try {
            $order = $changeOrderStatus->handle($order, $validator->validated()['status']);
        } catch (OrderRejected $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'order' => $order->toAdminArray()]);
    }
}
