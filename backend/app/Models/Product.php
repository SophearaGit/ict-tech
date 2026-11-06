<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'brand_id', 'type', 'category', 'price', 'qty', 'description', 'specs', 'image', 'image_alt', 'trending', 'badge'])]
class Product extends Model
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Products a customer may see or buy: those whose brand is switched ON. The one rule behind the
     * shop pages, the favorites list and checkout (the admin always sees every product).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereHas('brand', fn (Builder $brand) => $brand->where('is_active', true));
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
