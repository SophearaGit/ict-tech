<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'number', 'status', 'payment_method', 'phone', 'address', 'lat', 'lng', 'total'])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function toSummary(): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'total' => (float) $this->total,
            'created_at' => $this->created_at->toIso8601String(),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'brand' => $item->brand,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
            ])->all(),
        ];
    }

    public function toAdminArray(): array
    {
        return $this->toSummary() + [
            'id' => $this->id,
            'phone' => $this->phone,
            'address' => $this->address,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'customer' => $this->user ? ['name' => $this->user->name, 'email' => $this->user->email] : null,
        ];
    }
}
