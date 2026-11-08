<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A shop category. `slug` is the short code products point at (`products.category`); it is written once
 * and never changes, so a rename never breaks an address or a product. `is_active` is the ON/OFF switch,
 * written by the admin controller with forceFill, never mass-assigned from a request. There is no
 * products() relation: products join by `products.category = categories.slug`, and the controller counts them.
 */
#[Fillable(['slug', 'name', 'icon'])]
class Category extends Model
{
    /** Categories that are switched ON. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
