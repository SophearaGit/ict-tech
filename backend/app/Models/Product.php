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
     * Products a customer may see or buy: those whose brand is switched ON and whose category is not
     * switched OFF. A category code that is not in the table at all (an old odd value such as `Apple`)
     * does not hide the product. The one rule behind the shop pages, the favorites list and checkout
     * (the admin always sees every product).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->whereHas('brand', fn (Builder $brand) => $brand->where('is_active', true))
            ->whereNotIn('category', Category::where('is_active', false)->select('slug'));
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
