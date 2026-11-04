<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'brand_id', 'type', 'category', 'price', 'qty', 'description', 'specs', 'image', 'image_alt', 'trending', 'badge'])]
class Product extends Model
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'qty' => 'integer',
            'specs' => 'array',
            'trending' => 'boolean',
        ];
    }
}
