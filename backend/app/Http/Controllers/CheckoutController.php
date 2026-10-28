<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Exceptions\OrderRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    private const ITEM_UNAVAILABLE = 'One of the items in your cart is no longer available.';

    public function store(Request $request, PlaceOrder $placeOrder): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['ok' => false, 'error' => 'Please sign in.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,15}$/'],
            'address' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', 'in:aba,cod'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'between:1,99'],
        ], [
            // A cart saved by an older version of the site can hold ids that aren't database ids at all.
            'items.*.product_id.required' => self::ITEM_UNAVAILABLE,
            'items.*.product_id.integer' => self::ITEM_UNAVAILABLE,
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        try {
            $order = $placeOrder->handle($request->user(), $validator->validated());
        } catch (OrderRejected $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'order' => $order->toSummary()]);
    }
}
